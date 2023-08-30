<?php

namespace App\Controller\Form;

use App\Form\InventoryPricesType;
use App\Service\FileReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class InventoryPriceController extends AbstractController
{
    #[Route('/appraise/create', name: 'app_form_appraise_create')]
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        $appraise_form = $this->createForm(InventoryPricesType::class);
        $appraise_form->handleRequest($request);

        if ($appraise_form->isValid() and  $appraise_form->isSubmitted()) {
            $form_data = $appraise_form->getData();

            /** @var UploadedFile $appraise_file */
            $appraise_file = $form_data['price_file'];

            $reader = new FileReader();
            $entities = $reader->getCSVPrices($appraise_file->getContent());

            foreach (array_chunk($entities, 1000) as $chunkIndex => $chunk) {
                $this->serializeAttachments($chunk, "chunk-". $chunkIndex ."-". $appraise_file->getClientOriginalName());
            }

            return $this->redirectToRoute('app_inventory_appraise');
        }

        return new JsonResponse(['message' => 'Форма не прошла валидацию в системе']);
    }

    private function serializeAttachments($entity, $filename)
    {
        $serializePath = $this->getParameter('inventory_serialize_price_directory');

        if (!is_dir($serializePath)) {
            mkdir($serializePath, recursive: true);
        }

        file_put_contents($serializePath . $filename . ".price", serialize($entity));
    }
}
