<?php
namespace App\Controller\Inventory;

use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoadedController extends AbstractController
{

    #[Route('/admin/loaded/types', name: 'admin_loaded_types')]
    public function viewLoadedTypes(ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $types = $inventoryRepository->getDistinctTypes();

        return $this->render('inventory/loaded/types.html.twig', [
            'types' => $types
        ]);
    }

    #[Route('/admin/loaded/{type}/serials', name: 'admin_loaded_types_serials')]
    public function viewLoadedTypeSerials($type, ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $serials = $inventoryRepository->getDistinctTypeSerials($type);

        return $this->render('inventory/loaded/serials.html.twig', [
            'type' => $type,
            'serials' => $serials
        ]);
    }

    #[Route('/admin/loaded/{type}/{serials}/codes', name: 'admin_loaded_types_serials_codes')]
    public function viewLoadedTypeSerialsCodes($type, $serials, ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $codes = $inventoryRepository->findBy(['type' => $type, 'serial' => $serials]);

        return $this->render('inventory/loaded/codes.html.twig', [
            'type' => $type,
            'serials' => $serials,
            'codes' => $codes
        ]);
    }
}
