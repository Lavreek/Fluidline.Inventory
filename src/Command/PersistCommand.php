<?php
namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
use App\Entity\Inventory\InventoryAttachmenthouse;
use App\Entity\Inventory\InventoryPricehouse;
use App\Repository\Inventory\InventoryRepository;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'Persist', description: 'Добавление продукции в систему Inventory')]
class PersistCommand extends Command
{
    private Directory $directories;
    private ObjectManager $entityManager;
    private string $type;
    /**
     * @var array $images Объект, который корректирует сериализованные данные.
     * Работает относительно изображений продукции в сериализованных данных.
     */
    private array $images;

    /**
     * @var array $prices Объект, который корректирует сериализованные данные.
     * Работает относительно цен на продукцию в сериализованных данных
     */
    private array $prices;

    /**
     * @var array $models Объект, который корректирует сериализованные данные.
     * Работает относительно моделей продукции в сериализованных данных
     */
    private array $models;

    protected function configure(): void
    {
        $this->addOption('serial-folder', null, InputOption::VALUE_OPTIONAL,
            'Какая серийная директория должна быть обработана?', '');
        $this->addOption('serial', null, InputOption::VALUE_OPTIONAL,
            'Какая серия должна быть обработана?', '');
        $this->addOption('memory-limit', null, InputOption::VALUE_OPTIONAL,
            'Включить ограничение ресурсов? Значение в МБ', '-1');
        $this->addOption('more-than-one', null, InputOption::VALUE_OPTIONAL,
            'Включить продолжение цикла для обработки?', false);
        $this->addOption('skip-serials', null, InputOption::VALUE_OPTIONAL,
            'Какие серии должны быть пропущены?', '');
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initialSetup();

        $this->setMemoryLimit($input->getOption('memory-limit'));
        $forceSerialFolder = $input->getOption('serial-folder');
        $forceSerial = $input->getOption('serial');
        $moreThanOne = $input->getOption('more-than-one');
        $skipSerials = explode(',', $input->getOption('skip-serials'));

        $serializePath = $this->directories->getSerializePath();
        $serializeSerials = $this->getFiles($serializePath);

        $processedFilepath = $this->directories->getSerializePath() . 'processed.json';
        if (file_exists($processedFilepath)) {
            $processed = json_decode( file_get_contents($processedFilepath), true);
        }

        foreach ($serializeSerials as $serialFolder) {
            if (!empty($forceSerialFolder) && $serialFolder != $forceSerialFolder) {
                continue;
            }

            $serialFolderPath = $serializePath . $serialFolder;
            $serialFolderFiles = $this->getFiles($serialFolderPath);

            if (count($serialFolderFiles) === 0) {
                rmdir($serialFolderPath);
                continue;
            }

            foreach ($serialFolderFiles as $serialFile) {
                $filename = $serialFolderPath ."/". $serialFile;

                $serial = $serialFolder;

                preg_match('#([\w|\-|\s]*) \[.+\]#u', $serialFolder, $match);

                if (isset($match[1])) {
                    $serial = $match[1];
                    echo "Found parent serial: $serial.\n";
                }

                if (
                    (!empty($forceSerial) && $serial != $forceSerial) or
                    in_array($serial, $skipSerials)
                ) {
                    continue;
                }

                $f = fopen($filename, 'r');

                if (flock($f, LOCK_EX | LOCK_NB, $would_block)) {
                    echo "Using: $serialFolder directory.\n";

                    $entityManager = $this->getManager();

                    /** @var Inventory[] $serializeData */
                    $serializeData = unserialize(stream_get_contents($f));

                    $inventoryRepository = $entityManager->getRepository(Inventory::class);

                    if (isset($processed['filename'])) {
                        if ($processed['filename'] == $filename) {
                            $this->repair($inventoryRepository, $processedFilepath);
                        }
                    }

                    $this->persistSerializedData($serializeData, $serial, $serialFolder, $filename);
                    $this->persistAttachments($serializeData, $serial, $serialFolder, $filename);

                    fclose($f);

                    unlink($filename);

                    echo "File $filename unlinked.\n";

                    if (count($serialFolderFiles) == 1) {
                        rmdir($serialFolderPath);
                    }
                }

                if ($would_block) {
                    echo "Другой процесс уже удерживает блокировку файла";
                }

                if (!$moreThanOne) {
                    return Command::SUCCESS;
                }
            }
        }

        return Command::SUCCESS;
    }

