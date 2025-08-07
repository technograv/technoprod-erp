<?php

namespace App\Service;

use App\Entity\Societe;
use App\Entity\User;
use App\Entity\UserSocieteRole;
use App\Repository\SocieteRepository;
use App\Repository\UserSocieteRoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TenantService
{
    private const SESSION_KEY_CURRENT_SOCIETE = 'current_societe_id';
    private const SESSION_KEY_AVAILABLE_SOCIETES = 'available_societes';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private SocieteRepository $societeRepository,
        private UserSocieteRoleRepository $userSocieteRoleRepository
    ) {
    }

    /**
     * Récupère la société actuellement sélectionnée
     */
    public function getCurrentSociete(): ?Societe
    {
        $session = $this->getSession();
        if (!$session) {
            return null;
        }

        $societeId = $session->get(self::SESSION_KEY_CURRENT_SOCIETE);
        if (!$societeId) {
            // Aucune société sélectionnée, prendre la première disponible
            $societe = $this->getDefaultSocieteForCurrentUser();
            if ($societe) {
                $this->setCurrentSociete($societe);
                return $societe;
            }
            return null;
        }

        $societe = $this->societeRepository->find($societeId);
        
        // Vérifier que l'utilisateur a encore accès à cette société
        if ($societe && !$this->hasAccessToSociete($societe)) {
            // Plus d'accès, réinitialiser
            $session->remove(self::SESSION_KEY_CURRENT_SOCIETE);
            return $this->getCurrentSociete(); // Récursif pour prendre la première disponible
        }

        return $societe;
    }

    /**
     * Définit la société actuellement sélectionnée
     */
    public function setCurrentSociete(Societe $societe): bool
    {
        $session = $this->getSession();
        if (!$session) {
            return false;
        }

        if (!$this->hasAccessToSociete($societe)) {
            return false;
        }

        $session->set(self::SESSION_KEY_CURRENT_SOCIETE, $societe->getId());
        
        // Mettre à jour le cache des sociétés disponibles
        $this->refreshAvailableSocietes();
        
        return true;
    }

    /**
     * Récupère toutes les sociétés accessibles à l'utilisateur actuel
     */
    public function getAvailableSocietes(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return [];
        }

        $session = $this->getSession();
        $cached = $session?->get(self::SESSION_KEY_AVAILABLE_SOCIETES);
        
        // Si cache valide, l'utiliser
        if ($cached && is_array($cached) && !empty($cached)) {
            $societes = [];
            foreach ($cached as $societeData) {
                $societe = $this->societeRepository->find($societeData['id']);
                if ($societe) {
                    $societes[] = $societe;
                }
            }
            if (!empty($societes)) {
                return $societes;
            }
        }

        // Rafraîchir le cache
        return $this->refreshAvailableSocietes();
    }

    /**
     * Rafraîchit le cache des sociétés disponibles
     */
    public function refreshAvailableSocietes(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            // Super admin : toutes les sociétés
            $societes = $this->societeRepository->findActiveSocietes();
        } else {
            // Utilisateur normal : selon ses rôles
            $societes = $this->userSocieteRoleRepository->findAccessibleSocietesByUser($user);
        }

        // Mettre en cache
        $session = $this->getSession();
        if ($session) {
            $cacheData = [];
            foreach ($societes as $societe) {
                $cacheData[] = [
                    'id' => $societe->getId(),
                    'nom' => $societe->getNom(),
                    'type' => $societe->getType(),
                ];
            }
            $session->set(self::SESSION_KEY_AVAILABLE_SOCIETES, $cacheData);
        }

        return $societes;
    }

    /**
     * Vérifie si l'utilisateur actuel a accès à une société
     */
    public function hasAccessToSociete(Societe $societe): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessToSociete($societe);
    }

    /**
     * Récupère le rôle de l'utilisateur dans la société actuelle
     */
    public function getCurrentUserRole(): ?UserSocieteRole
    {
        $user = $this->getCurrentUser();
        $societe = $this->getCurrentSociete();
        
        if (!$user || !$societe) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            // Créer un rôle virtuel pour le super admin
            $role = new UserSocieteRole();
            $role->setUser($user)
                 ->setSociete($societe)
                 ->setRole(UserSocieteRole::ROLE_ADMIN)
                 ->setActive(true);
            return $role;
        }

        return $user->getRoleInSociete($societe);
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique dans la société actuelle
     */
    public function hasPermission(string $permission): bool
    {
        $role = $this->getCurrentUserRole();
        return $role ? $role->hasPermission($permission) : false;
    }

    /**
     * Vérifie si l'utilisateur est admin dans la société actuelle
     */
    public function isCurrentAdmin(): bool
    {
        $role = $this->getCurrentUserRole();
        return $role ? $role->isAdmin() : false;
    }

    /**
     * Vérifie si l'utilisateur est manager dans la société actuelle
     */
    public function isCurrentManager(): bool
    {
        $role = $this->getCurrentUserRole();
        return $role ? $role->isManager() : false;
    }

    /**
     * Récupère les informations de contexte pour les templates
     */
    public function getContextData(): array
    {
        $user = $this->getCurrentUser();
        $societe = $this->getCurrentSociete();
        $role = $this->getCurrentUserRole();
        $availableSocietes = $this->getAvailableSocietes();

        return [
            'current_user' => $user,
            'current_societe' => $societe,
            'current_role' => $role,
            'available_societes' => $availableSocietes,
            'is_super_admin' => $user?->isSuperAdmin() ?? false,
            'is_current_admin' => $this->isCurrentAdmin(),
            'is_current_manager' => $this->isCurrentManager(),
            'can_switch_societe' => count($availableSocietes) > 1,
        ];
    }

    /**
     * Switch vers une société (avec vérifications)
     */
    public function switchToSociete(int $societeId): bool
    {
        $societe = $this->societeRepository->find($societeId);
        if (!$societe || !$societe->isActive()) {
            return false;
        }

        return $this->setCurrentSociete($societe);
    }

    /**
     * Récupère la société par défaut pour l'utilisateur actuel
     */
    private function getDefaultSocieteForCurrentUser(): ?Societe
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return null;
        }

        $societes = $this->getAvailableSocietes();
        if (empty($societes)) {
            return null;
        }

        // Priorité : société mère d'abord, puis première société fille
        foreach ($societes as $societe) {
            if ($societe->isMere()) {
                return $societe;
            }
        }

        // Sinon, première société disponible
        return $societes[0];
    }

    /**
     * Nettoie le cache des sociétés (à appeler lors de modifications)
     */
    public function clearCache(): void
    {
        $session = $this->getSession();
        if ($session) {
            $session->remove(self::SESSION_KEY_AVAILABLE_SOCIETES);
        }
    }

    /**
     * Récupère l'utilisateur actuel
     */
    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    /**
     * Récupère la session courante
     */
    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request?->getSession();
    }

    /**
     * Méthodes utilitaires pour les templates et contrôleurs
     */

    /**
     * Récupère le nom de la société actuelle (pour affichage)
     */
    public function getCurrentSocieteName(): string
    {
        $societe = $this->getCurrentSociete();
        return $societe?->getDisplayName() ?? 'Aucune société sélectionnée';
    }

    /**
     * Récupère les couleurs de la société actuelle
     */
    public function getCurrentThemeColors(): array
    {
        $societe = $this->getCurrentSociete();
        return [
            'primary' => $societe?->getCouleurPrimaire() ?? '#dc3545',
            'secondary' => $societe?->getCouleurSecondaire() ?? '#6c757d',
        ];
    }

    /**
     * Récupère un paramètre custom de la société actuelle avec fallback vers société mère
     */
    public function getCurrentParametre(string $key, mixed $default = null): mixed
    {
        $societe = $this->getCurrentSociete();
        if (!$societe) {
            return $default;
        }

        // Paramètre de la société actuelle
        $value = $societe->getParametreCustom($key);
        if ($value !== null) {
            return $value;
        }

        // Fallback vers société mère si c'est une fille
        if ($societe->isFille() && $societe->getSocieteParent()) {
            $value = $societe->getSocieteParent()->getParametreCustom($key);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }
}