<?php

namespace App\Service;

use App\Entity\Societe;
use App\Entity\User;
use App\Entity\UserSocieteRole;
use App\Entity\GroupeUtilisateur;
use App\Repository\SocieteRepository;
use App\Repository\UserSocieteRoleRepository;
use App\Repository\GroupeUtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

class TenantService
{
    private const SESSION_KEY_CURRENT_SOCIETE = 'current_societe_id';
    private const SESSION_KEY_AVAILABLE_SOCIETES = 'available_societes';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private SocieteRepository $societeRepository,
        private UserSocieteRoleRepository $userSocieteRoleRepository,
        private GroupeUtilisateurRepository $groupeUtilisateurRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Récupère la société actuellement sélectionnée
     */
    public function getCurrentSociete(): ?Societe
    {
        $session = $this->getSession();
        if (!$session) {
            $this->logger->warning("🔍 getCurrentSociete - NO SESSION");
            return null;
        }

        $societeId = $session->get(self::SESSION_KEY_CURRENT_SOCIETE);
        $this->logger->info("🔍 getCurrentSociete - Session contains societe_id: " . ($societeId ?: 'NULL'));

        if (!$societeId) {
            // IMPORTANT: Ne PAS définir automatiquement une société par défaut ici
            // Cela écrase le choix de l'utilisateur à chaque requête !
            // La sélection par défaut doit se faire via TenantRedirectListener
            return null;
        }

        $societe = $this->societeRepository->find($societeId);

        if ($societe) {
            $this->logger->info("✅ getCurrentSociete - Found société: {$societe->getId()} ({$societe->getNom()})");
        } else {
            $this->logger->warning("⚠️ getCurrentSociete - Société ID {$societeId} not found in database");
        }

        // IMPORTANT : Ne PAS vérifier hasAccessToSociete() ici !
        // Si une société est en session, c'est qu'elle a été validée lors du switchToSociete()
        // Vérifier l'accès à chaque requête empêche la persistance et cause des problèmes
        // La vérification d'accès doit se faire uniquement dans switchToSociete() et setCurrentSociete()

        return $societe;
    }

    /**
     * Définit la société actuellement sélectionnée
     */
    public function setCurrentSociete(Societe $societe): bool
    {
        $session = $this->getSession();
        if (!$session) {
            $this->logger->error("❌ setCurrentSociete - NO SESSION");
            return false;
        }

        if (!$this->hasAccessToSociete($societe)) {
            $this->logger->error("❌ setCurrentSociete - NO ACCESS to société {$societe->getId()} ({$societe->getNom()})");
            return false;
        }

        $session->set(self::SESSION_KEY_CURRENT_SOCIETE, $societe->getId());
        $this->logger->info("✅ setCurrentSociete - Set société {$societe->getId()} in session");

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
            // Utilisateur normal : selon ses rôles, groupes ET permissions individuelles
            $societes = [];
            
            // 1. Sociétés accessibles via les rôles directs (ancien système)
            // Temporairement désactivé à cause d'erreur DQL - Jean Martin n'a pas d'anciens rôles
            // $societesViaRoles = $this->userSocieteRoleRepository->findAccessibleSocietesByUser($user);
            // foreach ($societesViaRoles as $societe) {
            //     $societes[$societe->getId()] = $societe;
            // }
            
            // 2. Sociétés accessibles via les groupes
            $societesViaGroupes = $user->getAccessibleSocietesFromGroupes();
            foreach ($societesViaGroupes as $societe) {
                if ($societe->isActive()) {
                    $societes[$societe->getId()] = $societe;
                }
            }
            
            // 3. Sociétés accessibles via les permissions individuelles (nouveau système)
            foreach ($user->getPermissions() as $permission) {
                if ($permission->isActif() && $permission->getSociete()->isActive()) {
                    $societe = $permission->getSociete();
                    $societes[$societe->getId()] = $societe;
                    
                    // Si c'est une société mère, ajouter automatiquement les sociétés filles
                    if ($societe->isMere()) {
                        $enfants = $this->entityManager->getRepository(Societe::class)
                            ->findBy(['societeParent' => $societe, 'active' => true]);
                        foreach ($enfants as $enfant) {
                            $societes[$enfant->getId()] = $enfant;
                        }
                    }
                }
            }
            
            $societes = array_values($societes);
            
            // Trier les sociétés par ordre personnalisé
            usort($societes, function($a, $b) {
                // D'abord par ordre
                if ($a->getOrdre() !== $b->getOrdre()) {
                    return $a->getOrdre() <=> $b->getOrdre();
                }
                // Puis par type (mère avant fille)
                if ($a->getType() !== $b->getType()) {
                    return $a->getType() === 'mere' ? -1 : 1;
                }
                // Enfin par nom
                return strcmp($a->getNom(), $b->getNom());
            });
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
            $this->logger->warning("🔍 hasAccessToSociete - No user");
            return false;
        }

        if ($user->isSuperAdmin()) {
            $this->logger->info("🔍 hasAccessToSociete({$societe->getNom()}) - User is super admin = TRUE");
            return true;
        }

        // IMPORTANT : Ne PAS utiliser getAvailableSocietes() ici car elle dépend de la session
        // Cela crée des problèmes lors du refresh où le cache n'est pas encore populé
        // Au lieu de ça, vérifier directement en base de données

