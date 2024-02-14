<?php
namespace App\Controller\Form;

use App\Entity\Inventory\Inventory;
use App\Form\Inventory\InventoryInputType;
use App\Repository\Inventory\InventoryRepository;
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

    private string $uploadDirectory;

    private string $logfileDirectory;

    #[Route('/constructor/create', name: 'app_constructor_create')]
    public function createInventory(Request $request, EntityManagerInterface $manager): JsonResponse|RedirectResponse
    {
        ini_set('memory_limit', '512M');

        $inventory_form = $this->createForm(InventoryInputType::class);
        $inventory_form->handleRequest($request);

        if ($inventory_form->isSubmitted() && $inventory_form->isValid()) {
            $this->setUploadDirectory($this->getParameter('inventory_upload_directory'));
            $this->setLogfileDirectory($this->getParameter('inventory_memory_usage'));

            $form_data = $inventory_form->getData();

            /** @var UploadedFile $inventory_file */
            $inventory_file = $form_data['inventory_file'];

            /** @var string $inventory_type */
            $inventory_type = $form_data['inventory_type'];

            /** @var string $inventory_serial */
            $inventory_serial = $form_data['inventory_serial'];

            $reader = new FileReader();
            $reader->setReadDirectory($this->getUploadDirectory());
            $reader->setFile($inventory_file);
            $reader->saveFile();

            $products = $reader->executeCreate();

            $logFile = $this->getLogfileDirectory();

            $puller = new EntityPuller();
            $puller->setLogfilePath(dirname($logFile));
            $puller->pullEntities($inventory_type, $inventory_serial, $products);

            try {
                /** @var InventoryRepository $inventoryRepository */
                $inventoryRepository = $manager->getRepository(Inventory::class);
                $inventoryRepository->removeSerialByType($inventory_serial, $inventory_type);

                $chunkCount = 0;
                foreach (array_chunk($products, 1000) as $chunkIndex => $chunk) {
                    $this->serializeProducts($chunk, $inventory_serial, "chunk-" . $chunkIndex . "-" . $inventory_file->getClientOriginalName());

                    $chunkCount ++;
                }

                /** @var string $imageSerialPath | Путь к файлу изображений серии */
                $priceSerialPath = $this->getParameter('inventory_generator_directory') . "prices/";

                $priceFile = $priceSerialPath . $inventory_serial . ".csv";
                if (file_exists($priceFile)) {
                    unlink($priceFile);
                }

                /** @var string $imageSerialPath | Путь к файлу изображений серии */
                $imageSerialPath = $this->getParameter('inventory_generator_directory') . "images/";

                $imageFile = $imageSerialPath . $inventory_serial . ".csv";
                if (file_exists($imageFile)) {
                    unlink($imageFile);
                }

                /** @var string $modelSerialPath | Путь к файлу моделей серии */
                $modelSerialPath = $this->getParameter('inventory_generator_directory') . "models/";

                $modelFile = $modelSerialPath . $inventory_serial . ".csv";
                if (file_exists($modelFile)) {
                    unlink($modelFile);
                }

                register_shutdown_function([
                    $this,
                    'inventoryRemains'
                ], $chunkCount);
            } catch (\Exception | \Throwable) {}

            return $this->redirectToRoute('app_home');
        }

        return new JsonResponse([
            'Форма не прошла валидацию в системе'
        ]);
    }

    private function serializeProducts($products, $serial, $filename): void
    {
        $serializePath = $this->getParameter('inventory_serialize_directory');
        $serialSerializePath = $serializePath . "/" . $serial . "/";

        if (! is_dir($serialSerializePath)) {
            mkdir($serialSerializePath, recursive: true);
        }

        file_put_contents($serialSerializePath . $filename . ".serial", serialize($products));
    }

    private function inventoryRemains($count)
    {
        $logFile = $this->getLogfileDirectory();

        file_put_contents($logFile, "\n Inventory Remains:" . "\n\t Memory at end: " . memory_get_usage() . "\n\t Chunks added: $count\n");
    }

    private function getUploadDirectory(): string
    {
        return $this->uploadDirectory;
    }

    private function setUploadDirectory($path): void
    {
        $this->uploadDirectory = $path;
    }

    private function getLogfileDirectory(): string
    {
        return $this->logfileDirectory;
    }

    private function setLogfileDirectory($path): void
    {
        $this->logfileDirectory = $path;
    }
}