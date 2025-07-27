<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;

class GmailService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Récupère la signature Gmail de l'utilisateur
     */
    public function getUserSignature(User $user): ?string
    {
        if (!$user->getGoogleAccessToken()) {
            $this->logger->warning('No Google access token for user', ['email' => $user->getEmail()]);
            return null;
        }

        try {
            // Appel à l'API Gmail pour récupérer les paramètres d'envoi
            $url = 'https://gmail.googleapis.com/gmail/v1/users/me/settings/sendAs';
            
            $headers = [
                'Authorization: Bearer ' . $user->getGoogleAccessToken(),
                'Accept: application/json'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $this->logger->error('cURL error when fetching Gmail signature', [
                    'error' => $curlError,
                    'user' => $user->getEmail()
                ]);
                return null;
            }

            if ($httpCode !== 200) {
                $this->logger->error('Gmail API error', [
                    'httpCode' => $httpCode,
                    'response' => $response,
                    'user' => $user->getEmail()
                ]);
                return null;
            }

            $data = json_decode($response, true);
            
            if (!$data || !isset($data['sendAs'])) {
                $this->logger->warning('No sendAs data in Gmail API response', [
                    'user' => $user->getEmail(),
                    'response' => $response
                ]);
                return null;
            }

            // Chercher la signature par défaut (celle de l'adresse principale)
            foreach ($data['sendAs'] as $sendAs) {
                if ($sendAs['sendAsEmail'] === $user->getEmail() && isset($sendAs['signature'])) {
                    $this->logger->info('Gmail signature retrieved successfully', [
                        'user' => $user->getEmail()
                    ]);
                    return $sendAs['signature'];
                }
            }

            $this->logger->info('No signature found for user', ['user' => $user->getEmail()]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Exception when fetching Gmail signature', [
                'error' => $e->getMessage(),
                'user' => $user->getEmail()
            ]);
            return null;
        }
    }

    /**
     * Rafraîchit le token d'accès Google si nécessaire
     */
    public function refreshAccessToken(User $user): bool
    {
        if (!$user->getGoogleRefreshToken()) {
            return false;
        }

        try {
            $postData = [
                'client_id' => $_ENV['GOOGLE_OAUTH_CLIENT_ID'],
                'client_secret' => $_ENV['GOOGLE_OAUTH_CLIENT_SECRET'],
                'refresh_token' => $user->getGoogleRefreshToken(),
                'grant_type' => 'refresh_token'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $tokenData = json_decode($response, true);
                if (isset($tokenData['access_token'])) {
                    $user->setGoogleAccessToken($tokenData['access_token']);
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing access token', [
                'error' => $e->getMessage(),
                'user' => $user->getEmail()
            ]);
            return false;
        }
    }
}