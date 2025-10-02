<?php

namespace App\Controller\Admin;

use App\DTO\Alerte\AlerteCreateDto;
use App\DTO\Alerte\AlerteUpdateDto;
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
    public function createAlerte(Request $request): JsonResponse
    {
        return $this->alerteService->createAlerteFromRequest($request);
    }

    // Routes spécifiques AVANT les routes génériques avec {id}
    #[Route('/instances/{detectorClass}', name: 'app_admin_alerte_instances', methods: ['GET'])]
    public function getInstances(string $detectorClass): JsonResponse
    {
        // Symfony décode automatiquement les paramètres d'URL
        return $this->alerteService->getInstancesByDetector($detectorClass);
    }

    #[Route('/instance/{id}/resolve', name: 'app_admin_alerte_instance_resolve', methods: ['POST'])]
    public function resolveInstance(int $id): JsonResponse
    {
        /** @var \App\Entity\User $admin */
        $admin = $this->getUser();
        return $this->alerteService->resolveInstance($id, $admin);
    }

    #[Route('/scan', name: 'app_admin_alerte_scan', methods: ['POST'])]
    public function scanAlertes(): JsonResponse
    {
        return $this->alerteService->runDetection();
    }

    #[Route('/type/create', name: 'app_admin_alerte_type_create', methods: ['POST'])]
    public function createAlerteType(Request $request): JsonResponse
    {
        return $this->alerteService->createAlerteType($request);
    }

    #[Route('/type/{id}', name: 'app_admin_alerte_type_get', methods: ['GET'])]
    public function getAlerteType(int $id): JsonResponse
    {
        return $this->alerteService->getAlerteType($id);
    }

    #[Route('/type/{id}', name: 'app_admin_alerte_type_update', methods: ['PUT'])]
    public function updateAlerteType(int $id, Request $request): JsonResponse
    {
        return $this->alerteService->updateAlerteType($id, $request);
    }

    #[Route('/type/{id}', name: 'app_admin_alerte_type_delete', methods: ['DELETE'])]
    public function deleteAlerteType(int $id, Request $request): JsonResponse
    {
        return $this->alerteService->deleteAlerteType($id, $request);
    }

    #[Route('/ordre', name: 'app_admin_alerte_ordre', methods: ['POST'])]
    public function updateOrdre(Request $request): JsonResponse
    {
        return $this->alerteService->updateOrdre($request);
    }

    #[Route('/entity-fields/{entityClass}', name: 'app_admin_alerte_entity_fields', methods: ['GET'])]
    public function getEntityFields(string $entityClass): JsonResponse
    {
        // Symfony décode automatiquement les paramètres d'URL, pas besoin de urldecode()
        return $this->alerteService->getEntityFields($entityClass);
    }

    // Routes génériques avec {id} EN DERNIER
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