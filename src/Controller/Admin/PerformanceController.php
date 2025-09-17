<?php

namespace App\Controller\Admin;

use App\Service\Admin\PerformanceCommercialeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/performances')]
#[IsGranted('ROLE_ADMIN')]
class PerformanceController extends AbstractController
{
    public function __construct(
        private PerformanceCommercialeService $performanceService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/commerciaux', name: 'app_admin_commerciaux', methods: ['GET'])]
    public function getCommerciaux(): JsonResponse
    {
        return $this->performanceService->getCommerciaux();
    }

    #[Route('/secteurs', name: 'app_admin_secteurs_list', methods: ['GET'])]
    public function getSecteursList(): JsonResponse
    {
        return $this->performanceService->getSecteursList();
    }

    #[Route('/secteurs-admin', name: 'app_admin_secteurs_admin', methods: ['GET'])]
    public function getSecteursAdmin(): Response
    {
        return $this->performanceService->getSecteursAdmin();
    }

    #[Route('/objectifs/{id}', name: 'app_admin_objectifs_commercial', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateObjectifsCommercial(int $id, Request $request): JsonResponse
    {
        return $this->performanceService->updateObjectifsCommercial($id, $request);
    }

    #[Route('/commerciales', name: 'app_admin_performances_commerciales', methods: ['GET'])]
    public function getPerformancesCommerciales(Request $request): JsonResponse
    {
        return $this->performanceService->getPerformancesCommerciales($request);
    }

    #[Route('/export', name: 'app_admin_performances_export', methods: ['GET'])]
    public function exportPerformancesCommerciales(Request $request): Response
    {
        return $this->performanceService->exportPerformancesCommerciales($request);
    }
}