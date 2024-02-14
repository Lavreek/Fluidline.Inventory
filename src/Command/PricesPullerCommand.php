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

#[AsCommand(name: 'PricesPuller', description: 'Добавление основных цен на продукцию')]
class PricesPullerCommand extends Command
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
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processed = 0;
        $this->initialSettings();

        $this->setMemoryLimit($input->getOption('memory-limit'));
        $forceFile = $input->getOption('file');
        $forceSerial = $input->getOption('file');
        $moreThanOne = $input->getOption('more-than-one');

        $locksFilepath = $this->directories->getLocksPath() . "prices/";
        if (!$this->directories->checkPath($locksFilepath)) {
            $this->directories->createDirectory($locksFilepath);
        }

        $pricesPath = $this->directories->getPricePath();
        $pricesFiles = $this->getFiles($pricesPath);

        foreach ($pricesFiles as $file) {
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

            $file = fopen($pricesPath . $file, 'r');

            if (flock($file, LOCK_EX | LOCK_NB, $would_block)) {
                $manager = $this->getManager();
                $inventoryRepository = $manager->getRepository(Inventory::class);

                $rowPosition = 0;

                while ($row = fgetcsv($file, separator: ';')) {
                    if ($rowPosition > 0) {
                        if (isset($row[0], $row[1], $row[2], $row[3])) {
                            if (!empty($row[0]) and !empty($row[3])) { // row[1] and row[2] can be zero
                                $inventory = $inventoryRepository->findOneBy([
                                    'code' => $row[0],
                                    'serial' => $fileinfo['filename']
                                ]);

                                if (!is_null($inventory)) {
                                    echo "Update: ". $inventory->getCode() ."\n";
                                    $price = $inventory->getPrice();

                                    $price->setValue($row[1]);
                                    $price->setWarehouse($row[2]);
                                    $price->setCurrency($row[3]);

                                    $manager->persist($inventory);

                                    $processed++;
                                }
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

            echo "PricesPuller processed $processed codes.\n";

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
        $difference = ['.', '..', '.gitignore'];
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
