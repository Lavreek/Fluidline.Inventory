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

    private function persistEntities($products, $serial, $type) : array
    {
        $newEntities = [];

        for ($i = 0; $i < count($products['codes']); $i++) {
            $code = $products['codes'][$i];
            $parameters = $products['parameters'][$i];
            $naming = $products['naming'][$i];

            $inventory = new Inventory();

            $inventory->setType($type);
            $inventory->setSerial($serial);
            $inventory->setCode($code);
            $inventory->setCreated(new \DateTime(date('Y-m-d H:i:s')));

            foreach ($parameters as $parameters_key => $parameters_value) {
                $paramhouse = new InventoryParamhouse();

                $paramhouse->setName($parameters_key);
                $paramhouse->setValue($parameters_value);

                 if (isset($naming[$parameters_key])) {
                     $paramhouse->setDescription($naming[$parameters_key]);
                 }

                $inventory->addParameter($paramhouse);
            }

            $newEntities[] = $inventory;
        }

        return $newEntities;
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

        if (!empty($path)) {
            file_put_contents($path . "/memory.log", $this->memory);
        }
    }

    public function pullEntities(string $type, string $serial, array $products) : array
    {
        register_shutdown_function([
            $this,
            'logMemoryData'
        ]);

        $this->setMemory('memory usage: start : ' . memory_get_usage());

        $entities = $this->persistEntities($products, $serial, $type);

        $this->setMemory('memory usage: end : ' . memory_get_usage());

        return $entities;
    }
}
