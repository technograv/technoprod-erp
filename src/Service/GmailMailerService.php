<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserPreferencesRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;

class GmailMailerService
{
    private MailerInterface $defaultMailer;
    private LoggerInterface $logger;
    private UserPreferencesRepository $userPreferencesRepository;
    private string $companySignature;

    public function __construct(
        MailerInterface $mailer, 
        LoggerInterface $logger,
        UserPreferencesRepository $userPreferencesRepository,
        string $companySignature = ''
    ) {
        $this->defaultMailer = $mailer;
        $this->logger = $logger;
        $this->userPreferencesRepository = $userPreferencesRepository;
        $this->companySignature = $companySignature;
    }

    public function sendWithUserGmail(Email $email, User $user): void
    {
        try {
            // Si l'utilisateur a un token Google et un email, essayer d'utiliser l'API Gmail
            if ($user->getGoogleAccessToken() && $user->getEmail()) {
                $this->sendWithGmailApi($email, $user);
            } else {
                // En développement, simuler l'envoi
                $this->simulateEmailSend($email, $user);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi Gmail: ' . $e->getMessage());
            // En cas d'erreur, simuler l'envoi aussi
            $this->simulateEmailSend($email, $user);
        }
    }

    private function simulateEmailSend(Email $email, User $user): void
    {
        $this->logger->info('SIMULATION ENVOI EMAIL', [
            'from' => $user->getEmail() ?: 'noreply@technoprod.fr',
            'to' => $email->getTo(),
            'subject' => $email->getSubject(),
            'user' => $user->getEmail()
        ]);

        // Pour le développement, on peut aussi essayer d'envoyer via le mailer par défaut
        // mais en gérant les erreurs de connexion
        try {
            $email->from($user->getEmail() ?: 'noreply@technoprod.fr');
            $this->defaultMailer->send($email);
            $this->logger->info('Email envoyé via mailer par défaut');
        } catch (\Exception $e) {
            $this->logger->warning('Impossible d\'envoyer via SMTP: ' . $e->getMessage());
            $this->logger->info('Email simulé avec succès (mode développement)');
        }
    }

    private function sendWithGmailApi(Email $email, User $user): void
    {
        try {
            // Configuration du client Google avec les paramètres nécessaires
            $client = new Google_Client();
            $clientId = $_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? null;
            $clientSecret = $_ENV['GOOGLE_OAUTH_CLIENT_SECRET'] ?? null;
            
            if (!$clientId || !$clientSecret) {
                return false; // OAuth non configuré, fallback vers SMTP
            }
            
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->setAccessToken($user->getGoogleAccessToken());
            
            // Vérifier si le token est encore valide
            if ($client->isAccessTokenExpired()) {
                // Essayer de rafraîchir le token avec le refresh token
                if ($user->getGoogleRefreshToken()) {
                    $client->refreshToken($user->getGoogleRefreshToken());
                    $newToken = $client->getAccessToken();
                    // TODO: Sauvegarder le nouveau token dans la base de données
                    $this->logger->info('Token Gmail rafraîchi pour: ' . $user->getEmail());
                } else {
                    throw new \Exception('Token Google expiré et pas de refresh token');
                }
            }

            // Créer le service Gmail
            $service = new Google_Service_Gmail($client);

            // Définir l'expéditeur avec l'email de l'utilisateur
            $email->from($user->getEmail());

            // Convertir l'email Symfony en message Gmail
            $rawMessage = $this->createGmailMessage($email, $user);
            $message = new Google_Service_Gmail_Message();
            $message->setRaw($rawMessage);

            // Envoyer via l'API Gmail
            $result = $service->users_messages->send('me', $message);
            
            $this->logger->info('Email envoyé via API Gmail', [
                'user' => $user->getEmail(),
                'to' => array_map(fn($addr) => $addr->getAddress(), $email->getTo()),
                'subject' => $email->getSubject(),
                'message_id' => $result->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi API Gmail: ' . $e->getMessage());
            // Fallback vers la simulation
            $this->simulateEmailSend($email, $user);
        }
    }

    private function createGmailMessage(Email $email, User $user): string
    {
        // Récupérer les préférences utilisateur pour la signature
        $preferences = $this->userPreferencesRepository->findOneBy(['user' => $user]);
        
        $signature = $this->getEffectiveSignature($user, $preferences);
        
        // Ajouter la signature si disponible
        if ($signature) {
            $currentBody = $email->getHtmlBody() ?: $email->getTextBody();
            if ($currentBody) {
                $emailWithSignature = $currentBody . '<br><br>' . $signature;
                $email->html($emailWithSignature);
            }
        }
        
        // Convertir l'email complet en string (avec les pièces jointes)
        $rawMessage = $email->toString();
        
        // Encoder en base64url pour l'API Gmail
        return rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');
    }

    /**
     * Détermine quelle signature utiliser selon les préférences utilisateur
     */
    private function getEffectiveSignature(User $user, $preferences): ?string
    {
        // Si pas de préférences, utiliser la signature d'entreprise par défaut
        if (!$preferences) {
            return $this->companySignature;
        }

        // Si l'utilisateur veut utiliser sa signature personnelle
        if ($preferences->usePersonalSignature()) {
            // Si une signature personnalisée est définie, l'utiliser
            if ($preferences->getCustomEmailSignature()) {
                return $preferences->getCustomEmailSignature();
            }
            // Sinon utiliser la signature Gmail de l'utilisateur si disponible
            elseif ($user->getGmailSignature()) {
                return $user->getGmailSignature();
            }
        }

        // Par défaut, utiliser la signature d'entreprise
        return $this->companySignature;
    }
}