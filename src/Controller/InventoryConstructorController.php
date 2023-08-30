<?php

namespace App\Controller;

use App\Form\InventoryInputType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryConstructorController extends AbstractController
{
    #[Route('/inventory/constructor', name: 'app_inventory_constructor')]
    public function index(): Response
    {
        $inventory_form = $this->createForm(InventoryInputType::class);

        return $this->render('inventory_constructor/index.html.twig', [
            'inventory_form' => $inventory_form->createView(),
            'controller_name' => 'InventoryConstructorController',
        ]);
    }
}
