<?php

namespace App\Controller\Admin;

use App\Service\Admin\GroupeUtilisateurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/groupes')]
#[IsGranted('ROLE_ADMIN')]
class GroupeController extends AbstractController
{
    public function __construct(
        private GroupeUtilisateurService $groupeService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/{id}', name: 'app_admin_groupe_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGroupe(int $id): JsonResponse
    {
        return $this->groupeService->getGroupe($id);
    }

    #[Route('/{id}', name: 'app_admin_groupe_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateGroupe(int $id, Request $request): JsonResponse
    {
        return $this->groupeService->updateGroupe($id, $request);
    }

    #[Route('/', name: 'app_admin_groupe_create', methods: ['POST'])]
    public function createGroupe(Request $request): JsonResponse
    {
        return $this->groupeService->createGroupe($request);
    }

    #[Route('/{id}', name: 'app_admin_groupe_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteGroupe(int $id): JsonResponse
    {
        return $this->groupeService->deleteGroupe($id);
    }

    #[Route('/{id}/toggle', name: 'app_admin_groupe_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleGroupe(int $id): JsonResponse
    {
        return $this->groupeService->toggleGroupe($id);
    }
}