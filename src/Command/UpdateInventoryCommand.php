<?php

namespace App\Command;

use App\Entity\Inventory;
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
    description: 'Add a short description for your command',
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
        $serializePath = $container->getParameter('inventory_serialize_price_directory');

        /** @var string $cronLogPath | Путь к файлу логирования данной задачи */
        $cronLogPath = $container->getParameter('inventory_cron_execute');

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');

        $entityManager = $doctrineRegistry->getManager();



        $serializeChunks = array_diff(scandir($serializePath), ['..', '.', '.gitignore']);

        if (count($serializeChunks) > 0) {
            $chunk = array_shift($serializeChunks);
            $filename = $serializePath . $chunk;

            $f = fopen($filename, 'r');

            if (flock($f, LOCK_EX | LOCK_NB, $would_block)) {
                $serializeData = unserialize(stream_get_contents($f));

                for ($i = 0; $i < count($serializeData); $i++) {
                    $code = $entityManager->getRepository(Inventory::class)->findOneBy(['name' => $serializeData[$i]['code']]);

                    if (!$code) {
//                        $code =
                    }

                    dd($serializeData[$i]);

//                    $entityManager->persist($serializeData[$i]);
                }
//                $entityManager->flush();
//                $entityManager->clear();

                fclose($f);

                unlink($chunk);
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

    protected function executeBase(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
