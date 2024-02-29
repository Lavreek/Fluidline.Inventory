<?php
namespace App\Controller;

use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
use App\Service\Serializer;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['POST'])]
    public function search(Request $request, ManagerRegistry $registry): JsonResponse
    {
        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $registry->getRepository(Inventory::class);

        $requestData = $request->request->all();

        if (count($requestData) > 0) {
            $search = $inventoryRepository->codeSearch($requestData['code']);
            return new JsonResponse([
                'search' => $search
            ]);
        }

        return new JsonResponse(['По заданному запросу ничего не найдено.']);
    }

    #[Route('/search/full', name: 'app_search_full', methods: ['POST'])]
    public function searchCards(Request $request, ManagerRegistry $registry): JsonResponse
    {
        $requestData = $request->request->all();

        $limit = 10;

        if (count($requestData) > 0) {
            /** @var InventoryRepository $inventoryRepository */
            $inventoryRepository = $registry->getRepository(Inventory::class);

            if (isset($requestData['limit'])) {
                if ($requestData['limit'] < 25) {
                    $limit = $requestData['limit'];
                } else {
                    $limit = 25;
                }
            }

            $search = Serializer::serializeElement($inventoryRepository->productsSearch($requestData['code'], $limit));

            $full = [];

            foreach (json_decode($search, true) as $item) {
                unset($item['created']);
                $full[] = $item;
            }

            return new JsonResponse([
                'search' => $full
            ]);
        }

        return new JsonResponse(['По заданному запросу ничего не найдено.']);
    }
}
