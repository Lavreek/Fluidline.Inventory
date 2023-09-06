<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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

    #[Route('/get/ordered/{serial}', name: 'app_get_serial', methods: ['POST'])]
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

        /** @var Inventory[] $inventory */
        $inventory = $inventoryRepository->findByOrder($serial, $order);

        foreach ($inventory as $itemIndex => $item) {
            if (get_class($item) !== "App\Entity\Inventory") {
                unset($inventory[$itemIndex]);
            }
        }

        $this->prepareOrdererRequest($inventory, $parametersArray, $serializerArray);

        return new JsonResponse(['filter' => $parametersArray, 'products' => $serializerArray]);
    }

    private function prepareOrdererRequest($inventory, &$parametersArray, &$serializerArray)
    {
        $encoders = [new JsonEncoder()];

        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER
            => function (object $object, string $format, array $context) : string
            {
                return $object->getCode();
            },
        ];

        $normalizers = [new ObjectNormalizer(defaultContext: $defaultContext)];

        $serializer = new Serializer($normalizers, $encoders);

        $serializerArray = $parametersArray = [];

        foreach ($inventory as $item) {
            $object = json_decode($serializer->serialize($item, 'json'), true);

            if (isset($object['parameters'])) {
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
            }

            unset(
                $object['price']['id'],
                $object['price']['code'],
                $object['created'],
            );

            $serializerArray[] = $object;
        }
    }

    private function prepareRequest($inventory, &$parametersArray, &$serializerArray)
    {
        $encoders = [new JsonEncoder()];

        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER
            => function (object $object, string $format, array $context) : string
            {
                return $object->getCode();
            },
        ];

        $normalizers = [new ObjectNormalizer(defaultContext: $defaultContext)];

        $serializer = new Serializer($normalizers, $encoders);

        $serializerArray = $parametersArray = [];

        foreach ($inventory as $item) {
            $object = json_decode($serializer->serialize($item, 'json'), true);

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

//    #[Route('/get/{serial}', name: 'app_get_serial')]
//    public function getSerial(
//        #[MapEntity(mapping: ['serial' => 'serial'])]
//        Inventory $inventory
//    ): JsonResponse
//    {
//        dd($inventory);
//
//        return new JsonResponse([$inventory]);
//    }
}
