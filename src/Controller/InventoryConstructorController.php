<?php

namespace App\Controller;

use App\Form\InventoryInputType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class InventoryConstructorController extends AbstractController
{
    private $inputDirectory;

    public function __construct($inputDirectory)
    {
        $this->inputDirectory = $inputDirectory;
    }

    #[Route('/inventory/constructor', name: 'app_inventory_constructor')]
    public function index(): Response
    {
        $inventory_form = $this->createForm(InventoryInputType::class);


        return $this->render('inventory_constructor/index.html.twig', [
            'inventory_form' => $inventory_form->createView(),
            'controller_name' => 'InventoryConstructorController',
        ]);
    }

    #[Route('/inventory/constructor/create', name: 'app_inventory_constructor_create')]
    public function createInventory(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $inventory_form = $this->createForm(InventoryInputType::class);
        $inventory_form->handleRequest($request);

        if ($inventory_form->isSubmitted() && $inventory_form->isValid()) {
            $form_data = $inventory_form->getData();

            /** @var UploadedFile $inventory_file */
            $inventory_file = $form_data['inventory_file'];

            $safe_filename = $slugger->slug($inventory_file->getClientOriginalName());
            $filename = $safe_filename . "." . $inventory_file->guessExtension();

            try {
                $inventory_file->move($this->getInputDirectory(), $filename);

            } catch (FileException $e) {
                return new JsonResponse('Ошибка при загрузке файла.');
            }
        }

        return new JsonResponse('here');
    }

    private function getInputDirectory()
    {
        return $this->inputDirectory;
    }
}
