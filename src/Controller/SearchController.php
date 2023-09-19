<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['POST'])]
    public function search(Request $request, ManagerRegistry $registry) : JsonResponse
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $requestData = $request->request->all();

        if (count($requestData) > 0) {
            $search = $inventoryRepository->codeSearch($requestData['code']);
            return new JsonResponse(['search' => $search]);
        }

        return new JsonResponse([]);
    }
}