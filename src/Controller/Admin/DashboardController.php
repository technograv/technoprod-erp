<?php

namespace App\Controller\Admin;

use App\Service\Admin\AdminDashboardService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
class DashboardController extends AbstractAdminController
{
    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ADMIN_ACCESS')]
    public function dashboard(AdminDashboardService $adminDashboardService): Response
    {
        // Force refresh du cache et récupération des données
        $adminDashboardService->refreshSecteursCache();
        $adminDashboardService->refreshCommerciauxCache();
        $dashboardData = $adminDashboardService->getAdminDashboardData();
        
        $templateData = array_merge($this->getBaseTemplateData(), [
            'pageTitle' => 'Administration - Dashboard TechnoProd',
            'stats' => $dashboardData['stats'],
            'google_maps_api_key' => $this->getParameter('google.maps.api.key'),
            'secteurs' => $dashboardData['secteurs'],
            'commerciaux' => $dashboardData['commerciaux'],
            'current_societe' => $dashboardData['current_societe'],
            'is_societe_mere' => $dashboardData['is_societe_mere'],
            'signature_entreprise' => $dashboardData['signature_entreprise'],
        ]);

        return $this->render('admin/dashboard.html.twig', $templateData);
    }

    protected function getBreadcrumb(): array
    {
        return [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Dashboard', 'url' => null]
        ];
    }
}