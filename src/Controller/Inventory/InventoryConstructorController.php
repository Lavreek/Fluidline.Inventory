<?php
namespace App\Controller\Inventory;

use App\Form\Inventory\InventoryInputType;
use App\Form\InventoryPricesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryConstructorController extends AbstractController
{

    #[Route('/download', name: 'app_download', methods: [
        'GET'
    ])]
    public function index(Request $request): Response
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');

        // ToDo: Make auth.
        if (! in_array($remoteAddress, [
            '77.50.146.14',
            '185.180.124.14',
            '127.0.0.1'
        ])) {
            return new Response();
        }

        $inventory_form = $this->createForm(InventoryInputType::class);

        return $this->render('inventory/constructor/index.html.twig', [
            'inventory_form' => $inventory_form->createView(),
            'controller_name' => 'InventoryConstructorController'
        ]);
    }

    #[Route('/appraise', name: 'app_appraise', methods: [
        'GET'
    ])]
    public function appraise(Request $request): Response
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');

        // ToDo: Make auth.
        if (! in_array($remoteAddress, [
            '77.50.146.14',
            '185.180.124.14',
            '127.0.0.1'
        ])) {
            return new Response();
        }

        $inventory_form = $this->createForm(InventoryPricesType::class);

        return $this->render('inventory/constructor/index.html.twig', [
            'inventory_form' => $inventory_form->createView(),
            'controller_name' => 'InventoryConstructorController'
        ]);
    }
}
