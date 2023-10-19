<?php

namespace App\Command;

use App\Command\Helper\Directory;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'PricePuller',
    description: 'Добавление основных цен на продукцию',
)]
class PricePullerCommand extends Command
{
    private Directory $directories;

    private ObjectManager $manager;

    protected function configure() : void { }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $executeScriptMemory = memory_get_usage();

        return Command::SUCCESS;
    }

    private function initialSettings()
    {
        /** @var $container - Контейнер приложения Symfony */
        $container = $this->getApplication()->getKernel()->getContainer();

        /** @var Registry $doctrineRegistry */
        $doctrineRegistry = $container->get('doctrine');
        $this->setManager($doctrineRegistry->getManager());

        $this->directories->setProductsPath($container->getParameter('products'));
    }

    private function getFiles(string $path) : array
    {
        $difference = ['.', '..', '.gitignore'];
        return array_diff(scandir($path), $difference);
    }

    private function setManager(ObjectManager $manager) : void
    {
        $this->manager = $manager;
    }

    private function getManager() : ObjectManager
    {
        return $this->manager;
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
            "Symfony command: ImagesPuller\n".
            "Процесс завершён добавления изображений завершён\n".
            "\tВремя начала: $startDate, Время завершения: $currentDate\n".
            "\tИзначальное потребление памяти: $startMemory Мб, Возрастание к концу: $riseMemory\n".
            "\tПик использования памяти: $peakMemory\n",
            FILE_APPEND
        );
    }
}
