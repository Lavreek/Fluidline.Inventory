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

#[AsCommand(
    name: 'SubmoduleCrawler',
    description: 'Add a short description for your command',
)]
final class SubmoduleCrawlerCommand extends Command
{
    private string $serializeDirectory;

    private string $lockDirectory;

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

        $this->setSerializeDirectory($container->getParameter('serialize'));
        $this->setLockDirectory($container->getParameter('inventory_crawler_locks_directory'));
        $this->setCronLogfile($container->getParameter('cron'));

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
                            echo "\nIn queue found $serial file.";
                            continue;
                        }

                        echo "\nCheck: serial $serial exist.";

                        $exist = file_exists($this->getLockDirectory() . $serial .".lock");

                        if (!$exist) {
                            $exist = $inventoryRepository->getSerialExist($type, $serial);
                        }

                        if ($exist === false) {
                            echo "\nEnter serial $serial\nUsing: $serial file.";

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
                                        $serial,
                                        "chunk-". $chunkIndex ."-". $serial_file
                                    );

                                    $chunkCount++;
                                }
                            } catch (\Exception | \Throwable) {
                                echo "\n Serialize error in $serial file";
                                return Command::FAILURE;
                            }

                            file_put_contents(
                                $this->getCronLogfile(),
                                "\n ". date('d-m-Y H:i:s') .
                                "\nSerial: $serial".
                                "\n\tStart with : $memoryUsage".
                                "\n\tRise in: ". memory_get_usage() - $memoryUsage .
                                "\n\tMemory peak: ". memory_get_peak_usage(),
                                FILE_APPEND
                            );

                            echo "\n$serial added.";

                            touch($this->getLockDirectory() . $serial . ".lock");

                            return Command::SUCCESS;
                        }
                    }
                }
            }
        }

        echo "\nSerials to adding in queue is not exist.";

        return Command::FAILURE;
    }

    private function checkFolder($path) : bool
    {
        if (!is_dir($path)) {
            return false;
        }

        return true;
    }

    private function createDirectory(string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, recursive: true);
        }
    }

    private function setLockDirectory(string $path) : void
    {
        $this->lockDirectory = $path;
        $this->createDirectory($path);
    }

    private function getLockDirectory() : string
    {
        return $this->lockDirectory;
    }

    private function setSerializeDirectory(string $path) : void
    {
        $this->serializeDirectory = $path;
        $this->createDirectory($path);
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