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

#[AsCommand(
    name: 'Remove',
    description: 'Add a short description for your command',
)]
class RemoveCommand extends Command
{
    private Directory $directories;
    private ObjectManager $manager;

    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED,
            'Какой тип должен быть удалён?', '');
        $this->addOption('serial', null, InputOption::VALUE_REQUIRED,
            'Какая серия должна быть удалена?', '');
        $this->directories = new Directory();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initialSettings();

        $type = $input->getOption('type');
        $serial = $input->getOption('serial');

        if (empty($type) or empty($serial)) {
            echo "Type or Serial cannot be empty.\n";
            return Command::FAILURE;
        }

        echo "Deleting Serial: $serial by Type: $type is started.\n";

        $bigSerialsPath = $this->directories->getBigsPath();
        $locksSerialsPath = $this->directories->getLocksPath();
        $serializedSerialsPath = $this->directories->getSerializePath();

        $bigFilepath = $bigSerialsPath . $type ."/". $serial .".big";
        $locksFilepath = $locksSerialsPath . $type ."/". $serial .".lock";
        $imagesLocksFilepath = $locksSerialsPath . "images/". $serial .".lock";
        $pricesLocksFilepath = $locksSerialsPath . "prices/". $serial .".lock";
        $serializeFilepath = $serializedSerialsPath . $serial . "/";

        $inventoryRepository = $this->getManager()->getRepository(Inventory::class);
        $inventoryRepository->removeSerialByType($serial, $type);

        $this->removeLoadedFile($bigFilepath);
        $this->removeLoadedFile($locksFilepath);
        $this->removeLoadedFile($imagesLocksFilepath);
        $this->removeLoadedFile($pricesLocksFilepath);

        
        if (is_dir($serializeFilepath)) {
            $serializedFiles = scandir($serializeFilepath);

            foreach (scandir($serializeFilepath) as $index => $file) {
                $fileinfo = pathinfo($file);

                if (isset($fileinfo['extension'])) {
                    if ($fileinfo['extension'] === "serial") {
                        $this->removeLoadedFile($serializeFilepath . $file);
                        unset($serializedFiles[$index]);
                    }
                }
            }

            if (count($serializedFiles) == 2) {
                rmdir($serializeFilepath);
            }
        }

        echo "Deleting done.\n";

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

    private function getManager(): ObjectManager
    {
        return $this->manager;
    }

    private function setManager(ObjectManager $registry): void
    {
        $this->manager = $registry;
    }

    private function removeLoadedFile($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
