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

#[AsCommand(name: 'PricePuller', description: 'Добавление основных цен на продукцию')]
class PricePullerCommand extends Command
{

    private Directory $directories;

    private ObjectManager $manager;

    protected function configure(): void
    {
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $executeScriptMemory = memory_get_usage();
        $executeScriptTime = time();

        $this->initialSettings();

        $pricePath = $this->directories->getPricePath();
        $files = $this->getFiles($pricePath);

        foreach ($files as $file) {
            $fileinfo = pathinfo($file);

            if (isset($fileinfo['extension'])) {
                if ($fileinfo['extension'] == "csv") {
                    $file = fopen($pricePath . $file, 'r');

                    if (flock($file, LOCK_EX | LOCK_NB, $would_block)) {
                        $manager = $this->getManager();
                        $inventoryRepository = $manager->getRepository(Inventory::class);

                        $rowPosition = 0;

                        while ($row = fgetcsv($file, separator: ';')) {
                            if ($rowPosition > 0) {
                                if (isset($row[0], $row[1], $row[2], $row[3])) {
                                    if (! empty($row[0]) and ! empty($row[3])) { // row[1] and row[2] can be zero
                                        $inventory = $inventoryRepository->findOneBy([
                                            'code' => $row[0],
                                            'serial' => $fileinfo['filename']
                                        ]);

                                        if (! is_null($inventory)) {
                                            $price = $inventory->getPrice();

                                            $price->setValue($row[1]);
                                            $price->setWarehouse($row[2]);
                                            $price->setCurrency($row[3]);

                                            $manager->persist($price);
                                        }
                                    }
                                }
                            }

                            $rowPosition ++;
                        }

                        try {
                            $manager->flush();
                            $manager->clear();
                        } catch (\Exception | \Throwable $exception) {
                            $customMessage = "\nFlush error in {$fileinfo['filename']}\n";

                            file_put_contents($this->directories->getLogfilePath(), "Symfony command: PricePuller\n" . $customMessage . $exception->getMessage() . "\n", FILE_APPEND);
                        }

                        touch($pricePath . $fileinfo['filename'] . ".lock");

                        fclose($file);

                        $this->createLogfileResult($executeScriptTime, $executeScriptMemory);
                        return Command::SUCCESS;
                    }

                    if ($would_block) {
                        echo "Другой процесс уже удерживает блокировку файла";
                    }

                    $date = date('d-m-Y H:i:s');

                    file_put_contents($this->directories->getLogfilePath(), "Symfony command: PricePuller in $date\n" . "Другой процесс уже удерживает блокировку файла \"{$fileinfo['basename']}\"", FILE_APPEND);
                }
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
            '.gitignore'
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

        file_put_contents($this->directories->getLogfilePath(), "Symfony command: PricePuller\n" . "Процесс завершён добавления цен на товары завершён\n" . "\tВремя начала: $startDate, Время завершения: $currentDate\n" . "\tИзначальное потребление памяти: $startMemory Мб, Возрастание к концу: $riseMemory\n" . "\tПик использования памяти: $peakMemory\n", FILE_APPEND);
    }
}
