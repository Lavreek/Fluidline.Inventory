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

        $this->prepareRequest($inventory, $parametersArray, $serializerArray);

        return new JsonResponse(['filter' => $parametersArray, 'products' => $serializerArray]);
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

        $this->prepareRequest($inventory, $parametersArray, $serializerArray);

        return new JsonResponse(['filter' => $parametersArray, 'products' => $serializerArray]);
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

    private function prepareRequest($inventory, &$parametersArray, &$serializerArray)
    {
        $serializerArray = $parametersArray = [];

        foreach ($inventory as $item) {
            $serialize = Serializer::serializeElement($item);

            $object = json_decode($serialize, true);

            foreach ($object['parameters'] as $parameterIndex => $parameter) {
                if (!isset($parametersArray[$parameter['name']])) {
                    $parametersArray[$parameter['name']] = ['values' => []];
                }

                if (!in_array($parameter['value'], $parametersArray[$parameter['name']]['values'])) {
                    $parametersArray[$parameter['name']]['values'][] = $parameter['value'];
                    if (isset($parameter['description'])) {
                        $parametersArray[$parameter['name']]['description'][] = $parameter['description'];
                    }
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
    }
}
