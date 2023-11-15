<?php

namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
use App\Entity\Inventory\InventoryAttachmenthouse;
use App\Entity\Inventory\InventoryPricehouse;
use App\Repository\InventoryRepository;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'Persist',
    description: 'Добавление продукции в систему Inventory',
)]

class PersistCommand extends Command
{
    const max_memory_limit = '1024M';

    private Directory $directories;

    /** @var array $images Объект, который корректирует сериализованные данные.
     * Работает относительно изображений продукции в сериализованных данных
     */
    private array $images;

    /** @var array $prices Объект, который корректирует сериализованные данные.
     * Работает относительно цен на продукцию в сериализованных данных
     */
    private array $prices;

    /** @var array $models Объект, который корректирует сериализованные данные.
     * Работает относительно моделей продукции в сериализованных данных
     */
    private array $models;

    private ObjectManager $entityManager;

    protected function configure(): void
    {
        $this->directories = new Directory();
    }

    private function setVariable(string $variable, mixed $value)
    {
        $this->$variable = $value;
    }

    private function getVariable(string $variable)
    {
        return $this->$variable;
    }

    private function setManager(ObjectManager $manager) : void
    {
        $this->entityManager = $manager;
    }

    private function getManager() : ObjectManager
    {
        return $this->entityManager;
    }

    private function writeToFile($path, $content) : void
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

