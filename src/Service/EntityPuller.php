<?php
namespace App\Service;

use App\Entity\Inventory\Inventory;
use App\Entity\Inventory\InventoryParamhouse;

class EntityPuller
{

    private string $memory = "";

    private string $logpath = "";

    private function setMemory($value)
    {
        $this->memory .= date("Y-m-d H:i:s") . " - " . $value . " \n";
    }

    private function persistEntities($entity, $serial, $type): Inventory
    {
        $inventory = new Inventory();

        $inventory->setType($type);
        $inventory->setSerial($serial);
        $inventory->setCode($entity['code']);
        $inventory->setCreated(new \DateTime(date('Y-m-d H:i:s')));

        if (! empty($entity['parameters'])) {
            foreach ($entity['parameters'] as $parameter) {
                $paramhouse = new InventoryParamhouse();

                $paramhouse->setName($parameter['name']);
                $paramhouse->setValue($parameter['value']);

                if (isset($parameter['description'])) {
                    $paramhouse->setDescription($parameter['description']);
                }

                $inventory->addParameter($paramhouse);
            }
        }

        return $inventory;
    }

    public function setLogfilePath($path): void
    {
        $this->logpath = $path;
    }

    public function getLogfilePath(): string
    {
        return $this->logpath;
    }

    private function logMemoryData()
    {
        $path = $this->getLogfilePath();

        if (! is_null($path)) {
            file_put_contents($path . "/memory.log", $this->memory);
        }
    }

    public function pullEntities(string $type, string $serial, array &$entities)
    {
        register_shutdown_function([
            $this,
            'logMemoryData'
        ]);

        $this->setMemory('memory usage: start : ' . memory_get_usage());

        for ($i = 0; $i < count($entities); $i ++) {
            $entities[$i] = $this->persistEntities($entities[$i], $serial, $type);
        }

        $this->setMemory('memory usage: end : ' . memory_get_usage());
    }
}