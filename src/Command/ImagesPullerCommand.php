<?php
namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
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
        $this->addOption('file', null, InputOption::VALUE_OPTIONAL, 'Which file could be serialized?', '');
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2048M');

        $forceFile = $input->getOption('file');

        $executeScriptMemory = memory_get_usage();
        $executeScriptTime = time();

        $this->initialSettings();

        $imagesPath = $this->directories->getImagePath();

        $imagesFiles = $this->getFiles($imagesPath);

        $imageFilesProcessed = 0;

        foreach ($imagesFiles as $file) {
            if (!empty($forceFile)) {
                if ($file != $forceFile) {
                    continue;
                }
            }

            $fileinfo = pathinfo($file);

            if (isset($fileinfo['extension'])) {
                if ($fileinfo['extension'] == 'csv') {
                    if (in_array($fileinfo['filename'] . ".lock", $imagesFiles)) {
                        continue;
                    }

                    echo "Using file: {$fileinfo['filename']}\n";

                    $imageFilesProcessed ++;

                    $file = fopen($imagesPath . $file, 'r');

                    if (flock($file, LOCK_EX | LOCK_NB, $would_block)) {
                        $manager = $this->getManager();

                        $inventoryRepository = $manager->getRepository(Inventory::class);

                        $inventory = $inventoryRepository->findOneBy([
                            'serial' => $fileinfo['filename']
                        ]);

                        if (is_null($inventory)) {
                            fclose($file);
                            continue;
                        }

                        echo "Executing process in serial {$fileinfo['filename']}\n";

                        $rowPosition = $changed = 0;

                        $header = [];

                        while ($row = fgetcsv($file, separator: ';')) {
                            if ($rowPosition == 0) {
                                $header = $row;

                            } else {
                                if (in_array('code', $header)) {
                                    $position = array_search('code', $header);

                                    $inventory = $inventoryRepository->findOneBy([
                                        'serial' => $fileinfo['filename'],
                                        'code' => $row[$position]
                                    ]);
                                } elseif (in_array('code_id', $header)) {
                                    $position = array_search('code_id', $header);

                                    $inventory = $inventoryRepository->findOneBy([
                                        'serial' => $fileinfo['filename'],
                                        'code_id' => $row[$position]
                                    ]);
                                } else {
                                    break;
                                }

                                if (!is_null($inventory)) {
                                    if (in_array('image_path', $header)) {
                                        $position = array_search('image_path', $header);

                                        echo "\nUsing code: ". $inventory->getCode();
                                        $attachment = $inventory->getAttachments();

                                        echo "\nSetting image: ". $row[$position];
                                        $attachment->setImage($row[$position]);

                                        $manager->persist($attachment);

                                        $changed++;
                                    }
                                }
                            }

                            $rowPosition ++;
                        }

                        $manager->flush();
                        $manager->clear();

                        fclose($file);

                        if ($changed > 0) {
                            touch($imagesPath . $fileinfo['filename'] . ".lock");
                        }

                        break;
                    }

                    if ($would_block) {
                        echo "Другой процесс уже удерживает блокировку файла";
                    }

                    $date = date('d-m-Y H:i:s');

                    file_put_contents($this->directories->getLogfilePath(), "Symfony command: ImagesPuller in $date\n" . "Другой процесс уже удерживает блокировку файла \"{$fileinfo['basename']}\"", FILE_APPEND);
                }
            }
        }

        if ($imageFilesProcessed > 0) {
            echo "\nFile {$fileinfo['filename']} was loaded.";
        }

        $this->createLogfileResult($executeScriptTime, $executeScriptMemory);

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

    private function createLogfileResult(int $start, int $memory): void
    {
        $currentDate = date('d-m-Y H:i:s');
        $startDate = date('d-m-Y H:i:s', $start);

        $startMemory = ($memory / 1024) / 1024;
        $currentMemory = (memory_get_usage() / 1024) / 1024;
        $riseMemory = $currentMemory - $startMemory;
        $peakMemory = memory_get_peak_usage();

        file_put_contents($this->directories->getLogfilePath(), "Symfony command: ImagesPuller\n" . "Процесс завершён добавления изображений завершён\n" . "\tВремя начала: $startDate, Время завершения: $currentDate\n" . "\tИзначальное потребление памяти: $startMemory Мб, Возрастание к концу: $riseMemory\n" . "\tПик использования памяти: $peakMemory\n", FILE_APPEND);
    }
}