        // 1. Vérifier via les groupes
        $societesViaGroupes = $user->getAccessibleSocietesFromGroupes();
        foreach ($societesViaGroupes as $s) {
            if ($s->getId() === $societe->getId() && $s->isActive()) {
                $this->logger->info("🔍 hasAccessToSociete({$societe->getNom()}) - Access via groupe = TRUE");
                return true;
            }
        }

        // 2. Vérifier via les permissions individuelles
        foreach ($user->getPermissions() as $permission) {
            if ($permission->isActif() && $permission->getSociete()->getId() === $societe->getId()) {
                $this->logger->info("🔍 hasAccessToSociete({$societe->getNom()}) - Access via permission directe = TRUE");
                return true;
            }

            // Si permission sur société mère, vérifier si société demandée est fille
            if ($permission->isActif() && $permission->getSociete()->isMere()) {
                $enfants = $this->entityManager->getRepository(Societe::class)
                    ->findBy(['societeParent' => $permission->getSociete(), 'active' => true]);
                foreach ($enfants as $enfant) {
                    if ($enfant->getId() === $societe->getId()) {
                        $this->logger->info("🔍 hasAccessToSociete({$societe->getNom()}) - Access via société mère = TRUE");
                        return true;
                    }
                }
            }
        }

        $this->logger->warning("🔍 hasAccessToSociete({$societe->getNom()}) - NO ACCESS = FALSE");
        return false;
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
        $user = $this->getCurrentUser();
        $societe = $this->getCurrentSociete();
        
        if (!$user || !$societe) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Vérifier via le rôle direct dans la société
        $role = $this->getCurrentUserRole();
        if ($role && $role->hasPermission($permission)) {
            return true;
        }

        // Vérifier via les groupes
        foreach ($user->getGroupes() as $groupe) {
            if ($groupe->isActif() && 
                $groupe->hasAccessToSociete($societe) && 
                $groupe->hasPermissionRecursive($permission)) {
                return true;
            }
        }

        return false;
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
            'user_groups' => $user ? $user->getGroupes()->toArray() : [],
            'user_groups_for_societe' => $this->getUserGroupsForCurrentSociete(),
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
    public function getDefaultSocieteForCurrentUser(): ?Societe
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return null;
        }

        $societes = $this->getAvailableSocietes();
        if (empty($societes)) {
            return null;
        }

        // Priorité 1: Société principale définie par l'utilisateur (si elle est accessible)
        $societePrincipale = $user->getEffectiveSocietePrincipale();
        if ($societePrincipale && in_array($societePrincipale, $societes, true)) {
            return $societePrincipale;
        }

        // Priorité 2: Société mère d'abord, puis première société fille
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
        if (!$request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
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

    /**
     * Récupère les groupes de l'utilisateur qui ont accès à la société actuelle
     */
    public function getUserGroupsForCurrentSociete(): array
    {
        $user = $this->getCurrentUser();
        $societe = $this->getCurrentSociete();
        
        if (!$user || !$societe) {
            return [];
        }

        $groupsForSociete = [];
        foreach ($user->getGroupes() as $groupe) {
            if ($groupe->isActif() && $groupe->hasAccessToSociete($societe)) {
                $groupsForSociete[] = $groupe;
            }
        }

        return $groupsForSociete;
    }

    /**
     * Récupère toutes les permissions de l'utilisateur dans la société actuelle (rôles + groupes)
     */
    public function getAllUserPermissions(): array
    {
        $user = $this->getCurrentUser();
        $societe = $this->getCurrentSociete();
        
        if (!$user || !$societe) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            // Super admin a toutes les permissions
            return ['*'];
        }

        $permissions = [];

        // Permissions du rôle direct
        $role = $this->getCurrentUserRole();
        if ($role) {
            $permissions = array_merge($permissions, $role->getPermissions());
        }

        // Permissions des groupes
        foreach ($this->getUserGroupsForCurrentSociete() as $groupe) {
            $permissions = array_merge($permissions, $groupe->getAllPermissions());
        }

        return array_unique($permissions);
    }

    /**
     * Récupère le niveau de permission maximum de l'utilisateur dans la société actuelle
     */
    public function getUserMaxLevel(): int
    {
        $user = $this->getCurrentUser();
        $societe = $this->getCurrentSociete();
        
        if (!$user || !$societe) {
            return 0;
        }

        if ($user->isSuperAdmin()) {
            return 10;
        }

        $maxLevel = 0;

        // Niveau du rôle direct
        $role = $this->getCurrentUserRole();
        if ($role) {
            if ($role->isAdmin()) {
                $maxLevel = max($maxLevel, 8);
            } elseif ($role->isManager()) {
                $maxLevel = max($maxLevel, 6);
            } else {
                $maxLevel = max($maxLevel, 4);
            }
        }

        // Niveaux des groupes
        foreach ($this->getUserGroupsForCurrentSociete() as $groupe) {
            $maxLevel = max($maxLevel, $groupe->getNiveau());
        }

        return $maxLevel;
    }

    /**
     * Vérifie si l'utilisateur a un niveau de permission suffisant
     */
    public function hasMinimumLevel(int $requiredLevel): bool
    {
        return $this->getUserMaxLevel() >= $requiredLevel;
    }

    /**
     * Récupère les noms des groupes de l'utilisateur pour la société actuelle (pour affichage)
     */
    public function getUserGroupsNamesForCurrentSociete(): array
    {
        $groupNames = [];
        foreach ($this->getUserGroupsForCurrentSociete() as $groupe) {
            $groupNames[] = $groupe->getNom();
        }
        return $groupNames;
    }
}