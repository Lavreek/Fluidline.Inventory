<?php
namespace App\Controller\Inventory;

use App\Controller\Inventory\Helpers\GetHelper;
use App\Entity\Inventory\Inventory;
use App\Repository\Inventory\InventoryRepository;
use App\Service\Serializer;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GetController extends AbstractController
{
    /**
     * Максимальное возможное количество продуктов в 1 заказ
     */
    const query_max_limit = 50;

    #[Route('/get/{serial}', name: 'get_serial', methods: ['POST'])]
    public function getSerial($serial, Request $request, ManagerRegistry $registry): JsonResponse
    {
        $limit = 10;

        if ($request->request->get('limit') !== null) {
            $limit = $request->request->get('limit');

            if ($limit > self::query_max_limit) {
                $limit = self::query_max_limit;
            }
        }

        $manager = $registry->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);

        $inventory = $inventoryRepository->findBy(['serial' => $serial], limit: $limit);

        $filter = $inventoryRepository->getSerialFilter($serial);
        $products = GetHelper::prepareRequest($inventory, $filter);

        return new JsonResponse([
            'filter' => $filter,
            'products' => $products
        ]);
    }

    #[Route('/get/ordered/{serial}', name: 'get_ordered_serial', methods: ['POST'])]
    public function getOrderedSerial($serial, Request $request, ManagerRegistry $registry): JsonResponse
    {
        $limit = 10;

        if ($request->request->get('limit') !== null) {
            $limit = $request->request->get('limit');

            if ($limit > self::query_max_limit) {
                $limit = self::query_max_limit;
            }
        }

        $requestData = $request->request->all();

        if (isset($requestData['order'])) {
            $order = $requestData['order'];
        } else {
            $order = [];
        }

        $manager = $registry->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);

        $inventory = $inventoryRepository->findByOrder($serial, $order, $limit);

        foreach ($inventory as $itemIndex => $item) {
            if (get_class($item) !== "App\Entity\Inventory\Inventory") {
                unset($inventory[$itemIndex]);
            }
        }

        $filter = $inventoryRepository->getSerialFilter($serial);
        $products = GetHelper::prepareRequest($inventory, $filter);

        return new JsonResponse([
            'filter' => $filter,
            'products' => $products
        ]);
    }

    #[Route('/get/product/id/{id}', name: 'get_product_by_id')]
    public function getProductById(#[MapEntity(mapping: ['id' => 'id'])] Inventory $inventory): JsonResponse
    {
        $serialize = Serializer::serializeElement($inventory);

        $item = json_decode($serialize, true);
        unset($item['created']);

        return new JsonResponse($item, status: 200);
    }

    #[Route('/get/product/{code}', name: 'get_product_code')]
    public function getProduct(#[MapEntity(mapping: ['code' => 'code'])] Inventory $inventory): JsonResponse
    {
        $serialize = Serializer::serializeElement($inventory);

        $item = json_decode($serialize, true);
        unset($item['created']);

        return new JsonResponse($item, status: 200);
    }
}
