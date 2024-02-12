<?php
namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
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
 * C помощью продукции созданной в github.com/Lavreek/Fluidline.InventoryProducts происходит обход всех каталогов
 * с продукцией, чтобы таким образом создать сериализацию, чтобы повысить производительность, а также
 * сократить потребление памяти.
 * Ведь приложение создано для работы на виртуальном хостинге.
 */
#[AsCommand(name: 'Crawler', description: 'Создание сериализованных образов продукции, как сущности "Inventory"')]
final class CrawlerCommand extends Command
{
    private Directory $directories;
    private ObjectManager $manager;

    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_OPTIONAL,
            'Какой тип должен быть обработан?', '');
        $this->addOption('serial', null, InputOption::VALUE_OPTIONAL,
            'Какая серия должна быть обработана?', '');
        $this->addOption('file', null, InputOption::VALUE_OPTIONAL,
            'Какой файл должен быть обработан?', '');
        $this->addOption('big', null, InputOption::VALUE_OPTIONAL,
            'Включить в обработку большие ресурсы?', false);
        $this->addOption('memory-limit', null, InputOption::VALUE_OPTIONAL,
            'Включить ограничение ресурсов? Значение в МБ', '-1');
        $this->addOption('max-products', null, InputOption::VALUE_OPTIONAL,
            'Включить ограничение количества ресурсов? Значение в шт', '10000');
        $this->addOption('more-than-one', null, InputOption::VALUE_OPTIONAL,
            'Включить продолжение цикла для обработки?', false);
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processed = 0;
        $this->initialSettings();

        $this->setMemoryLimit($input->getOption('memory-limit'));
        $maxProductCount = $input->getOption('max-products');
        $forceType = $input->getOption('type');
        $forceSerial = $input->getOption('serial');
        $forceFile = $input->getOption('file');
        $bigFiles = $input->getOption('big');
        $moreThanOne = $input->getOption('more-than-one');

        $inventoryPath = $this->directories->getCrawlerPath();
        $inventoryTypes = $this->getFiles($inventoryPath);

        $this->optionalSettings($forceType, $forceSerial);

        foreach ($inventoryTypes as $type) {
            if (!empty($forceType) && $type != $forceType) {
                continue;
            }

            $inventoryTypePath = $inventoryPath . $type;
            $inventorySerials = $this->getFiles($inventoryTypePath);

            if (count($inventorySerials) < 0) {
                continue;
            }

            foreach ($inventorySerials as $file1) {
                $serialInfo = pathinfo($file1);

                if (
                    !empty($forceSerial) && $serialInfo['filename'] != $forceSerial or
                    !empty($forceFile) && $serialInfo['basename'] != $forceFile
                ) {
                    continue;
                }

                $serial = $serialInfo['filename'];

                if ($this->checkFiles($type, $serial, $forceFile)) {
                    continue;
                }

                if (!$bigFiles) {
                    if (file_exists($inventoryPath . "{$type}/{$serial}.big")) {
                        continue;
                    }
                }

                echo "Using type: $type | serial: $serial\n";

                $reader = new FileReader();
                $reader->setReadDirectory($inventoryPath);
                $reader->setFile($type . "/" . $file1);
                $reader->setMaxProductCount($maxProductCount);

                $products = $reader->executeCreate();

                if (!is_array($products) and $products > $maxProductCount) {
                    touch($inventoryPath . $type . "/" . $serial .".big");
                    continue;
                }

                if (!$bigFiles) {
                    if (count($products) > 500) {
                        touch($inventoryPath . $type . "/" . $serial .".big");
                        continue;
                    }
                }

                $puller = new EntityPuller();
                $puller->setLogfilePath(dirname($this->directories->getLogfilePath()));
                $products = $puller->pullEntities($type, $serial, $products);

                try {
                    foreach (array_chunk($products, 250) as $chunkIndex => $chunk) {
                        $filename = $chunkIndex . "-" . $file1;
                        $this->serializeProducts($chunk, $serial, $filename);
                    }

                } catch (\Exception | \Throwable $e) {
                    echo "Serialize error in $serial file\n\t". $e->getMessage() ."\n";
                    return Command::FAILURE;
                }

                echo "$serial added.\n";

                $lockfile = $this->directories->getLocksPath() . $type . "/" . $serial . ".lock";

                if (!$this->directories->checkPath(dirname($lockfile))) {
                    $this->directories->createDirectory(dirname($lockfile));
                }

                if (!file_exists($lockfile)) {
                    touch($lockfile);
                }

                $processed++;

                if (!$moreThanOne) {
                    return Command::SUCCESS;
                }
            }
        }

        echo "Serials to adding in queue is not existed.\nProcessed count: $processed.\n";

        return Command::SUCCESS;
    }

    private function checkFiles($type, $serial, $forceFile)
    {
        if (file_exists($this->directories->getLocksPath() . "{$type}/{$serial}.skip")) {
            return true;
        }

        if (is_dir($this->directories->getSerializePath() . $serial)) {
            return true;
        }

        if (file_exists($this->directories->getLocksPath() ."{$type}/{$serial}.lock")) {
            if (!empty($forceFile) && $forceFile == $serial .".csv") {
                die("Найден файл ". $this->directories->getLocksPath() ."{$type}/{$serial}.lock по данной серии.");
            }

            return true;
        }

        if (preg_match('#raw|RAW#u', $type)) {
            return true;
        }

        if (preg_match('#raw|RAW#u', $serial)) {
            return true;
        }

        return false;
    }

    private function initialSettings()
    {
        /** @var $container - Контейнер приложения Symfony */
        $container = $this->getApplication()
            ->getKernel()
            ->getContainer();

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');
        $this->setManager($doctrineRegistry->getManager());

        $this->directories->setProductsPath($container->getParameter('products'));
    }

    private function optionalSettings(string $type, string $serial)
    {
        if (!empty($serial) and !empty($type)) {
            $this->remove($serial, $type);
        }
    }

    private function remove(string $serial, string $type): void
    {
        $entityManager = $this->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $entityManager->getRepository(Inventory::class);

        $inventoryRepository->removeBySerialType($serial, $type);
    }

    private function getFiles($path): array
    {
        $difference = ['.', '..', '.gitignore'];
        return array_diff(scandir($path), $difference);
    }

    private function serializeProducts($products, $serial, $filename): void
    {
        $serialSerializePath = $this->directories->getSerializePath() . "/$serial/";

        if (!is_dir($serialSerializePath)) {
            mkdir($serialSerializePath, recursive: true);
        }

        file_put_contents($serialSerializePath . $filename . ".serial", serialize($products));
    }

    private function getManager(): ObjectManager
    {
        return $this->manager;
    }
    private function setManager(ObjectManager $registry): void
    {
        $this->manager = $registry;
    }

    private function setMemoryLimit($memory) : void
    {
        ini_set('memory_limit', $memory);
    }
}
