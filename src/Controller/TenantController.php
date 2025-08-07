<?php

namespace App\Controller;

use App\Service\TenantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tenant')]
#[IsGranted('ROLE_USER')]
class TenantController extends AbstractController
{
    public function __construct(
        private TenantService $tenantService
    ) {
    }

    /**
     * Switch vers une société
     */
    #[Route('/switch/{societeId}', name: 'app_tenant_switch', methods: ['POST'])]
    public function switchSociete(int $societeId): JsonResponse
    {
        $success = $this->tenantService->switchToSociete($societeId);
        
        if ($success) {
            $context = $this->tenantService->getContextData();
            return $this->json([
                'success' => true,
                'message' => 'Société changée avec succès',
                'current_societe' => [
                    'id' => $context['current_societe']->getId(),
                    'nom' => $context['current_societe']->getNom(),
                    'display_name' => $context['current_societe']->getDisplayName(),
                    'type' => $context['current_societe']->getType(),
                ],
                'theme_colors' => $this->tenantService->getCurrentThemeColors(),
                'current_role' => $context['current_role']?->getRoleLibelle(),
                'redirect_needed' => true // Pour recharger la page si nécessaire
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Impossible de changer vers cette société'
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Récupère les informations de contexte actuel
     */
    #[Route('/context', name: 'app_tenant_context', methods: ['GET'])]
    public function getContext(): JsonResponse
    {
        $context = $this->tenantService->getContextData();
        
        $response = [
            'current_societe' => null,
            'available_societes' => [],
            'current_role' => null,
            'permissions' => [],
            'theme_colors' => $this->tenantService->getCurrentThemeColors(),
            'is_super_admin' => $context['is_super_admin'],
            'can_switch_societe' => $context['can_switch_societe'],
        ];

        if ($context['current_societe']) {
            $response['current_societe'] = [
                'id' => $context['current_societe']->getId(),
                'nom' => $context['current_societe']->getNom(),
                'display_name' => $context['current_societe']->getDisplayName(),
                'type' => $context['current_societe']->getType(),
                'logo' => $context['current_societe']->getLogo(),
            ];
        }

        foreach ($context['available_societes'] as $societe) {
            $response['available_societes'][] = [
                'id' => $societe->getId(),
                'nom' => $societe->getNom(),
                'display_name' => $societe->getDisplayName(),
                'type' => $societe->getType(),
                'logo' => $societe->getLogo(),
                'is_current' => $context['current_societe'] && $societe->getId() === $context['current_societe']->getId(),
            ];
        }

        if ($context['current_role']) {
            $response['current_role'] = [
                'role' => $context['current_role']->getRole(),
                'libelle' => $context['current_role']->getRoleLibelle(),
                'is_admin' => $context['current_role']->isAdmin(),
                'is_manager' => $context['current_role']->isManager(),
            ];
            
            // Permissions courantes
            $permissions = ['create', 'read', 'update', 'delete', 'manage_users', 'manage_clients', 'manage_devis', 'manage_comptabilite'];
            foreach ($permissions as $permission) {
                $response['permissions'][$permission] = $context['current_role']->hasPermission($permission);
            }
        }

        return $this->json($response);
    }

    /**
     * Rafraîchit le cache des sociétés
     */
    #[Route('/refresh', name: 'app_tenant_refresh', methods: ['POST'])]
    public function refreshCache(): JsonResponse
    {
        $this->tenantService->clearCache();
        $societes = $this->tenantService->refreshAvailableSocietes();
        
        return $this->json([
            'success' => true,
            'message' => 'Cache rafraîchi',
            'count' => count($societes)
        ]);
    }

    /**
     * Récupère les paramètres de thème pour la société actuelle
     */
    #[Route('/theme', name: 'app_tenant_theme', methods: ['GET'])]
    public function getTheme(): JsonResponse
    {
        $societe = $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return $this->json([
                'success' => false,
                'message' => 'Aucune société sélectionnée'
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'theme' => [
                'nom_societe' => $societe->getDisplayName(),
                'couleur_primaire' => $societe->getCouleurPrimaire() ?? '#dc3545',
                'couleur_secondaire' => $societe->getCouleurSecondaire() ?? '#6c757d',
                'logo' => $societe->getLogo(),
                'template_theme' => $this->tenantService->getCurrentParametre('template_theme', 'default'),
                'parametres_custom' => $societe->getParametresCustom(),
            ]
        ]);
    }

    /**
     * Page de sélection de société (si aucune n'est sélectionnée)
     */
    #[Route('/select', name: 'app_tenant_select', methods: ['GET'])]
    public function selectSociete(): Response
    {
        $context = $this->tenantService->getContextData();
        
        // Si une société est déjà sélectionnée, rediriger vers le dashboard
        if ($context['current_societe']) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Si aucune société disponible, erreur
        if (empty($context['available_societes'])) {
            throw $this->createAccessDeniedException('Aucune société accessible.');
        }

        return $this->render('tenant/select.html.twig', [
            'available_societes' => $context['available_societes'],
            'is_super_admin' => $context['is_super_admin'],
        ]);
    }
}