<?php
namespace App\Controller\Inventory;

use App\Entity\Inventory\Inventory;
use App\Form\Remover\BySerialType;
use App\Repository\Inventory\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DeleteController extends AbstractController
{

    #[Route('/remove/by/serial', name: 'app_remove_by_serial')]
    public function bySerial(Request $request, ManagerRegistry $registry): Response
    {
        $removeForm = $this->createForm(BySerialType::class);
        $removeForm->handleRequest($request);

        if ($removeForm->isSubmitted() and $removeForm->isValid()) {
            $formData = $removeForm->getData();

            /** @var InventoryRepository $inventoryRepository */
            $inventoryRepository = $registry->getRepository(Inventory::class);
            $inventoryRepository->removeBySerialType($formData['serial'], $formData['type']);
        }

        return $this->render('inventory/remover/index.html.twig', [
            'remove_form' => $removeForm->createView()
        ]);
    }

    #[Route('/delete/{type}/{serial}', name: 'delete_type_serial')]
    public function deleteTypeSerial($type, $serial, ManagerRegistry $registry): Response
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);
        $inventoryRepository->removeBySerialType($serial, $type);

        return $this->redirectToRoute('admin_loaded_types');
    }
}
