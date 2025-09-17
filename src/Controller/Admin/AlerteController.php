<?php

namespace App\Controller\Admin;

use App\DTO\AlerteCreateDto;
use App\DTO\AlerteUpdateDto;
use App\Entity\Alerte;
use App\Service\Admin\AlerteAdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/alertes')]
#[IsGranted('ROLE_ADMIN')]
class AlerteController extends AbstractController
{
    public function __construct(
        private AlerteAdminService $alerteService
    ) {
    }

    #[Route('/', name: 'app_admin_alertes', methods: ['GET'])]
    public function alertes(): JsonResponse
    {
        return $this->alerteService->getAllAlertes();
    }

    #[Route('/', name: 'app_admin_alerte_create', methods: ['POST'])]
    public function createAlerte(#[MapRequestPayload] AlerteCreateDto $dto, Request $request): JsonResponse
    {
        return $this->alerteService->createAlerte($dto, $request);
    }

    #[Route('/{id}', name: 'app_admin_alerte_get', methods: ['GET'])]
    public function getAlerte(Alerte $alerte): JsonResponse
    {
        return $this->alerteService->getAlerte($alerte);
    }

    #[Route('/{id}', name: 'app_admin_alerte_update', methods: ['PUT'])]
    public function updateAlerte(Alerte $alerte, #[MapRequestPayload] AlerteUpdateDto $dto, Request $request): JsonResponse
    {
        return $this->alerteService->updateAlerte($alerte, $dto, $request);
    }

    #[Route('/{id}', name: 'app_admin_alerte_delete', methods: ['DELETE'])]
    public function deleteAlerte(Alerte $alerte, Request $request): JsonResponse
    {
        return $this->alerteService->deleteAlerte($alerte, $request);
    }
}