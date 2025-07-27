<?php

namespace App\Controller;

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
}