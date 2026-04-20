<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Contrôleur temporaire pour les tests - À SUPPRIMER EN PRODUCTION
 */
class TestAuthController extends AbstractController
{
    #[Route('/test/login/{email}', name: 'test_login')]
    public function testLogin(
        string $email,
        EntityManagerInterface $em,
        Security $security,
        Request $request
    ): Response {
        // Sécurité : uniquement en environnement dev
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createAccessDeniedException('Test login only available in dev environment');
        }

        // Trouver l'utilisateur
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new Response('User not found: ' . $email, 404);
        }

        // Connecter l'utilisateur
        $security->login($user);

        return $this->redirectToRoute('workflow_dashboard');
    }
}
