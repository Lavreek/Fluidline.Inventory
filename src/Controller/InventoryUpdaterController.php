<?php

namespace App\Controller;

use App\Form\InventoryUpdateType;
use App\Service\AttachmentUpdater;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryUpdaterController extends AbstractController
{
    #[Route('/update/full/price', name: 'app_update_full_price')]
    public function updateFullPrice(): Response
    {
        return $this->render('inventory_updater/index.html.twig', [
            'controller_name' => 'InventoryUpdaterController',
        ]);
    }

    #[Route('/update/{serial}/price', name: 'app_update_serial_price')]
    public function updateSerialPrice($serial): Response
    {
        return $this->render('inventory_updater/index.html.twig', [
            'type' => 'Цен',
            'serial' => $serial,
        ]);
    }

    #[Route('/update/full/model', name: 'app_update_full_model')]
    public function updateFullModel(): Response
    {
        return $this->render('inventory_updater/index.html.twig', [
            'controller_name' => 'InventoryUpdaterController',
        ]);
    }

    #[Route('/update/{serial}/model', name: 'app_update_serial_model')]
    public function updateSerialModel($serial): Response
    {
        return $this->render('inventory_updater/index.html.twig', [
            'type' => 'Моделей',
            'serial' => $serial,
        ]);
    }

    #[Route('/update/full/image', name: 'app_update_full_image')]
    public function updateFullImage(): Response
    {
        return $this->render('inventory_updater/index.html.twig', [
            'controller_name' => 'InventoryUpdaterController',
        ]);
    }

    #[Route('/update/{serial}/image', name: 'app_update_serial_image')]
    public function updateSerialImage($serial, Request $request, ManagerRegistry $registry): Response
    {
        $imageForm = $this->createForm(InventoryUpdateType::class);
        $imageForm->handleRequest($request);

        if ($imageForm->isSubmitted() and $imageForm->isValid()) {
            $imagePath = $this->getParameter('inventory_generator_images_directory');
            $data = $imageForm->getData();

            $updater = new AttachmentUpdater();
            $updater->setSerial($serial);
            $updater->setFilepath($imagePath);
            $entitiesContent = $updater->updateAttachments($data['update_file']->getContent(), 'image_path');

            $updatePath = $this->getParameter('inventory_serialize_update_directory');

            if (!is_dir($updatePath . $serial)) {
                mkdir($updatePath . $serial, recursive: true);
            }

            file_put_contents($updatePath . $serial . "/update_image_path.csv", $entitiesContent);
        }

        return $this->render('inventory_updater/serial.html.twig', [
            'type' => 'Изображений',
            'serial' => $serial,
            'serial_form' => $imageForm,
        ]);
    }
}