    private function getFiles(string $path) : array
    {
        $difference = ['..', '.', '.gitignore'];
        return array_diff(scandir($path), $difference);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', self::max_memory_limit);
        $executeScriptMemory = memory_get_usage();
        $executeScriptTime = time();

        $this->initialSetup();

        $serializePath = $this->directories->getSerializePath();

        $serializeSerials = $this->getFiles($serializePath);

        if (count($serializeSerials) > 0) {
            $serialFolder = array_shift($serializeSerials);

            $serializeSerialsPath = $serializePath . $serialFolder ."/";

            $serialFiles = $this->getFiles($serializeSerialsPath);

            $filename = $serializeSerialsPath . array_shift($serialFiles);

            $f = fopen($filename, 'r');

            if (flock($f, LOCK_EX | LOCK_NB, $would_block)) {
                echo "\nUsing: $serialFolder directory.";

                preg_match_all('#(\w+) \[.+\]#u', $serialFolder, $match);

                if (isset($match[1][0])) {
                    $serial = $match[1][0];
                    echo "\nFound parent serial: $serial";

                } else {
                    $serial = $serialFolder;
                }

                $entityManager = $this->getManager();

                /** @var InventoryRepository $inventory */
                $inventory = $entityManager->getRepository(Inventory::class);

                /** @var Inventory[] $serializeData */
                $serializeData = unserialize(stream_get_contents($f));

                for ($i = 0; $i < count($serializeData); $i++) {
                    if ($serializeData[$i]->getSerial() != $serial) {
                        $serializeData[$i]->setSerial($serial);
                    }

                    $entityManager->persist($serializeData[$i]);
                }

                try {
                    $entityManager->flush();
                    echo "\n In $serial entities added. \n";

                    $entityManager->clear();

                } catch (\Exception | \Throwable $exception) {
                    if (isset($type)) {
                        $inventory->removeBySerialType($serial, $serializeData[$i]->getType());
                    }

                    $customMessage = "\nFlush error by $serialFolder in $filename\n";

                    file_put_contents(
                        $this->directories->getLogfilePath(),
                        "Symfony command: Persist\n".
                        $customMessage . $exception->getMessage() ."\n",
                        FILE_APPEND
                    );

                    echo $customMessage;

                    fclose($f);

                    return Command::FAILURE;
                }

                $prices = $this->getVariable('prices');
                $images = $this->getVariable('images');
                $models = $this->getVariable('models');

                for ($i = 0; $i < count($serializeData); $i++) {
                    /** @var Inventory $code */
                    $code = $inventory->findOneBy(['code' => $serializeData[$i]->getCode()]);

                    if (!is_null($code)) {
                        if (!file_exists($prices['path'] . $serial . ".csv")) {
                            touch($prices['path'] . $serial . ".gen");
                            $prices['csv_content'] .= $prices['csv_header'];
                        }

                        $pricehouse = new InventoryPricehouse();
                        $pricehouse->setValue(0);
                        $pricehouse->setWarehouse(0);
                        $pricehouse->setCode($code);
                        $pricehouse->setCurrency('$');

                        $prices['csv_content'] .= implode(';', [
                            $code->getCode(), $pricehouse->getValue(), $pricehouse->getWarehouse(), $pricehouse->getCurrency()
                        ]) ."\n";

                        if (!file_exists($images['path'] . $serial . ".csv")) {
                            touch($images['path'] . $serial . ".gen");
                            $images['csv_content'] .= $images['csv_header'];
                        }

                        if (!file_exists($models['path'] . $serial . ".csv")) {
                            touch($models['path'] . $serial . ".gen");
                            $models['csv_content'] .= $models['csv_header'];
                        }

                        $attachmenthouse = new InventoryAttachmenthouse();
                        $attachmenthouse->setImage("/assets/reborn/inventory/default.png");
                        $images['csv_content'] .= implode(';', [
                            $code->getCode(), $code->getId(), $attachmenthouse->getImage()
                        ]) ."\n";

                        $attachmenthouse->setModel("");
                        $models['csv_content'] .= implode(';', [
                            $code->getCode(), $code->getId(), $attachmenthouse->getModel()
                        ]) ."\n";

                        $attachmenthouse->setCode($code);

                        $entityManager->persist($pricehouse);
                        $entityManager->persist($attachmenthouse);
                    }
                }

                try {
                    $entityManager->flush();
                    echo "\nIn $serial - attachments \n\t File $filename added.\n";

                    $this->writeToFile($prices['path'] . $serial . ".gen", $prices['csv_content']);
                    $this->writeToFile($images['path'] . $serial . ".gen", $images['csv_content']);
                    $this->writeToFile($models['path'] . $serial . ".gen", $models['csv_content']);

                    $entityManager->clear();
                } catch (\Exception | \Throwable $exception) {
                    fclose($f);

                    $customMessage = "\nFlush error by $serialFolder in $filename\n";

                    file_put_contents(
                        $this->directories->getLogfilePath(),
                        "Symfony command: Persist\n".
                        $customMessage . $exception->getMessage() ."\n",
                        FILE_APPEND
                    );

                    return Command::FAILURE;
                }

                fclose($f);

                unlink($filename);

                if (count($serialFiles) < 1) {
                    rmdir($serializeSerialsPath);
                }

                $this->createLogfileResult($executeScriptTime, $executeScriptMemory);
            }

            if ($would_block) {
                echo "Другой процесс уже удерживает блокировку файла";
            }

            file_put_contents(
                $this->directories->getLogfilePath(),
                "Symfony command: Persist\n".
                "Другой процесс уже удерживает блокировку файла\n".
                FILE_APPEND
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Первоначальная настройка переменных, которые участвуют в процессе добавления продукции
     * @return void
     */
    private function initialSetup() : void
    {
        $container = $this->getApplication()->getKernel()->getContainer();

        $this->directories->setProductsPath($container->getParameter('products'));

        $doctrine = $container->get('doctrine');
        $this->setManager($doctrine->getManager());

        // Создание объекта, который определяет назначения для сущности InventoryPricehouse
        $this->setVariable('prices', [
            'path' => $this->directories->getPricePath(),
            'csv_header' => "code;value;count;currency\n",
            'csv_content' => "",
        ]);

        // Создание объекта, который определяет назначения для сущности InventoryAttachmethouse (Image)
        $this->setVariable('images', [
            'path' => $this->directories->getImagePath(),
            'csv_header' => "code;code_id;image_path\n",
            'csv_content' => "",
        ]);

        // Создание объекта, который определяет назначения для сущности InventoryAttachmethouse (Model)
        $this->setVariable('models', [
            'path' => $this->directories->getModelPath(),
            'csv_header' => "code;code_id;model_path\n",
            'csv_content' => "",
        ]);
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
            "Symfony command: Persist\n".
            "Процесс добавления продукции завершён\n".
            "\tВремя начала: $startDate, Время завершения: $currentDate\n".
            "\tИзначальное потребление памяти: $startMemory Мб, Возрастание к концу: $riseMemory\n".
            "\tПик использования памяти: $peakMemory\n",
            FILE_APPEND
        );
    }
}