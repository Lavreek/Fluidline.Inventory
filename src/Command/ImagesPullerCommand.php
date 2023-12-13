<?php
namespace App\Command;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ImagesPuller', description: 'Загрузка изображений для продукции')]
class ImagesPullerCommand extends Command
{

    private Directory $directories;

    private ObjectManager $manager;

    protected function configure(): void
    {
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '2048M');

        $executeScriptMemory = memory_get_usage();
        $executeScriptTime = time();

        $this->initialSettings();

        $imagesPath = $this->directories->getImagePath();

        $imagesFiles = $this->getFiles($imagesPath);

        $imageFilesProcessed = 0;

        foreach ($imagesFiles as $file) {
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
                            // echo "Serial {$fileinfo['filename']} is not isset\n";
                            fclose($file);
                            continue;
                        }

                        echo "Executing process in serial {$fileinfo['filename']}\n";

                        $rowPosition = 0;

                        while ($row = fgetcsv($file, separator: ';')) {
                            if ($rowPosition > 0) {
                                if (isset($row[0], $row[1], $row[2])) {
                                    if (! empty($row[0]) and ! empty($row[1] and ! empty($row[2]))) {
                                        $inventory = $inventoryRepository->findOneBy([
                                            'serial' => $fileinfo['filename'],
                                            'code' => $row[0]
                                        ]);

                                        if (!is_null($inventory)) {
                                            echo "Using code: ". $inventory->getCode() ."\n";
                                            $attachment = $inventory->getAttachments();
                                            $attachment->setImage($row[2]);

                                            $manager->persist($attachment);
                                        }
                                    }
                                }
                            }

                            $rowPosition ++;
                        }

                        $manager->flush();
                        $manager->clear();

                        fclose($file);

                        touch($imagesPath . $fileinfo['filename'] . ".lock");

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
            echo "File {$fileinfo['filename']} was loaded.";
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
