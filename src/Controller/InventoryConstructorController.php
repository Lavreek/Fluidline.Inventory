<?php

namespace App\Controller;

use App\Form\InventoryInputType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryConstructorController extends AbstractController
{
    #[Route('/inventory/constructor', name: 'app_inventory_constructor', methods: ['GET'])]
    public function index(Request $request): Response|JsonResponse
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');

        if (!in_array($remoteAddress, ['77.50.146.14', '127.0.0.1'])) {
            return new JsonResponse();
        }

        $inventory_form = $this->createForm(InventoryInputType::class);

        return $this->render('inventory_constructor/index.html.twig', [
            'inventory_form' => $inventory_form->createView(),
            'controller_name' => 'InventoryConstructorController',
        ]);
    }
}
