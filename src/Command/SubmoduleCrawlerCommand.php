<?php

namespace App\Command;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use App\Service\EntityPuller;
use App\Service\FileReader;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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

    private string $crawlerDirectory;

    private mixed $container;

    private ObjectManager $manager;

    private int $memory;

    /** @var string $cronLogfile | Путь к файлу использования памяти для задачи */
    private string $cronLogfile;

    protected function configure(): void
    {
        $this
            ->addOption(
                'file', null, InputOption::VALUE_OPTIONAL,
                'Which file could be serialized?', ''
            );
        ;
    }

    private function initialSettings()
    {
        /** @var App_KernelDevDebugContainer $container | Контейнер приложения Symfony */
        $container = $this->getApplication()->getKernel()->getContainer();

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');
        $this->setManager($doctrineRegistry->getManager());

        $this->setSerializeDirectory($container->getParameter('serialize'));
        $this->setCronLogfile($container->getParameter('cron'));
        $this->setCrawlerDirectory($container->getParameter('products'));
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', '2048M');
        $this->setMemory(memory_get_usage());

        $crawlerFilename = $input->getOption('file');

        $this->initialSettings();

        /** @var string $productsInventoryPath Путь к файлам продукции */
        $productsInventoryPath = $this->getCrawlerDirectory() ."inventory/";

        if ($this->checkFolder($productsInventoryPath)) {
            $inventoryTypes = array_diff(
                scandir($productsInventoryPath), ['.', '..', '.gitignore']
            );

            $entityManager = $this->getManager();

            /** @var InventoryRepository $inventoryRepository */
            $inventoryRepository = $entityManager->getRepository(Inventory::class);

            foreach ($inventoryTypes as $type) {
                /** @var array $files Файлы продукции */
                $files = array_diff(
                    scandir($productsInventoryPath . $type), ['.', '..', '.gitignore']
                );

                $this->setLockDirectory($productsInventoryPath . $type . "/");

                if (count($files) > 0) {
                    foreach ($files as $file) {
                        $fileinfo = pathinfo($file);
                        $serial = $fileinfo['filename'];


                        if (is_dir($this->getSerializeDirectory() . $serial)) {
                            echo "Serial $serial already in queue.\n";
                            continue;
                        }

                        if (file_exists($this->getLockDirectory() . $serial .".lock")) {
                            echo "Serial $serial lock file exist.\n";
                            continue;
                        }

                        echo "Serial $serial using now.\n";

                        $inventoryRepository->removeBySerialType($serial, $type);

                        $reader = new FileReader();
                        $reader->setReadDirectory($productsInventoryPath);
                        $reader->setFile($type . "/" . $file);
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
                                    "chunk-". $chunkIndex ."-". $file
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
                            "\n\tStart with : {$this->getMemory()}".
                            "\n\tRise in: ". memory_get_usage() - $this->getMemory() .
                            "\n\tMemory peak: ". memory_get_peak_usage(),
                            FILE_APPEND
                        );

                        echo "\n$serial added.";

                        die();
                        touch($this->getLockDirectory() . $serial . ".lock");

                        return Command::SUCCESS;
                    }
                }
            }
        } else {
            echo "\nProducts is not exsist.";

            return Command::FAILURE;
        }

        echo "\nSerials to adding in queue is not exist.";

        return Command::FAILURE;
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

    /** @deprecated  */
    private function setContainer(mixed $container) : void
    {
        $this->container = $container;
    }

    /** @deprecated  */
    private function getContainer() : string
    {
        return $this->container;
    }

    private function setMemory(int $memory) : void
    {
        $this->memory = $memory;
    }

    private function getMemory() : int
    {
        return $this->memory;
    }

    private function setManager(ObjectManager $registry) : void
    {
        $this->manager = $registry;
    }

    private function getManager() : ObjectManager
    {
        return $this->manager;
    }

    private function setCrawlerDirectory(string $path) : void
    {
        $this->crawlerDirectory = $path;
        $this->createDirectory($path);
    }

    private function getCrawlerDirectory() : string
    {
        return $this->crawlerDirectory;
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
}