<?php
namespace App\Twig\Runtime;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Twig\Extension\RuntimeExtensionInterface;

class UserRuntime extends AbstractController implements RuntimeExtensionInterface
{
    public function __construct()
    {

    }

    public function isUser() : bool
    {
        $user = $this->getUser();

        if (!is_null($user)) {
            $roles = $this->getUser()->getRoles();

            if (in_array('ROLE_USER', $roles)) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin() : bool
    {
        $user = $this->getUser();

        if (!is_null($user)) {
            $roles = $this->getUser()->getRoles();

            if (in_array('ROLE_ADMIN', $roles)) {
                return true;
            }
        }

        return false;
    }

    public function getUsername() : string|bool
    {
        $user = $this->getUser();

        if (!is_null($user)) {
            return $user->getUserIdentifier();
        }

        return false;
    }
}
