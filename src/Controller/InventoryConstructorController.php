<?php

namespace App\Controller;

use App\Form\InventoryInputType;
use App\Service\EntityPuller;
use App\Service\FileReader;
use Doctrine\Persistence\ManagerRegistry;
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
    private string $inputDirectory;

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
    public function createInventory(Request $request, ManagerRegistry $registry): JsonResponse
    {
        $inventory_form = $this->createForm(InventoryInputType::class);
        $inventory_form->handleRequest($request);

        if ($inventory_form->isSubmitted() && $inventory_form->isValid()) {
            $form_data = $inventory_form->getData();

            /** @var UploadedFile $inventory_file */
            $inventory_file = $form_data['inventory_file'];

            /** @var string $inventory_type */
            $inventory_type = $form_data['inventory_type'];

            $filename = $inventory_file->getClientOriginalName();

            try {
                file_put_contents(
                    $this->getInputDirectory() . $filename,
                    $inventory_file->getContent()
                );

            } catch (FileException $e) {
                return new JsonResponse(['error' => "Ошибка при загрузке файла.", 'exception' => $e->getMessage()]);
            }

            $products = (new FileReader())->executeCreate($this->getInputDirectory() . $filename);

            $puller = (new EntityPuller($registry))->pullEntities($inventory_type, $products);

            return new JsonResponse(['Сущности были успешно добавлены']);
        }

        return new JsonResponse(['Форма не прошла валидацию в системе']);
    }

    private function getInputDirectory()
    {
        return $this->inputDirectory;
    }
}
