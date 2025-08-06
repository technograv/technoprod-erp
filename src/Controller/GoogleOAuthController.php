<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\GmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class GoogleOAuthController extends AbstractController
{
    #[Route('/google-oauth/start', name: 'google_oauth_start')]
    public function start(SessionInterface $session): Response
    {
        $client_id = $_ENV['GOOGLE_OAUTH_CLIENT_ID'];
        $redirect_uri = 'https://test.decorpub.fr:8080/google-oauth/callback';
        $state = bin2hex(random_bytes(16));
        
        // Stocker le state en session
        $session->set('oauth_state', $state);
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'openid email profile https://www.googleapis.com/auth/gmail.settings.basic https://www.googleapis.com/auth/gmail.send',
            'response_type' => 'code',
            'state' => $state,
            'access_type' => 'offline'
        ];
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        return $this->redirect($auth_url);
    }
    
    #[Route('/google-oauth/callback', name: 'google_oauth_callback')]
    public function callback(Request $request, SessionInterface $session, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage, GmailService $gmailService): Response
    {
        // Log de debug pour le test manuel
        $log_file = '/home/decorpub/TechnoProd/technoprod/var/manual_oauth_debug.log';
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Test OAuth manuel - début callback\n", FILE_APPEND);
        
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $stored_state = $session->get('oauth_state');
        
        file_put_contents($log_file, "Code: " . substr($code, 0, 20) . "...\n", FILE_APPEND);
        file_put_contents($log_file, "State: " . $state . "\n", FILE_APPEND);
        
        if (!$code) {
            $this->addFlash('error', 'Pas de code d\'autorisation reçu');
            return $this->redirectToRoute('app_login');
        }
        
        if ($state !== $stored_state) {
            $this->addFlash('error', 'État OAuth invalide');
            return $this->redirectToRoute('app_login');
        }
        
        // Échanger le code contre un token
        $client_id = $_ENV['GOOGLE_OAUTH_CLIENT_ID'];
        $client_secret = $_ENV['GOOGLE_OAUTH_CLIENT_SECRET'];
        $redirect_uri = 'https://test.decorpub.fr:8080/google-oauth/callback';
        
        $post_data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirect_uri
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $this->addFlash('error', 'Erreur cURL: ' . $curl_error);
            return $this->redirectToRoute('app_login');
        }
        
        $token_data = json_decode($response, true);
        
        if ($http_code !== 200 || isset($token_data['error'])) {
            file_put_contents($log_file, "ERREUR TOKEN: " . ($token_data['error'] ?? 'Unknown') . " - " . ($token_data['error_description'] ?? '') . "\n", FILE_APPEND);
            file_put_contents($log_file, "HTTP Code: " . $http_code . "\n", FILE_APPEND);
            file_put_contents($log_file, "Response: " . $response . "\n", FILE_APPEND);
            $this->addFlash('error', 'Erreur token: ' . ($token_data['error'] ?? 'Unknown') . ' - ' . ($token_data['error_description'] ?? ''));
            return $this->redirectToRoute('app_login');
        }
        
        // Récupérer les informations utilisateur
        $access_token = $token_data['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_data = json_decode($user_response, true);
        
        if (!$user_data || !isset($user_data['email'])) {
            $this->addFlash('error', 'Impossible de récupérer les données utilisateur');
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier le domaine
        $email = $user_data['email'];
        $domain = substr(strrchr($email, "@"), 1);
        $allowed_domains = ['decorpub.fr', 'technograv.fr', 'pimpanelo.fr', 'technoburo.fr', 'pimpanelo.com'];
        
        if (!in_array($domain, $allowed_domains)) {
            $this->addFlash('error', 'Domaine non autorisé: ' . $domain);
            return $this->redirectToRoute('app_login');
        }
        
        file_put_contents($log_file, "SUCCÈS ! Email: " . $email . " | Nom: " . ($user_data['name'] ?? 'N/A') . "\n", FILE_APPEND);
        
        // Créer ou mettre à jour l'utilisateur
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);
        
        if (!$user) {
            // Créer un nouveau utilisateur
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            // Définir nom et prenom à partir des données Google
            $user->setNom($user_data['family_name'] ?? 'Utilisateur');
            $user->setPrenom($user_data['given_name'] ?? 'Google');
            file_put_contents($log_file, "Nouvel utilisateur créé: " . $email . "\n", FILE_APPEND);
        } else {
            // Mettre à jour nom/prénom si pas déjà définis
            if (!$user->getNom() && isset($user_data['family_name'])) {
                $user->setNom($user_data['family_name']);
            }
            if (!$user->getPrenom() && isset($user_data['given_name'])) {
                $user->setPrenom($user_data['given_name']);
            }
            file_put_contents($log_file, "Utilisateur existant trouvé: " . $email . "\n", FILE_APPEND);
        }
        
        // Mettre à jour les informations Google
        $user->setGoogleId($user_data['id']);
        $user->setGoogleAccessToken($token_data['access_token']);
        if (isset($token_data['refresh_token'])) {
            $user->setGoogleRefreshToken($token_data['refresh_token']);
        }
        $user->setAvatar($user_data['picture'] ?? null);
        $user->setIsGoogleAccount(true);
        
        // Si c'est le super admin, lui donner les droits admin
        if ($user->isSuperAdmin()) {
            $user->setRoles(['ROLE_ADMIN']);
            file_put_contents($log_file, "Droits super admin accordés\n", FILE_APPEND);
        }
        
        $entityManager->persist($user);
        $entityManager->flush();
        
        // Récupérer la signature Gmail en arrière-plan (après flush)
        try {
            $signature = $gmailService->getUserSignature($user);
            if ($signature) {
                $user->setGmailSignature($signature);
                $entityManager->flush();
                file_put_contents($log_file, "Signature Gmail récupérée et sauvegardée\n", FILE_APPEND);
            } else {
                file_put_contents($log_file, "Aucune signature Gmail trouvée\n", FILE_APPEND);
            }
        } catch (\Exception $e) {
            file_put_contents($log_file, "Erreur lors de la récupération de la signature Gmail: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        
        // Connecter automatiquement l'utilisateur
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        
        file_put_contents($log_file, "Utilisateur connecté automatiquement\n", FILE_APPEND);
        
        $this->addFlash('success', 'Connexion Google réussie ! Bienvenue ' . ($user_data['given_name'] ?? $email));
        
        // Rediriger vers le dashboard
        return $this->redirectToRoute('app_home');
    }
}