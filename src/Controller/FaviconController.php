<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FaviconController extends AbstractController
{
    #[Route('/favicon.ico', name: 'app_favicon')]
    public function index(): Response
    {
        $svgContent = file_get_contents($this->getParameter('kernel.project_dir') . '/public/favicon.svg');

        return new Response(
            $svgContent,
            Response::HTTP_OK,
            ['Content-Type' => 'image/svg+xml']
        );
    }
}
