<?php

namespace App\Service;

use App\Entity\Inventory;
use App\Entity\InventoryParamhouse;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

class EntityPuller
{
    private ManagerRegistry $registry;

    public function __construct($registry)
    {
        $this->registry = $registry;
    }

    public function pullEntities(string $type, array $entities)
    {
        $manager = $this->getManager();

        $inventoryRepository = $manager->getRepository(Inventory::class);

        $inventories = [];

        foreach ($entities as $entity) {
            $inventory = $inventoryRepository->findBy(['type' => $type, 'code' => $entity['code']]);

            if (empty($inventory)) {
                $inventory = new Inventory();

                $inventory->setType($type);
                $inventory->setCode($entity['code']);

                if (!empty($entity['parameters'])) {
                    foreach ($entity['parameters'] as $parameter) {
                        $paramhouse = new InventoryParamhouse();

                        $paramhouse->setName($parameter['name']);
                        $paramhouse->setValue($parameter['value']);

                        if (isset($parameter['description'])) {
                            $paramhouse->setDescription($parameter['description']);
                        }

                        $inventory->addParameters($paramhouse);
                    }
                }

                $inventories[] = $inventory;
            }
        }

        foreach ($inventories as $inventory) {
            $manager->persist($inventory);
        }
        $manager->flush();
    }

    private function getRegistry() : ManagerRegistry
    {
        return $this->registry;
    }

    private function getManager() : ObjectManager
    {
        return $this->getRegistry()->getManager();
    }
}