<?php

namespace App\Twig\Runtime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Extension\RuntimeExtensionInterface;

class InventorySerialAttachmentsRuntime extends AbstractController implements RuntimeExtensionInterface
{
    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function getImageFile(string $serial) : bool
    {
        $imagePath = $this->getParameter('inventory_generator_directory') ."images/";

        if (file_exists($imagePath . $serial . ".csv")) {
            return true;
        }

        return false;
    }

    public function getModelFile(string $serial) : bool
    {
        $modelPath = $this->getParameter('inventory_generator_directory') ."models/";

        if (file_exists($modelPath . $serial . ".csv")) {
            return true;
        }

        return false;
    }

    public function getPriceFile(string $serial) : bool
    {
        $pricePath = $this->getParameter('inventory_generator_directory') ."prices/";

        if (file_exists($pricePath . $serial . ".csv")) {
            return true;
        }

        return false;
    }
}
