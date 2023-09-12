<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use App\Service\Serializer;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class InventoryGetterController extends AbstractController
{
    #[Route('/get/{serial}', name: 'app_get_serial', methods: ['POST'])]
    public function getSerial($serial, ManagerRegistry $registry): JsonResponse
    {
        $limit = 100;

        /** @var ObjectManager $manager */
        $manager = $registry->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);

        /** @var Inventory[] $inventory */
        $inventory = $inventoryRepository->findBy(['serial' => $serial], limit: $limit);

        $filter = $this->getSerialFilter($registry, $serial);

        $products = $this->prepareRequest($inventory, $filter);

        return new JsonResponse(['filter' => $filter, 'products' => $products]);
    }

    #[Route('/get/ordered/{serial}', name: 'app_get_ordered_serial', methods: ['POST'])]
    public function getOrderedSerial($serial, Request $request, ManagerRegistry $registry): JsonResponse
    {
        $requestData = $request->request->all();
        $limit = 100;

        if (isset($requestData['order'])) {
            $order = $requestData['order'];
        } else {
            $order = [];
        }

        /** @var ObjectManager $manager */
        $manager = $registry->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);

        $inventory = $inventoryRepository->findByOrder($serial, $order);

        foreach ($inventory as $itemIndex => $item) {
            if (get_class($item) !== "App\Entity\Inventory") {
                unset($inventory[$itemIndex]);
            }
        }

        $filter = $this->getSerialFilter($registry, $serial);
        $products = $this->prepareRequest($inventory, $filter);

        return new JsonResponse(['filter' => $filter, 'products' => $products]);
    }

    #[Route('/get/product/{code}', name: 'app_get_product')]
    public function getProduct(
        #[MapEntity(mapping: ['code' => 'code'])]
        Inventory $inventory
    ): JsonResponse
    {
        $serialize = Serializer::serializeElement($inventory);

        $item = json_decode($serialize, true);
        unset($item['created']);

        return new JsonResponse($item);
    }

    private function getSerialFilter(ManagerRegistry $registry, $serial): array
    {
        /** @var ObjectManager $manager */
        $manager = $registry->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);

        $filter = $inventoryRepository->getSerialFilter($serial);

        return $filter;
    }

    private function prepareRequest($inventory, &$filter) : array
    {
        $serializerArray = $filterOrder = $filterConstructed = [];

        foreach ($inventory as $item) {
            $serialize = Serializer::serializeElement($item);

            $object = json_decode($serialize, true);

            foreach ($object['parameters'] as $parameterIndex => $parameter) {
                if (!in_array($parameter['name'], $filterOrder)) {
                    $filterOrder[] = $parameter['name'];
                }

                unset(
                    $object['parameters'][$parameterIndex]['id'],
                    $object['parameters'][$parameterIndex]['code'],
                );
            }

            unset(
                $object['price']['id'],
                $object['price']['code'],
                $object['created'],
            );

            $serializerArray[] = $object;
        }

        foreach ($filterOrder as $filterPosition) {
            foreach ($filter as $item) {
                if ($item['name'] == $filterPosition) {
                    $filterConstructed[$item['name']]['values'][] = $item['value'];

                    if (!is_null($item['description'])) {
                        $filterConstructed[$item['name']]['descriptions'][] = $item['description'];
                    } else {
                        $filterConstructed[$item['name']]['descriptions'][] = "";
                    }
                }
            }
        }

        $filter = $filterConstructed;

        return $serializerArray;
    }
}
