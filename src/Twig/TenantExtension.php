<?php

namespace App\Twig;

use App\Service\TenantService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class TenantExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private TenantService $tenantService
    ) {
    }

    /**
     * Variables globales disponibles dans tous les templates
     */
    public function getGlobals(): array
    {
        try {
            $context = $this->tenantService->getContextData();
            
            return [
                'tenant_context' => $context,
                'current_societe' => $context['current_societe'],
                'current_user_role' => $context['current_role'],
                'available_societes' => $context['available_societes'],
                'is_tenant_admin' => $context['is_current_admin'],
                'is_tenant_manager' => $context['is_current_manager'],
                'is_super_admin' => $context['is_super_admin'],
                'can_switch_societe' => $context['can_switch_societe'],
                'tenant_theme_colors' => $this->tenantService->getCurrentThemeColors(),
            ];
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des valeurs par défaut
            return [
                'tenant_context' => [],
                'current_societe' => null,
                'current_user_role' => null,
                'available_societes' => [],
                'is_tenant_admin' => false,
                'is_tenant_manager' => false,
                'is_super_admin' => false,
                'can_switch_societe' => false,
                'tenant_theme_colors' => ['primary' => '#dc3545', 'secondary' => '#6c757d'],
            ];
        }
    }

    /**
     * Fonctions Twig personnalisées
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant_has_permission', [$this, 'hasPermission']),
            new TwigFunction('tenant_parameter', [$this, 'getParameter']),
            new TwigFunction('tenant_societe_name', [$this, 'getSocieteName']),
            new TwigFunction('tenant_theme_color', [$this, 'getThemeColor']),
        ];
    }

    /**
     * Vérifie si l'utilisateur a une permission dans la société actuelle
     */
    public function hasPermission(string $permission): bool
    {
        try {
            return $this->tenantService->hasPermission($permission);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère un paramètre de la société actuelle avec fallback
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        try {
            return $this->tenantService->getCurrentParametre($key, $default);
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Récupère le nom d'affichage de la société actuelle
     */
    public function getSocieteName(): string
    {
        try {
            return $this->tenantService->getCurrentSocieteName();
        } catch (\Exception $e) {
            return 'TechnoProd';
        }
    }

    /**
     * Récupère une couleur du thème de la société actuelle
     */
    public function getThemeColor(string $colorKey = 'primary'): string
    {
        try {
            $colors = $this->tenantService->getCurrentThemeColors();
            return $colors[$colorKey] ?? '#dc3545';
        } catch (\Exception $e) {
            return $colorKey === 'secondary' ? '#6c757d' : '#dc3545';
        }
    }
}