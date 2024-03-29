<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomepageController extends AbstractController
{
    #[Route(['/', '/home', '/homepage'], name: 'app_home')]
    public function home(): Response
    {
        return $this->render('homepage/index.html.twig');
    }
}
