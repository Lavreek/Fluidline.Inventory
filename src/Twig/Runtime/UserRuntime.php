<?php
namespace App\Twig\Runtime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Extension\RuntimeExtensionInterface;

class UserRuntime extends AbstractController implements RuntimeExtensionInterface
{
    public function __construct()
    {

    }

    public function isUser() : bool
    {
        $roles = $this->getUser()->getRoles();

        if (in_array('ROLE_USER', $roles)) {
            return true;
        }

        return false;
    }

    public function isAdmin() : bool
    {
        $roles = $this->getUser()->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        return false;
    }

    public function getUsername() : string
    {
        $user = $this->getUser();

        return $user->getUserIdentifier();
    }
}
