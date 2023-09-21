<?php

namespace App\Command;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use App\Service\EntityPuller;
use App\Service\FileReader;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'SubmoduleCrawler',
    description: 'Add a short description for your command',
)]
final class SubmoduleCrawlerCommand extends Command
{
    private string $serializeDirectory;

    /** @var string $cronLogfile | Путь к файлу использования памяти для задачи */
    private string $cronLogfile;

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', '1024M');

        $memoryUsage = memory_get_usage();

        /** @var App_KernelDevDebugContainer $container | Контейнер приложения Symfony */
        $container = $this->getApplication()->getKernel()->getContainer();

        $this->setSerializeDirectory($container->getParameter('inventory_serialize_directory'));
        $this->setCronLogfile($container->getParameter('inventory_cron_execute'));

        /** @var string $crawlerPath | Путь к файлам модуля продукции */
        $crawlerPath = $container->getParameter('inventory_crawler_directory');
        $productsInventoryPath = $crawlerPath . "inventory/";

        if ($this->checkFolder($productsInventoryPath)) {
            $serializedPath = $this->getSerializeDirectory();

            $inventoryTypes = array_diff(
                scandir($productsInventoryPath), ['.', '..', '.gitignore']
            );

            /** @var Registry $doctrineRegistry */
            $doctrineRegistry = $container->get('doctrine');
            $entityManager = $doctrineRegistry->getManager();

            /** @var InventoryRepository $inventoryRepository */
            $inventoryRepository = $entityManager->getRepository(Inventory::class);

            foreach ($inventoryTypes as $type) {
                $serials = array_diff(
                    scandir($productsInventoryPath . $type), ['.', '..', '.gitignore']
                );

                if (count($serials) > 0) {
                    foreach ($serials as $serial) {
                        $serial_file = $serial;
                        $pathinfo = pathinfo($serial_file);
                        $serial = $pathinfo['filename'];

                        if (is_dir($serializedPath . $pathinfo['filename'])) {
                            continue;
                        }

                        echo "\n Using: $serial file. \n";

                        $exist = $inventoryRepository->getSerialExist($type, $serial);

                        if ($exist === false) {
                            $reader = new FileReader();
                            $reader->setReadDirectory($productsInventoryPath);
                            $reader->setFile($type . "/" . $serial_file);
                            $products = $reader->executeCreate();

                            $puller = new EntityPuller();
                            $puller->setLogfilePath(dirname($this->getCronLogfile()));
                            $puller->pullEntities($type, $serial, $products);

                            try {
                                $chunkCount = 0;

                                foreach (array_chunk($products, 1000) as $chunkIndex => $chunk) {
                                    $this->serializeProducts(
                                        $chunk,
                                        $pathinfo['filename'],
                                        "chunk-". $chunkIndex ."-". $serial_file
                                    );

                                    $chunkCount++;
                                }
                            } catch (\Exception | \Throwable) { }

                            file_put_contents(
                                $this->getCronLogfile(),
                                "\n ". date('d-m-Y H:i:s') .
                                "\n Serial: $serial".
                                "\n\tStart with : $memoryUsage. Rise in: ". memory_get_usage() - $memoryUsage .
                                ". Memory peak: ". memory_get_peak_usage() .".\n",
                                FILE_APPEND
                            );

                            return Command::SUCCESS;
                        }
                    }
                }
            }
        }

        return Command::FAILURE;
    }

    private function checkFolder($path) : bool
    {
        if (!is_dir($path)) {
            return false;
        }

        return true;
    }

    private function setSerializeDirectory($path) : void
    {
        $this->serializeDirectory = $path;
    }

    private function getSerializeDirectory() : string
    {
        return $this->serializeDirectory;
    }

    private function setCronLogfile($path) : void
    {
        $this->cronLogfile = $path;
    }

    private function getCronLogfile() : string
    {
        return $this->cronLogfile;
    }

    private function serializeProducts($products, $serial, $filename) : void
    {
        $serializePath = $this->getSerializeDirectory();

        $serialSerializePath =  $serializePath . "/" . $serial . "/";

        if (!is_dir($serialSerializePath)) {
            mkdir($serialSerializePath, recursive: true);
        }

        file_put_contents($serialSerializePath . $filename . ".serial", serialize($products));
    }
}