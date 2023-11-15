<?php

namespace App\Controller\Auth;

use App\Form\Auth\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/auth/login', name: 'auth_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $login_form = $this->createForm(LoginType::class);
        $login_form->handleRequest($request);

        $renderOptions = [
            'login_form' => $login_form,
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ];

        return $this->render('auth/login/index.html.twig', $renderOptions);
    }
}
