<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use App\Service\Serializer;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryViewerController extends AbstractController
{
    #[Route('/view/serial', name: 'app_view_serial')]
    public function viewSerial(ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $serials = $inventoryRepository->distinctSerial();

        return $this->render('inventory_viewer/index.html.twig', [
            'serials' => $serials,
        ]);
    }

    #[Route('/view/serial/{serial}', name: 'app_view_products_by_serial')]
    public function viewProductsBySerial($serial, ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $products = $inventoryRepository->findBySerial($serial);

        $productsTable = [];

        foreach ($products as $product) {
            $productsTable[] = json_decode(Serializer::serializeElement($product), true);
        }

        return $this->render('inventory_viewer/products.html.twig', [
            'serial' => $serial,
            'products' => $productsTable,
        ]);
    }
}
