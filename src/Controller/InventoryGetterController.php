<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class InventoryGetterController extends AbstractController
{
    #[Route('/get/{serial}', name: 'app_get_serial')]
    public function getSerial($serial, ManagerRegistry $registry): JsonResponse
    {
        $limit = 100;

        /** @var ObjectManager $manager */
        $manager = $registry->getManager();

        /** @var InventoryRepository $inventoryRepository */
        $inventoryRepository = $manager->getRepository(Inventory::class);

        /** @var Inventory[] $inventory */
        $inventory = $inventoryRepository->findBy(['serial' => $serial], $limit);

        $encoders = [new JsonEncoder()];

        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object, string $format, array $context): string {
                return $object->getCode();
            },
        ];

        $normalizers = [new ObjectNormalizer(defaultContext: $defaultContext)];

        $serializer = new Serializer($normalizers, $encoders);

        $serializerArray = $parametersArray = [];

        foreach ($inventory as $item) {
            $object = json_decode($serializer->serialize($item, 'json'), true);

            foreach ($object['parameters'] as $parameter) {
                if (!isset($parametersArray[$parameter['name']])) {
                    $parametersArray[$parameter['name']] = ['values' => []];
                }

                if (!isset($parametersArray[$parameter['name']]['description'])) {
                    $parametersArray[$parameter['name']]['description'] = $parameter['description'];
                }

                if (!in_array($parameter['value'], $parametersArray[$parameter['name']]['values'])) {
                    $parametersArray[$parameter['name']]['values'][] = $parameter['value'];
                }
            }

            unset($object['created']);

            $serializerArray[] = $object;
        }

        return new JsonResponse(['filter' => $parametersArray, 'products' => $serializerArray]);
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