    private function persistSerializedData($serializeData, $serial, $folder, $filename)
    {
        $manager = $this->getManager();

        $type = null;

        for ($i = 0; $i < count($serializeData); $i++) {
            if ($serializeData[$i]->getSerial() != $serial) {
                $serializeData[$i]->setSerial($serial);
            }

            if (is_null($type) and !is_null($serializeData[$i]->getType())) {
                $type = $serializeData[$i]->getType();
            }

            $manager->persist($serializeData[$i]);
        }

        echo "Type detected as \"$type\".\n";

        try {
            $manager->flush();
            echo "In $serial - Inventory and InventoryParamhouse added.\n";

            $manager->clear();

        } catch (\Exception | \Throwable $e) {
            echo "Flush error by $folder in \n\t$filename\n\n" . $e->getMessage() ."\n";
            die();
        }
    }

    private function persistAttachments($serializeData, $serial, $folder, $filename)
    {
        $manager = $this->getManager();

        $prices = $this->getVariable('prices');
        $images = $this->getVariable('images');
        $models = $this->getVariable('models');

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);


        for ($i = 0; $i < count($serializeData); $i ++) {
            /** @var Inventory $inventory */
            $inventory = $inventoryRepository->findOneBy([
                'code' => $serializeData[$i]->getCode()
            ]);

            if (!is_null($inventory)) {
                $this->fillParameters($prices, $serial);

                $pricehouse = $inventory->getPrice();

                if (is_null($pricehouse)) {
                    $pricehouse = new InventoryPricehouse();
                }

                $pricehouse->setValue(0);
                $pricehouse->setWarehouse(0);
                $pricehouse->setCode($inventory);
                $pricehouse->setCurrency('$');

                if ($prices['exist'] !== 2) {
                    $prices['csv_content'] .= implode(';', [
                        $inventory->getCode(),
                        $pricehouse->getValue(),
                        $pricehouse->getWarehouse(),
                        $pricehouse->getCurrency()
                    ]) . "\n";
                }

                $this->fillParameters($images, $serial);
                $this->fillParameters($models, $serial);

                $attachmenthouse = $inventory->getAttachments();

                if (is_null($attachmenthouse)) {
                    $attachmenthouse = new InventoryAttachmenthouse();
                }

                $attachmenthouse->setImage("/assets/inventory/default.png");

                if ($images['exist'] !== 2) {
                    $images['csv_content'] .= implode(';', [
                            $inventory->getCode(),
                            $inventory->getId(),
                            $attachmenthouse->getImage()
                        ]) . "\n";
                }

                $attachmenthouse->setModel("");

                if ($models['exist'] !== 2) {
                    $models['csv_content'] .= implode(';', [
                            $inventory->getCode(),
                            $inventory->getId(),
                            $attachmenthouse->getModel()
                        ]) . "\n";
                }

                $attachmenthouse->setCode($inventory);

                $manager->persist($pricehouse);
                $manager->persist($attachmenthouse);
            }
        }

