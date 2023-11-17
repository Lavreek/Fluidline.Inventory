<?php

namespace App\Controller\Inventory;

use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
use App\Service\Serializer;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoadedController extends AbstractController
{
    #[Route('/admin/loaded/serials', name: 'admin_loaded_serials')]
    public function viewSerial(ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $serials = $inventoryRepository->distinctSerial();

        return $this->render('inventory/loaded/serials.html.twig', [
            'user' => $this->getUser(),
            'serials' => $serials,
        ]);
    }

    #[Route('/admin/view/serial/{serial}', name: 'admin_view_products_by_serial')]
    public function viewProductsBySerial($serial, ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $products = $inventoryRepository->findBySerial($serial);

        $productsTable = [];

        foreach ($products as $product) {
            $productsTable[] = json_decode(Serializer::serializeElement($product), true);
        }

        return $this->render('inventory/viewer/products.html.twig', [
            'serial' => $serial,
            'products' => $productsTable,
        ]);
    }
}
