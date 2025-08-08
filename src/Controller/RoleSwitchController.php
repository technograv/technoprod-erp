<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class RoleSwitchController extends AbstractController
{
    #[Route('/switch-role', name: 'switch_role', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function switchRole(Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier qu'on est en mode développement
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json(['success' => false, 'message' => 'Switch de rôles disponible uniquement en mode développement']);
        }

        $data = json_decode($request->getContent(), true);
        $newRole = $data['role'] ?? null;

        // Valider le rôle demandé
        $allowedRoles = ['ROLE_USER', 'ROLE_COMMERCIAL', 'ROLE_ADMIN'];
        if (!in_array($newRole, $allowedRoles)) {
            return $this->json(['success' => false, 'message' => 'Rôle non autorisé']);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non connecté']);
        }

        // Mettre à jour les rôles de l'utilisateur
        switch ($newRole) {
            case 'ROLE_ADMIN':
                $user->setRoles(['ROLE_ADMIN', 'ROLE_COMMERCIAL', 'ROLE_USER']);
                break;
            case 'ROLE_COMMERCIAL':
                $user->setRoles(['ROLE_COMMERCIAL', 'ROLE_USER']);
                break;
            case 'ROLE_USER':
                $user->setRoles(['ROLE_USER']);
                break;
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();
        
        // Mettre à jour le token de sécurité avec les nouveaux rôles
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);

        return $this->json([
            'success' => true, 
            'message' => 'Rôle changé avec succès',
            'new_role' => $newRole,
            'roles' => $user->getRoles()
        ]);
    }

    #[Route('/get-test-users', name: 'get_test_users', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTestUsers(EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Vérifier qu'on est en mode développement
            if ($this->getParameter('kernel.environment') !== 'dev') {
                return $this->json(['success' => false, 'message' => 'Switch d\'utilisateurs disponible uniquement en mode développement']);
            }

            // Récupérer tous les utilisateurs actifs
            $users = $entityManager->getRepository(User::class)->findBy([
                'isActive' => true
            ], ['nom' => 'ASC']);
            
            $currentUser = $this->getUser();
            $usersData = [];
            
            // Trouver le compte principal Google OAuth (celui qui s'est connecté initialement)
            $mainGoogleAccount = null;
            foreach ($users as $user) {
                if ($user->isGoogleAccount()) {
                    $mainGoogleAccount = $user;
                    break; // On prend le premier compte Google trouvé comme compte principal
                }
            }
            
            // Ajouter le compte principal Google en premier (s'il existe)
            if ($mainGoogleAccount) {
                $isCurrentUser = $mainGoogleAccount->getId() === $currentUser->getId();
                $usersData[] = [
                    'id' => $mainGoogleAccount->getId(),
                    'nom' => $mainGoogleAccount->getFullName(),
                    'email' => $mainGoogleAccount->getEmail(),
                    'roles' => $mainGoogleAccount->getRoles(),
                    'societePrincipale' => $mainGoogleAccount->getSocietePrincipale() ? $mainGoogleAccount->getSocietePrincipale()->getNom() : null,
                    'groupes' => $mainGoogleAccount->getNomsGroupes(),
                    'isCurrentUser' => $isCurrentUser,
                    'isMainAccount' => true
                ];
            }
            
            // Puis ajouter les comptes de test (non-Google)
            foreach ($users as $user) {
                if (!$user->isGoogleAccount()) {
                    $isCurrentUser = $user->getId() === $currentUser->getId();
                    $usersData[] = [
                        'id' => $user->getId(),
                        'nom' => $user->getFullName(),
                        'email' => $user->getEmail(),
                        'roles' => $user->getRoles(),
                        'societePrincipale' => $user->getSocietePrincipale() ? $user->getSocietePrincipale()->getNom() : null,
                        'groupes' => $user->getNomsGroupes(),
                        'isCurrentUser' => $isCurrentUser,
                        'isMainAccount' => false
                    ];
                }
            }

            return $this->json([
                'success' => true,
                'users' => $usersData,
                'currentUserId' => $this->getUser()->getId()
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/switch-user', name: 'switch_user', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function switchUser(Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): JsonResponse
    {
        // Vérifier qu'on est en mode développement
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json(['success' => false, 'message' => 'Switch d\'utilisateurs disponible uniquement en mode développement']);
        }

        $data = json_decode($request->getContent(), true);
        $targetUserId = $data['userId'] ?? null;

        if (!$targetUserId) {
            return $this->json(['success' => false, 'message' => 'ID utilisateur manquant']);
        }

        $targetUser = $entityManager->getRepository(User::class)->find($targetUserId);
        
        if (!$targetUser) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }

        if (!$targetUser->isActive()) {
            return $this->json(['success' => false, 'message' => 'Utilisateur inactif']);
        }

        // Permettre le switch vers un compte Google OAuth pour permettre le retour au compte principal
        // (pas de restriction particulière en mode dev pour les tests)

        // Mettre à jour le token de sécurité avec le nouvel utilisateur
        $token = new UsernamePasswordToken($targetUser, 'main', $targetUser->getRoles());
        $tokenStorage->setToken($token);

        return $this->json([
            'success' => true, 
            'message' => 'Utilisateur changé avec succès',
            'user' => [
                'id' => $targetUser->getId(),
                'nom' => $targetUser->getFullName(),
                'email' => $targetUser->getEmail(),
                'roles' => $targetUser->getRoles()
            ]
        ]);
    }
}