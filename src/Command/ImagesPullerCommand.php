<?php
namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
use App\Entity\Inventory\InventoryAttachmenthouse;
use App\Service\FileHelper;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ImagesPuller', description: 'Загрузка изображений для продукции')]
class ImagesPullerCommand extends Command
{
    private Directory $directories;
    private ObjectManager $manager;

    protected function configure(): void
    {
        $this->addOption('file', null, InputOption::VALUE_OPTIONAL,
            'Какой файл должен быть обработан?', '');
        $this->addOption('serial', null, InputOption::VALUE_OPTIONAL,
            'Какая серия должна быть обработана?', '');
        $this->addOption('more-than-one', null, InputOption::VALUE_OPTIONAL,
            'Включить продолжение цикла для обработки?', false);
        $this->addOption('memory-limit', null, InputOption::VALUE_OPTIONAL,
            'Включить ограничение ресурсов? Значение в МБ', '-1');
        $this->addOption('isset-break', null, InputOption::VALUE_OPTIONAL,
            'Отключить, если серия не найдена?', false);
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processed = 0;
        $this->initialSettings();

        $this->setMemoryLimit($input->getOption('memory-limit'));
        $forceFile = $input->getOption('file');
        $forceSerial = $input->getOption('serial');
        $moreThanOne = $input->getOption('more-than-one');
        $issetBreak = $input->getOption('isset-break');

        $locksFilepath = $this->directories->getLocksPath() . "images/";
        if (!$this->directories->checkPath($locksFilepath)) {
            $this->directories->createDirectory($locksFilepath);
        }

        $imagesPath = $this->directories->getImagePath();
        $imagesFiles = $this->getFiles($imagesPath);

        foreach ($imagesFiles as $file) {
            if (!empty($forceFile) && $file != $forceFile) {
                continue;
            }

            $fileinfo = pathinfo($file);
            $serial = $fileinfo['filename'];

            if (!empty($forceSerial) && $serial != $forceSerial) {
                continue;
            }

            $lockFile = $locksFilepath . $serial .".lock";

            if (isset($fileinfo['extension'])) {
                if (file_exists($lockFile) or
                    $fileinfo['extension'] == "gen"
                ) {
                    continue;
                }

            } else {
                continue;
            }

            echo "ImagesPuller Serial: $serial.\n";

            $file = fopen($imagesPath . $file, 'r');

            if (flock($file, LOCK_EX | LOCK_NB, $would_block)) {
                $manager = $this->getManager();

                $inventoryRepository = $manager->getRepository(Inventory::class);
                $inventory = $inventoryRepository->findOneBy(['serial' => $serial]);

                if (is_null($inventory)) {
                    fclose($file);
                    continue;
                }

                echo "Executing process in serial {$fileinfo['filename']}\n";

                $rowPosition = 0;

                $header = [];

                $helper = new FileHelper();
                $delimiter = $helper->getFileDelimiter($file);

                while ($row = fgetcsv($file, separator: $delimiter)) {
                    if ($rowPosition == 0) {
                        $header = $row;

                    } else {
                        if (in_array('code', $header)) {
                            $position = array_search('code', $header);

                            $inventory = $inventoryRepository->findOneBy([
                                'serial' => $serial,
                                'code' => $row[$position]
                            ]);
                        } elseif (in_array('code_id', $header)) {
                            $position = array_search('code_id', $header);

                            $inventory = $inventoryRepository->findOneBy([
                                'serial' => $serial,
                                'code_id' => $row[$position]
                            ]);
                        } else {
                            break;
                        }

                        if (!is_null($inventory)) {
                            if (in_array('image_path', $header)) {
                                $position = array_search('image_path', $header);

                                if (!is_bool($position)) {
                                    // echo "Using code: ". $inventory->getCode() ."\n";

                                    /** @var InventoryAttachmenthouse $attachment */
                                    $attachment = $inventory->getAttachments();

                                    if ($row[$position] !== "/assets/inventory/default.png") {
                                        $attachment->setImage($row[$position]);

                                        $manager->persist($attachment);

                                        // echo "Setting image: ". $row[$position] ."\n";

                                        $processed++;
                                    }
                                }
                            }
                        } else {
                            if ($rowPosition === 1 && $issetBreak) {
                                echo "Probably $serial serial is not isset";
                                return Command::FAILURE;
                            }
                        }
                    }

                    $rowPosition ++;
                }

                $manager->flush();
                $manager->clear();

                touch($locksFilepath . $serial . ".lock");

                fclose($file);
            }

            if ($would_block) {
                echo "Другой процесс уже удерживает блокировку файла";
            }

            echo "ImagesPuller processed $processed codes.\n";

            if (!$moreThanOne) {
                return Command::SUCCESS;
            }
        }

        return Command::SUCCESS;
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

    private function getFiles(string $path): array
    {
        $difference = [
            '.',
            '..',
            '.gitignore',
            'example.csv'
        ];
        return array_diff(scandir($path), $difference);
    }

    private function setManager(ObjectManager $manager): void
    {
        $this->manager = $manager;
    }

    private function getManager(): ObjectManager
    {
        return $this->manager;
    }

    private function setMemoryLimit($memory) : void
    {
        ini_set('memory_limit', $memory);
    }
}
