<?php

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountController extends AbstractController
{
    #[Route('/auth/account', name: 'auth_account')]
    public function index(): Response
    {
        return $this->render('auth/account/index.html.twig', [
            'user' => $this->getUser(),
            'controller_name' => 'AccountController',
        ]);
    }
}
