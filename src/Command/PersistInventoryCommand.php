<?php

namespace App\Command;

use App_KernelDevDebugContainer;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'persistInventory',
    description: 'Add a short description for your command',
)]

class PersistInventoryCommand extends Command
{
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1024M');

        $memoryUsage = memory_get_usage();

        /** @var App_KernelDevDebugContainer $container | Контейнер приложения Symfony */
        $container = $this->getApplication()->getKernel()->getContainer();

        /** @var string $serializePath | Путь к сериализованным файлам */
        $serializePath = $container->getParameter('inventory_serialize_directory');

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');

        $entityManager = $doctrineRegistry->getManager();

        $serializeSerials = array_diff(scandir($serializePath), ['..', '.', '.gitignore']);

        if (count($serializeSerials) > 0) {
            $serializeSerialsPath = $serializePath . array_shift($serializeSerials) . "/";
            $filesInThere = array_diff(scandir($serializeSerialsPath), ['..', '.']);
            $filename = $serializeSerialsPath . array_shift($filesInThere);

            $f = fopen($filename, 'r');

            if (flock($f, LOCK_EX | LOCK_NB, $would_block)) {
                $serializeData = unserialize(stream_get_contents($f));

                for ($i = 0; $i < count($serializeData); $i++) {
                    $entityManager->persist($serializeData[$i]);
                }
                $entityManager->flush();
                $entityManager->clear();

                fclose($f);

                unlink($filename);

                if (count($filesInThere) < 1) {
                    rmdir($serializeSerialsPath);
                }
            }

            if ($would_block) {
                echo "Другой процесс уже удерживает блокировку файла";
            }
        }

        file_put_contents(
            __DIR__ . "/log.log",
            "Start with : $memoryUsage. Rise in: ". memory_get_usage() - $memoryUsage .
            ". Memory peak: ". memory_get_peak_usage() ."."
        );

        return Command::SUCCESS;
    }

    private function executeBase(InputInterface $input, OutputInterface $output): int
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
