<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/maintenance')]
class MaintenanceController extends AbstractController
{
    #[Route('', name: 'app_admin_maintenance', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/maintenance.html.twig');
    }
}
