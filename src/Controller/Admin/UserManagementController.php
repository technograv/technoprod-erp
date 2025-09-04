<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\GroupeUtilisateur;
use App\Entity\UserPermission;
use App\Entity\Societe;
use App\Repository\UserRepository;
use App\Repository\GroupeUtilisateurRepository;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class UserManagementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantService $tenantService
    ) {}

    // ================================
    // USERS MANAGEMENT
    // ================================

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(): Response
    {
        $users = $this->entityManager
            ->getRepository(User::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $groupes = $this->entityManager
            ->getRepository(GroupeUtilisateur::class)
            ->findBy(['actif' => true], ['nom' => 'ASC']);
        
        $societes = $this->entityManager
            ->getRepository(Societe::class)
            ->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/user_management/users.html.twig', [
            'users' => $users,
            'groupes' => $groupes,
            'societes' => $societes
        ]);
    }

    #[Route('/users/{id}/toggle-active', name: 'app_admin_users_toggle_active', methods: ['POST'])]
    public function toggleUserActive(User $user): JsonResponse
    {
        try {
            $user->setIsActive(!$user->isActive());
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'active' => $user->isActive(),
                'message' => $user->isActive() ? 'Utilisateur activé' : 'Utilisateur désactivé'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/users/{id}/update-roles', name: 'app_admin_users_update_roles', methods: ['PUT'])]
    public function updateUserRoles(Request $request, User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['roles']) || !is_array($data['roles'])) {
                return $this->json(['error' => 'Rôles invalides'], 400);
            }

            // Validation des rôles
            $validRoles = ['ROLE_USER', 'ROLE_COMMERCIAL', 'ROLE_MANAGER', 'ROLE_ADMIN'];
            $roles = array_intersect($data['roles'], $validRoles);
            
            if (empty($roles)) {
                $roles = ['ROLE_USER']; // Au moins un rôle par défaut
            }

            $user->setRoles($roles);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Rôles mis à jour avec succès',
                'roles' => $user->getRoles()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/users/{id}/groupes', name: 'app_admin_users_get_groupes', methods: ['GET'])]
    public function getUserGroupes(User $user): JsonResponse
    {
        $groupes = [];
        foreach ($user->getGroupes() as $groupe) {
            $groupes[] = [
                'id' => $groupe->getId(),
                'nom' => $groupe->getNom(),
                'couleur' => $groupe->getCouleur(),
                'niveau' => $groupe->getNiveau()
            ];
        }

        return $this->json([
            'groupes' => $groupes,
            'societe_principale' => $user->getSocietePrincipale()?->getNom()
        ]);
    }

    #[Route('/users/{id}/groupes', name: 'app_admin_users_update_groupes', methods: ['PUT'])]
    public function updateUserGroupes(Request $request, User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['groupes_ids'])) {
                return $this->json(['error' => 'IDs groupes manquants'], 400);
            }

            // Vider les groupes actuels
            $user->getGroupes()->clear();
            
            // Ajouter les nouveaux groupes
            foreach ($data['groupes_ids'] as $groupeId) {
                $groupe = $this->entityManager->find(GroupeUtilisateur::class, $groupeId);
                if ($groupe && $groupe->isActif()) {
                    $user->addGroupe($groupe);
                }
            }

            $this->entityManager->flush();

            // Retourner les données des groupes pour la mise à jour de l'interface
            $groupesData = [];
            foreach ($user->getGroupes() as $groupe) {
                $groupesData[] = [
                    'id' => $groupe->getId(),
                    'nom' => $groupe->getNom(),
                    'couleur' => $groupe->getCouleur()
                ];
            }

            return $this->json([
                'success' => true,
                'message' => 'Groupes mis à jour avec succès',
                'groupes' => $groupesData
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/users/{id}/permissions', name: 'app_admin_users_get_permissions', methods: ['GET'])]
    public function getUserPermissions(User $user): JsonResponse
    {
        $userPermissions = $this->entityManager->getRepository(UserPermission::class)
            ->findBy(['user' => $user]);
        
        $permissions = [];
        
        foreach ($userPermissions as $userPermission) {
            if ($userPermission->isActif()) {
                $permissions[] = [
                    'societe_id' => $userPermission->getSociete()->getId(),
                    'societe_nom' => $userPermission->getSociete()->getNom(),
                    'permissions' => $userPermission->getPermissions(),
                    'niveau' => $userPermission->getNiveau(),
                    'actif' => $userPermission->isActif()
                ];
            }
        }

        return $this->json($permissions);
    }

    #[Route('/users/{id}/permissions', name: 'app_admin_users_update_permissions', methods: ['PUT'])]
    public function updateUserPermissions(Request $request, User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['permissions']) || !is_array($data['permissions'])) {
                return $this->json(['error' => 'Format de données incorrect'], 400);
            }

            // Supprimer toutes les permissions existantes de cet utilisateur
            $existingPermissions = $this->entityManager->getRepository(UserPermission::class)
                ->findBy(['user' => $user]);
            
            foreach ($existingPermissions as $permission) {
                $this->entityManager->remove($permission);
            }

            // Ajouter les nouvelles permissions
            foreach ($data['permissions'] as $permissionData) {
                if (!isset($permissionData['societe_id']) || !isset($permissionData['permissions'])) {
                    continue;
                }

                $societe = $this->entityManager->find(Societe::class, $permissionData['societe_id']);
                if (!$societe) {
                    continue;
                }

                $userPermission = new UserPermission();
                $userPermission->setUser($user);
                $userPermission->setSociete($societe);
                $userPermission->setPermissions($permissionData['permissions']);
                $userPermission->setNiveau($permissionData['niveau'] ?? 5);
                $userPermission->setActif($permissionData['actif'] ?? true);
                
                $this->entityManager->persist($userPermission);
            }
            
            $this->entityManager->flush();

            // Récupérer les nouvelles permissions pour l'affichage
            $newPermissions = $this->entityManager->getRepository(UserPermission::class)
                ->findBy(['user' => $user]);
            
            $permissionsData = [];
            foreach ($newPermissions as $permission) {
                $permissionsData[] = [
                    'societe' => [
                        'id' => $permission->getSociete()->getId(),
                        'nom' => $permission->getSociete()->getNom()
                    ],
                    'niveau' => $permission->getNiveau(),
                    'permissions' => $permission->getPermissions()
                ];
            }

            return $this->json([
                'success' => true,
                'message' => 'Permissions mises à jour avec succès',
                'permissions' => $permissionsData
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/users/{id}/societe-principale', name: 'app_admin_users_update_societe_principale', methods: ['PUT'])]
    public function updateUserSocietePrincipale(Request $request, User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['societe_id'])) {
                return $this->json(['error' => 'ID société manquant'], 400);
            }

            $societe = $this->entityManager->find(Societe::class, $data['societe_id']);
            if (!$societe) {
                return $this->json(['error' => 'Société non trouvée'], 404);
            }

            $user->setSocietePrincipale($societe);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Société principale mise à jour avec succès',
                'societe' => $societe ? [
                    'id' => $societe->getId(),
                    'nom' => $societe->getNom()
                ] : null
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/users/{id}', name: 'app_admin_user_get', methods: ['GET'])]
    public function getUserDetails(User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'roles' => $user->getRoles(),
            'is_active' => $user->isActive(),
            'created_at' => $user->getCreatedAt()?->format('d/m/Y H:i'),
            'updated_at' => $user->getUpdatedAt()?->format('d/m/Y H:i'),
            'societe_principale' => $user->getSocietePrincipale()?->getNom(),
            'groupes_count' => $user->getGroupes()->count()
        ]);
    }

    #[Route('/users/{id}/reset-password', name: 'app_admin_user_reset_password', methods: ['POST'])]
    public function resetUserPassword(User $user, MailerInterface $mailer): JsonResponse
    {
        try {
            // Générer un mot de passe temporaire
            $tempPassword = $this->generateRandomPassword();
            
            // Hacher le mot de passe
            // Note: Dans un vrai système, utiliser UserPasswordHasherInterface
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Mettre à jour l'utilisateur
            $user->setPassword($hashedPassword);
            $user->setMustChangePassword(true); // Forcer le changement au prochain login
            $this->entityManager->flush();

            // Envoyer l'email avec le nouveau mot de passe
            $email = (new Email())
                ->from('admin@technoprod.com')
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe TechnoProd')
                ->html($this->renderView('emails/password_reset.html.twig', [
                    'user' => $user,
                    'temp_password' => $tempPassword
                ]));

            $mailer->send($email);

            return $this->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé et envoyé par email'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la réinitialisation: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // GROUPES UTILISATEURS
    // ================================

    #[Route('/groupes-utilisateurs', name: 'app_admin_groupes_utilisateurs', methods: ['GET'])]
    public function groupesUtilisateurs(): Response
    {
        $groupes = $this->entityManager
            ->getRepository(GroupeUtilisateur::class)
            ->findBy([], ['ordre' => 'ASC', 'nom' => 'ASC']);
        
        // Calcul des statistiques pour le dashboard
        $repository = $this->entityManager->getRepository(GroupeUtilisateur::class);
        $stats = [
            'total' => $repository->count([]),
            'actifs' => $repository->count(['actif' => true]),
            'racines' => $repository->count(['parent' => null]),
            'enfants' => $repository->createQueryBuilder('g')
                ->select('COUNT(g.id)')
                ->where('g.parent IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult()
        ];
        
        // Permissions disponibles dans le système
        $availablePermissions = [
            'admin' => [
                'admin.all' => 'Administration complète',
                'users.manage' => 'Gestion des utilisateurs',
                'companies.manage' => 'Gestion des sociétés',
                'system.config' => 'Configuration système'
            ],
            'users' => [
                'users.read' => 'Lecture utilisateurs',
                'users.create' => 'Création utilisateurs',
                'users.update' => 'Modification utilisateurs',
                'users.delete' => 'Suppression utilisateurs'
            ],
            'clients' => [
                'clients.read' => 'Lecture clients',
                'clients.create' => 'Création clients',
                'clients.update' => 'Modification clients',
                'clients.delete' => 'Suppression clients'
            ],
            'devis' => [
                'devis.read' => 'Lecture devis',
                'devis.create' => 'Création devis',
                'devis.update' => 'Modification devis',
                'devis.delete' => 'Suppression devis',
                'devis.sign' => 'Signature devis'
            ],
            'reports' => [
                'reports.all' => 'Tous les rapports',
                'reports.commercial' => 'Rapports commerciaux',
                'reports.financial' => 'Rapports financiers'
            ]
        ];
        
        return $this->render('admin/user_management/groupes_utilisateurs.html.twig', [
            'groupes' => $groupes,
            'stats' => $stats,
            'available_permissions' => $availablePermissions
        ]);
    }

    // ================================
    // API ROUTES
    // ================================

    #[Route('/api/societes-tree', name: 'app_admin_api_societes_tree', methods: ['GET'])]
    public function getSocietesTree(): JsonResponse
    {
        $societes = $this->entityManager
            ->getRepository(Societe::class)
            ->findBy(['societeParent' => null, 'active' => true], ['nom' => 'ASC']);
        
        $result = [];
        
        foreach ($societes as $societe) {
            $societeData = [
                'id' => $societe->getId(),
                'nom' => $societe->getNom(),
                'display_name' => $societe->getDisplayName(),
                'type' => $societe->getType(),
                'enfants' => []
            ];
            
            // Ajouter les sociétés filles
            $enfants = $this->entityManager
                ->getRepository(Societe::class)
                ->findBy(['societeParent' => $societe, 'active' => true], ['nom' => 'ASC']);
            
            foreach ($enfants as $enfant) {
                $societeData['enfants'][] = [
                    'id' => $enfant->getId(),
                    'nom' => $enfant->getNom(),
                    'display_name' => $enfant->getDisplayName(),
                    'type' => $enfant->getType(),
                    'parent_id' => $societe->getId()
                ];
            }
            
            $result[] = $societeData;
        }
        
        return $this->json($result);
    }

    #[Route('/api/groupes-disponibles', name: 'app_admin_api_groupes_disponibles', methods: ['GET'])]
    public function getGroupesDisponibles(): JsonResponse
    {
        $groupes = $this->entityManager
            ->getRepository(GroupeUtilisateur::class)
            ->findBy(['actif' => true], ['nom' => 'ASC']);
        
        $result = [];
        
        foreach ($groupes as $groupe) {
            $result[] = [
                'id' => $groupe->getId(),
                'nom' => $groupe->getNom(),
                'description' => $groupe->getDescription(),
                'niveau' => $groupe->getNiveau(),
                'couleur' => $groupe->getCouleur()
            ];
        }
        
        return $this->json($result);
    }

    // ================================
    // HELPER METHODS
    // ================================

    private function generateRandomPassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }
}