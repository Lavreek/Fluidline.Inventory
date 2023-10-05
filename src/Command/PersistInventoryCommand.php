<?php

namespace App\Command;

use App\Entity\Inventory;
use App\Entity\InventoryAttachmenthouse;
use App\Entity\InventoryPricehouse;
use App\Repository\InventoryRepository;
use ContainerPx3PnUb\App_KernelDevDebugContainer;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'persistInventory',
    description: 'Добавление продукции в систему Inventory',
)]

class PersistInventoryCommand extends Command
{
    private mixed $container;

    /**
     * @var string $serialize Путь к файлам сериализованным после генерации продукции из файла.
     */
    private string $serialize;

    /**
     * @var string $cron Путь к файлу логирования потребляемой памяти.
     * По идее скрипт должен вызываться с задачи crontab.
     */
    private string $cron;

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

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    private function createContainer($container)
    {
        $this->container = $container;
    }

    private function getContainer() : App_KernelDevDebugContainer
    {
        return $this->container;
    }

    private function createPath(string $variable, string $parameterName)
    {
        $container = $this->getContainer();

        /** Адаптивное присвоение переменным "путь" к директориям */
        $this->$variable = $container->getParameter($parameterName);
    }

    private function getPath(string $variable)
    {
        return $this->$variable;
    }

    private function setVariable(string $variable, mixed $value)
    {
        $this->$variable = $value;
    }

    private function getVariable(string $variable)
    {
        return $this->$variable;
    }

    private function getContainerPath(string $path) : string
    {
        return $this->getContainer()->getParameter($path);
    }

    private function setManager(ObjectManager $manager)
    {
        $this->entityManager = $manager;
    }

    private function getManager() : ObjectManager
    {
        return $this->entityManager;
    }

    /**
     * Первоначальная настройка переменных, которые участвуют в процессе добавления продукции
     *
     * @return void
     */
    private function initialSetup() : void
    {
        // Создание рабочего контейнера приложения Symfony для использования функций внутренней обработки
        $this->createContainer($this->getApplication()->getKernel()->getContainer());

        // Создание пути переменной $serialize
        $this->createPath('serialize', 'serialize');

        // Создание пути переменной $cron
        $this->createPath('cron', 'cron');

        // Создание объекта, который определяет назначения для сущности InventoryPricehouse
        $this->setVariable('prices', [
            'path' => $this->getContainerPath('products') . "prices/",
            'csv_header' => "code;value;count;currency\n",
            'csv_content' => "",
        ]);

        // Создание объекта, который определяет назначения для сущности InventoryAttachmethouse (Image)
        $this->setVariable('images', [
            'path' => $this->getContainerPath('products') . "images/",
            'csv_header' => "code;code_id;image_path\n",
            'csv_content' => "",
        ]);

        // Создание объекта, который определяет назначения для сущности InventoryAttachmethouse (Model)
        $this->setVariable('models', [
            'path' => $this->getContainerPath('products') . "models/",
            'csv_header' => "code;code_id;model_path\n",
            'csv_content' => "",
        ]);
    }

    private function initialEntityManager()
    {
        $container = $this->getContainer();

        /** @var Registry $doctrine Объект doctrine, связь с сущностями базы данных */
        $doctrine = $container->get('doctrine');

        $this->entityManager = $doctrine->getManager();
    }

    private function writeToFile($path, $content) : void
    {
        $writed = false;

        while (!$writed) {
            $writed = file_put_contents($path, $content, FILE_APPEND);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', '1024M');

        $memoryUsage = memory_get_usage();

        $this->initialSetup();
        $this->initialEntityManager();

        $serializePath = $this->getPath('serialize');

        $entityManager = $this->getManager();

        $serializeSerials = array_diff(scandir($serializePath), ['..', '.', '.gitignore']);

        if (count($serializeSerials) > 0) {
            $serialPathCut = array_shift($serializeSerials);

            $serializeSerialsPath = $serializePath . $serialPathCut ."/";
            $filesInThere = array_diff(scandir($serializeSerialsPath), ['..', '.']);
            $filename = $serializeSerialsPath . array_shift($filesInThere);

            $f = fopen($filename, 'r');

            echo "\nUsing: $serialPathCut directory.";

            if (flock($f, LOCK_EX | LOCK_NB, $would_block)) {
                preg_match_all('#(\w+) \[.+\]#u', $serialPathCut, $match);

                if (isset($match[1][0])) {
                    $serial = $match[1][0];
                    echo "\nFound parent serial: $serial";
                } else {
                    $serial = $serialPathCut;
                }

                /** @var InventoryRepository $inventory */
                $inventory = $entityManager->getRepository(Inventory::class);

                /** @var Inventory[] $serializeData */
                $serializeData = unserialize(stream_get_contents($f));

                $type = "";

                for ($i = 0; $i < count($serializeData); $i++) {
                    if (empty($type)) {
                        $type = $serializeData[$i]->getType();
                    }

                    $serializeData[$i]->setSerial($serial);

                    $entityManager->persist($serializeData[$i]);
                }

                try {
                    $entityManager->flush();
                    echo "\n In $serial entities added. \n";

                    $entityManager->clear();

                } catch (\Exception | \Throwable $exception) {
                    echo "\n Throw exception in $serial \n\t Exception message: {$exception->getMessage()}\n\t By file $filename.\n";

                    $inventory->removeBySerialType($serial, $type);

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
                            touch($prices['path'] . $serial . ".csv");
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
                            touch($images['path'] . $serial . ".csv");
                            $images['csv_content'] .= $images['csv_header'];
                        }

                        if (!file_exists($models['path'] . $serial . ".csv")) {
                            touch($models['path'] . $serial . ".csv");
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

                    $this->writeToFile($prices['path'] . $serial . ".csv", $prices['csv_content']);
                    $this->writeToFile($images['path'] . $serial . ".csv", $images['csv_content']);
                    $this->writeToFile($models['path'] . $serial . ".csv", $models['csv_content']);

                    $entityManager->clear();
                } catch (\Exception | \Throwable) {
                    fclose($f);

                    return Command::FAILURE;
                }

                fclose($f);

                unlink($filename);

                if (count($filesInThere) < 1) {
                    rmdir($serializeSerialsPath);
                }

                file_put_contents(
                    $this->getPath('cron'),
                    "\n ". date('d-m-Y H:i:s') .
                    "\n Serial: $serial".
                    "\n\tStart with : $memoryUsage. Rise in: ". memory_get_usage() - $memoryUsage .
                    ". Memory peak: ". memory_get_peak_usage() .".\n",
                    FILE_APPEND
                );
            }

            if ($would_block) {
                echo "Другой процесс уже удерживает блокировку файла";
            }

            file_put_contents(
                $this->getPath('cron'),
                "\n". date('d-m-Y H:i:s') .
                "\nДругой процесс уже удерживает блокировку файла".
                "\n\tStart with : $memoryUsage. Rise in: ". memory_get_usage() - $memoryUsage .
                ". Memory peak: ". memory_get_peak_usage() .".\n",
                FILE_APPEND
            );
        }

        return Command::SUCCESS;
    }
}
