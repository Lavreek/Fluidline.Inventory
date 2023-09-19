<?php

namespace App\Command;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'updateInventory',
    description: 'Обновление цены продукции',
)]

class UpdateInventoryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        ini_set('memory_limit', '1024M');

        $memoryUsage = memory_get_usage();

        /** @var App_KernelDevDebugContainer $container | Контейнер приложения Symfony */
        $container = $this->getApplication()->getKernel()->getContainer();

        /** @var string $serializePath | Путь к сериализованным файлам */
        $serializePath = $container->getParameter('inventory_serialize_update_directory');

        /** @var string $cronLogPath | Путь к файлу логирования данной задачи */
        $cronLogPath = $container->getParameter('inventory_cron_execute');

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');

        $entityManager = $doctrineRegistry->getManager();

        $serializeChunks = array_diff(scandir($serializePath), ['..', '.', '.gitignore']);

        if (count($serializeChunks) > 0) {
            $serial = array_shift($serializeChunks);

            $chunks = array_diff(
                scandir($serializePath . $serial), ['..', '.', '.gitignore']
            );

            $chunksCount = count($chunks);
            $chunk = array_diff($chunks);

            $filename = $serializePath . $serial ."/". array_shift($chunk);

            $fileContent = file_get_contents($filename);
            $executedContent = "";

            $f = fopen($filename, 'r');

            if (flock($f, LOCK_EX | LOCK_NB, $would_block)) {
                $row = 0;

                $commandColumn =
                $commandColumnKey =
                $attachmentColumn = null;

                /** @var InventoryRepository $inventoryRepository */
                $inventoryRepository = $entityManager->getRepository(Inventory::class);

                while ($data = fgetcsv($f, separator: ';')) {
                    if ($row === 0) {
                        if (in_array('code_id', $data) and is_null($commandColumn)) {
                            $commandColumn = array_search('code_id', $data);
                            $commandColumnKey = 'id';
                        }

                        if (in_array('code', $data) and is_null($commandColumn)) {
                            $commandColumn = array_search('code', $data);
                            $commandColumnKey = 'code';
                        }

                        $attachmentColumn = $data[1];

                        if (in_array($attachmentColumn, ['value', 'currency', 'warehouse']) ) {
                            unset($data[0]);

                            $attachmentColumn = $data;
                        }
                    } else {
                        $executedContent .= implode(';', $data) . "\n";
                        $inventory = $inventoryRepository->findOneBy([$commandColumnKey => $data[$commandColumn]]);

                        if (is_string($attachmentColumn)) {
                            $attachment = $inventory->getAttachments();

                            switch ($attachmentColumn) {
                                case 'image_path' : {
                                    $attachment->setImage($data[1]);
                                    break;
                                }

                                case 'modal_path' : {
                                    $attachment->setModel($data[1]);
                                    $entityManager->persist($attachment);
                                    break;
                                }
                            }

                            $entityManager->persist($attachment);
                        }

                        if (is_array($attachmentColumn)) {
                            $price = $inventory->getPrice();

                            $price_value = array_search('price_value', $attachmentColumn);
                            if ($price_value !== false) {
                                $price->setValue($data[$price_value]);
                            }

                            $price_currency = array_search('price_currency', $attachmentColumn);
                            if ($price_currency !== false) {
                                $price->setValue($data[$price_currency]);
                            }

                            $price_warehouse = array_search('price_warehouse', $attachmentColumn);
                            if ($price_warehouse !== false) {
                                $price->setValue($data[$price_warehouse]);
                            }

                            $entityManager->persist($price);
                        }
                    }

                    $row++;

                    if ($row > 1000) {
                        break;
                    }
                }

                $entityManager->flush();
                $entityManager->clear();

                fclose($f);

                file_put_contents($filename, str_replace($executedContent, '', $fileContent));

                if ($row === 1) {
                    unlink($filename);

                    if ($chunksCount === 1) {
                        rmdir(dirname($filename));
                    }
                }
            }

            if ($would_block) {
                echo "Другой процесс уже удерживает блокировку файла";
            }

            file_put_contents(
                $cronLogPath,
                "\n ". date('d-m-Y H:i:s') .
                "\n Update: ".
                "\n\tStart with : $memoryUsage. Rise in: ". memory_get_usage() - $memoryUsage .
                ". Memory peak: ". memory_get_peak_usage() .".\n",
                FILE_APPEND
            );
        }

        return Command::SUCCESS;
    }
}