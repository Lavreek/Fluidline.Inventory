<?php
namespace App\Controller\Inventory;

use App\Command\Helper\Directory;
use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DeleteController extends AbstractController
{
    #[Route('/delete/{type}/{serial}', name: 'delete_type_serial')]
    public function deleteTypeSerial($type, $serial, ManagerRegistry $registry): Response
    {
        $directories = new Directory();
        $directories->setProductsPath($this->getParameter('products'));

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);
        $inventoryRepository->removeSerialByType($serial, $type);

        $bigSerialsPath = $directories->getBigsPath();
        $locksSerialsPath = $directories->getLocksPath();
        $serializedSerialsPath = $directories->getSerializePath();

        $bigFilepath = $bigSerialsPath . $type ."/". $serial .".big";
        $locksFilepath = $locksSerialsPath . $type ."/". $serial .".lock";
        $imagesLocksFilepath = $locksSerialsPath . "images/". $serial .".lock";
        $pricesLocksFilepath = $locksSerialsPath . "prices/". $serial .".lock";
        $serializeFilepath = $serializedSerialsPath . $serial . "/";

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

        return $this->redirectToRoute('admin_loaded_types');
    }

    private function removeLoadedFile($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