        try {
            $manager->flush();

            echo "In $serial - InventoryAttachmenthouse added.\n";

            if ($prices['exist'] !== 2) {
                $this->writeToFile($prices['path'] . $serial . ".gen", $prices['csv_content']);
                echo "\tFile ". $prices['path'] . $serial . ".gen" ." - created.\n";
            }

            if ($images['exist'] !== 2) {
                $this->writeToFile($images['path'] . $serial . ".gen", $images['csv_content']);
                echo "\tFile ". $images['path'] . $serial . ".gen" ." - created.\n";
            }

            if ($models['exist'] !== 2) {
                $this->writeToFile($models['path'] . $serial . ".gen", $models['csv_content']);
                echo "\tFile ". $models['path'] . $serial . ".gen" ." - created.\n";
            }

            $manager->clear();

        } catch (\Exception | \Throwable $e) {
            echo "In $serial - InventoryAttachmenthouse: ".$e->getMessage() .
                "\nFlush error by $folder in \"attachments\" - $filename\n";

            file_put_contents(
                $this->directories->getSerializePath() . "processed.json",
                json_encode(['filename' => $filename])
            );
            die();
        }
    }

    private function repair($repository, $processedFilepath)
    {
        $repository->deleteEmpty();
        unlink($processedFilepath);
    }

    private function setVariable(string $variable, mixed $value)
    {
        $this->$variable = $value;
    }

    private function getVariable(string $variable)
    {
        return $this->$variable;
    }

    private function setManager(ObjectManager $manager): void
    {
        $this->entityManager = $manager;
    }

    private function getManager(): ObjectManager
    {
        return $this->entityManager;
    }

    private function writeToFile($path, $content): void
    {
        if (!$this->directories->checkPath(dirname($path))) {
            $this->directories->createDirectory(dirname($path));
        }

        if (!file_exists($path)) {
            touch($path);
        }

        $f = fopen($path, 'r+');
        fwrite($f, $content);
        fclose($f);
    }

    private function getFiles(string $path): ?array
    {
        $difference = ['..', '.', '.gitignore', 'processed.json'];
        return array_diff(scandir($path), $difference);
    }

    private function fillParameters(&$parameter, $serial)
    {
        if (empty($parameter['csv_content'])) {
            $this->startedFileExist($parameter['path'] . $serial, $parameter['exist'], $parameter['csv_content']);
        }

        if ($parameter['exist'] == 0) {
            touch($parameter['path'] . $serial . ".gen");
            $parameter['csv_content'] .= $parameter['csv_header'];
            $parameter['exist'] = 1;
        }
    }

    /**
     * Первоначальная настройка переменных, которые участвуют в процессе добавления продукции
     *
     * @return void
     */
    private function initialSetup(): void
    {
        $container = $this->getApplication()
            ->getKernel()
            ->getContainer();

        $this->directories->setProductsPath($container->getParameter('products'));

        $doctrine = $container->get('doctrine');
        $this->setManager($doctrine->getManager());

        // Создание объекта, который определяет назначения для сущности InventoryPricehouse
        $this->setVariable('prices', [
            'path' => $this->directories->getPricePath(),
            'csv_header' => "code;value;count;currency\n",
            'csv_content' => "",
            'exist' => 0 // 0 - Отсутствует, 1 - Сгенерированные, 2 - Существует оригинал
        ]);

        // Создание объекта, который определяет назначения для сущности InventoryAttachmethouse (Image)
        $this->setVariable('images', [
            'path' => $this->directories->getImagePath(),
            'csv_header' => "code;code_id;image_path\n",
            'csv_content' => "",
            'exist' => 0 // 0 - Отсутствует, 1 - Сгенерированные, 2 - Существует оригинал
        ]);

        // Создание объекта, который определяет назначения для сущности InventoryAttachmethouse (Model)
        $this->setVariable('models', [
            'path' => $this->directories->getModelPath(),
            'csv_header' => "code;code_id;model_path\n",
            'csv_content' => "",
            'exist' => 0 // 0 - Отсутствует, 1 - Сгенерированные, 2 - Существует оригинал
        ]);
    }

    private function startedFileExist($path, &$exist, &$content): void
    {
        $genPath = $path . ".gen";

        if (file_exists($path . ".csv")) {
            $exist = 2;
            if (file_exists($genPath)) {
                unlink($genPath);
            }
        } elseif (file_exists($genPath)) {
            $exist = 1;
            $content = file_get_contents($genPath);
        }
    }

    private function setMemoryLimit($memory) : void
    {
        ini_set('memory_limit', $memory);
    }
}
