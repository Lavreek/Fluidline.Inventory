<?php

namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
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

/**
 *     C помощью продукции созданной в github.com/Lavreek/Fluidline.InventoryProducts происходит обход всех каталогов
 * с продукцией, чтобы таким образом создать сериализацию, чтобы повысить производительность, а также
 * сократить потребление памяти. Ведь приложение создано для работы на виртуальном хостинге.
 */

#[AsCommand(
    name: 'Crawler',
    description: 'Создание сериализованных образов продукции, как сущности "Inventory"',
)]
final class CrawlerCommand extends Command
{
    /** Ограничение выставляемое процессу при работе на виртуальном хостинге */
    const max_memory_limit = '1024M';

    /** Около максимальное количество элементов способных поместиться в лимит памяти */
    const max_products_count = 100000;

    private Directory $directories;

    private mixed $container;

    private ObjectManager $manager;

    protected function configure(): void
    {
        $this
            ->addOption(
                'file', null, InputOption::VALUE_OPTIONAL,
                'Which file could be serialized?', ''
            );

        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', self::max_memory_limit);
        $executeScriptMemory = memory_get_usage();
        $executeScriptTime = time();

        $forceFile = $input->getOption('file');

        $this->initialSettings();

        $crawlerPath = $this->directories->getCrawlerPath();

        $crawlerTypes = $this->getFiles($crawlerPath);

        foreach ($crawlerTypes as $type) {
            /** @var array $files Файлы продукции */
            $files = $this->getFiles($crawlerPath . $type);

            if (count($files) > 0) {
                foreach ($files as $file) {
                    if (!empty($forceFile)) {
                        if ($file != $forceFile) {
                            continue;
                        }
                    }

                    $fileinfo = pathinfo($file);
                    $serial = $fileinfo['filename'];

                    if (is_dir($this->directories->getSerializePath()  . $serial)) {
                        echo "Serial $serial already in queue.\n";
                        continue;
                    }

                    if (file_exists($this->directories->getLocksPath() . $type ."/". $serial .".lock")) {
                        echo "Serial $serial lock file exist.\n";
                        continue;
                    }

                    echo "Serial $serial using now.\n";

                    $this->removeSerial($type, $serial);

                    $reader = new FileReader();
                    $reader->setReadDirectory($crawlerPath);
                    $reader->setFile($type . "/" . $file);
                    $products = $reader->executeCreate();

                    if (count($products) > self::max_products_count) {
                        echo "Too many products\n";

                        return Command::FAILURE;
                    }

                    $puller = new EntityPuller();
                    $puller->setLogfilePath(dirname($this->directories->getLogfilePath()));
                    $puller->pullEntities($type, $serial, $products);

                    try {
                        foreach (array_chunk($products, 1000) as $chunkIndex => $chunk) {
                            $filename = $chunkIndex ."-". $file;
                            $this->serializeProducts($chunk, $serial, $filename);
                        }

                    } catch (\Exception | \Throwable $exception) {
                        $customMessage = "\n Serialize error in $serial file\n";

                        file_put_contents(
                            $this->directories->getLogfilePath(),
                            $customMessage . $exception->getMessage() ."\n"
                        );

                        echo $customMessage;

                        return Command::FAILURE;
                    }

                    $this->createLogfileResult($executeScriptTime, $executeScriptMemory);

                    echo "\n$serial added.";

                    $lockfile = $this->directories->getLocksPath() . $type ."/". $serial .".lock";

                    if (!$this->directories->checkPath(dirname($lockfile))) {
                        $this->directories->createDirectory(dirname($lockfile));
                    }

                    if (!file_exists($lockfile)) {
                        touch($lockfile);
                    }

                    return Command::SUCCESS;
                }
            }
        }

        echo "\nSerials to adding in queue is not exist.";

        return Command::FAILURE;
    }

    private function initialSettings()
    {
        /** @var $container - Контейнер приложения Symfony */
        $container = $this->getApplication()->getKernel()->getContainer();

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');
        $this->setManager($doctrineRegistry->getManager());

        $this->directories->setProductsPath($container->getParameter('products'));
    }

    private function removeSerial(string $type, string $serial) : void
    {
        $entityManager = $this->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $entityManager->getRepository(Inventory::class);

        $inventoryRepository->removeBySerialType($serial, $type);
    }

    private function getFiles($path) : array
    {
        $difference = ['.', '..', '.gitignore'];
        return array_diff(scandir($path), $difference);
    }

    private function serializeProducts($products, $serial, $filename) : void
    {
        $serialSerializePath =  $this->directories->getSerializePath() ."/$serial/";

        if (!is_dir($serialSerializePath)) {
            mkdir($serialSerializePath, recursive: true);
        }

        file_put_contents($serialSerializePath . $filename . ".serial", serialize($products));
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

    private function setManager(ObjectManager $registry) : void
    {
        $this->manager = $registry;
    }

    private function getManager() : ObjectManager
    {
        return $this->manager;
    }

    private function createLogfileResult(int $start, int $memory) : void
    {
        $currentDate = date('d-m-Y H:i:s');
        $startDate = date('d-m-Y H:i:s', $start);

        $startMemory = ($memory / 1024) / 1024;
        $currentMemory = (memory_get_usage() / 1024) / 1024;
        $riseMemory = $currentMemory - $startMemory;
        $peakMemory = memory_get_peak_usage();

        file_put_contents(
            $this->directories->getLogfilePath(),
            "Symfony command: ImagesPuller\n".
            "Процесс завершён добавления изображений завершён\n".
            "\tВремя начала: $startDate, Время завершения: $currentDate\n".
            "\tИзначальное потребление памяти: $startMemory Мб, Возрастание к концу: $riseMemory\n".
            "\tПик использования памяти: $peakMemory\n",
            FILE_APPEND
        );
    }
}