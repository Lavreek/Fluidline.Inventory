<?php

namespace App\Controller\Form;

use App\Entity\Inventory;
use App\Form\InventoryInputType;
use App\Repository\InventoryRepository;
use App\Service\EntityPuller;
use App\Service\FileReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class InventoryCreateController extends AbstractController
{
    private string $inputDirectory;

    public function __construct($inputDirectory)
    {
        $this->inputDirectory = $inputDirectory;
    }

    #[Route('/inventory/constructor/create', name: 'app_inventory_constructor_create')]
    public function createInventory(Request $request, EntityManagerInterface $manager) : JsonResponse|RedirectResponse
    {
        ini_set('memory_limit', '512M');

        $inventory_form = $this->createForm(InventoryInputType::class);
        $inventory_form->handleRequest($request);

        if ($inventory_form->isSubmitted() && $inventory_form->isValid()) {
            $form_data = $inventory_form->getData();

            /** @var UploadedFile $inventory_file */
            $inventory_file = $form_data['inventory_file'];

            /** @var string $inventory_type */
            $inventory_type = $form_data['inventory_type'];

            /** @var string $inventory_serial */
            $inventory_serial = $form_data['inventory_serial'];

            $reader = new FileReader();
            $reader->setFile($inventory_file);
            $reader->setReadDirectory($this->getInputDirectory());
            $reader->saveFile();

            $products = $reader->executeCreate();

            $puller = new EntityPuller();
            $puller->pullEntities($inventory_type, $inventory_serial, $products);

            try {
                $manager->beginTransaction();
                /** @var InventoryRepository $inventoryRepository */
                $inventoryRepository = $manager->getRepository(Inventory::class);
                $inventoryRepository->removeBySerialType($inventory_serial, $inventory_type);

                $rows = 0;
                foreach (array_chunk($products, 1000) as $chunkIndex => $chunk) {
                    $this->serializeProducts(
                        $chunk,
                        $inventory_serial,
                        "chunk-". $chunkIndex ."-". $inventory_file->getClientOriginalName()
                    );
                }

                $manager->commit();

                register_shutdown_function([$this, 'inventoryRemains'], $rows);

            } catch (\Exception | \Throwable $exception) {
                $manager->rollback();
            }

            return $this->redirectToRoute('app_inventory_constructor');
        }

        return new JsonResponse(['Форма не прошла валидацию в системе']);
    }

    private function getInputDirectory()
    {
        return $this->inputDirectory;
    }

    private function serializeProducts($products, $serial, $filename)
    {
        $serializePath = $this->getParameter('inventory_serialize_directory');

        $serialSerializePath =  $serializePath . "/" . $serial . "/";

        if (!is_dir($serialSerializePath)) {
            mkdir($serialSerializePath, recursive: true);
        }

        file_put_contents($serialSerializePath . $filename . ".serial", serialize($products));
    }

    private function inventoryRemains($rows)
    {
        $logFile = $this->getParameter('inventory_memory_usage');

        file_put_contents($logFile,
            "\n Inventory Remains:
            \n\t Memory at end: memory_get_usage(): ". memory_get_usage() ." \n
            \n\t Rows added: $rows\n"
        );
    }
}
