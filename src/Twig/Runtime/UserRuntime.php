<?php

namespace App\Twig\Runtime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Extension\RuntimeExtensionInterface;

class UserRuntime extends AbstractController implements RuntimeExtensionInterface
{
    public function __construct() { }

    public function getUserRole(UserInterface $user)
    {
        dd($user);
    }
}
