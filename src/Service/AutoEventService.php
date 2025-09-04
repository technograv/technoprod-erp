<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Commande;
use App\Entity\Facture;
use App\Entity\UserPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AutoEventService
{
    public function __construct(
        private GoogleCalendarService $calendarService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée un événement de livraison lors de la définition d'une date de livraison
     */
    public function createLivraisonEvent(Commande $commande): ?string
    {
        $dateReference = $commande->getDateLivraisonReelle() ?? $commande->getDateLivraisonPrevue();
        
        if (!$dateReference || !$commande->getCommercial()) {
            return null;
        }

        $user = $commande->getCommercial();
        $preferences = $this->getUserPreferences($user);
        
        if (!$preferences || !$user->isGoogleAccount() || !$user->getGoogleAccessToken()) {
            return null;
        }

        $calendarId = $preferences->getWriteCalendarForType('livraisons');
        if (!$calendarId || $calendarId === 'primary') {
            $this->logger->warning('Aucun calendrier de livraison configuré pour l\'utilisateur', [
                'user_id' => $user->getId(),
                'commande' => $commande->getNumeroCommande()
            ]);
            return null;
        }
        
        $isRealDelivery = $commande->getDateLivraisonReelle() !== null;
        $eventTitle = $isRealDelivery ? "Livraison réelle - {$commande->getNumeroCommande()}" : "Livraison prévue - {$commande->getNumeroCommande()}";
        
        $eventData = [
            'summary' => $eventTitle,
            'description' => "Livraison de la commande {$commande->getNumeroCommande()}\n" .
                           "Client: {$commande->getClient()->getNomComplet()}\n" .
                           "Montant: {$commande->getTotalTtc()}€\n" .
                           "Statut: " . ($isRealDelivery ? 'Livraison réelle' : 'Livraison prévue'),
            'start' => [
                'dateTime' => $dateReference->format('c'),
                'timeZone' => 'Europe/Paris'
            ],
            'end' => [
                'dateTime' => (clone $dateReference)->modify('+2 hours')->format('c'),
                'timeZone' => 'Europe/Paris'
            ],
            'attendees' => [],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 24 * 60], // 1 jour avant
                    ['method' => 'popup', 'minutes' => 60] // 1 heure avant
                ]
            ]
        ];

        // Ajouter l'adresse de livraison si disponible
        if ($commande->getAdresseLivraison()) {
            $adresse = $commande->getAdresseLivraison();
            $eventData['location'] = implode(', ', array_filter([
                $adresse->getAdresse(),
                $adresse->getCodePostal(),
                $adresse->getVille()
            ]));
        }

        try {
            $eventId = $this->calendarService->createEvent($user, $calendarId, $eventData);
            
            $this->logger->info('Événement livraison créé automatiquement', [
                'commande_id' => $commande->getId(),
                'commande_numero' => $commande->getNumeroCommande(),
                'user_id' => $user->getId(),
                'calendar_id' => $calendarId,
                'event_id' => $eventId
            ]);

            return $eventId;
        } catch (\Exception $e) {
            $this->logger->error('Erreur création événement livraison automatique', [
                'commande_id' => $commande->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crée un événement de facturation lors de la création d'une facture
     */
    public function createFacturationEvent(Facture $facture): ?string
    {
        if (!$facture->getDateEcheance() || !$facture->getCommercial()) {
            return null;
        }

        $user = $facture->getCommercial();
        $preferences = $this->getUserPreferences($user);
        
        if (!$preferences || !$user->isGoogleAccount() || !$user->getGoogleAccessToken()) {
            return null;
        }

        $calendarId = $preferences->getWriteCalendarForType('facturations');
        
        $eventData = [
            'summary' => "Échéance - {$facture->getNumeroFacture()}",
            'description' => "Échéance de paiement pour la facture {$facture->getNumeroFacture()}\n" .
                           "Client: {$facture->getClient()->getNomComplet()}\n" .
                           "Montant: {$facture->getTotalTtc()}€",
            'start' => [
                'date' => $facture->getDateEcheance()->format('Y-m-d'),
                'timeZone' => 'Europe/Paris'
            ],
            'end' => [
                'date' => $facture->getDateEcheance()->format('Y-m-d'),
                'timeZone' => 'Europe/Paris'
            ],
            'attendees' => [],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 3 * 24 * 60], // 3 jours avant
                    ['method' => 'popup', 'minutes' => 24 * 60] // 1 jour avant
                ]
            ]
        ];

        try {
            $eventId = $this->calendarService->createEvent($user, $calendarId, $eventData);
            
            $this->logger->info('Événement facturation créé automatiquement', [
                'facture_id' => $facture->getId(),
                'facture_numero' => $facture->getNumeroFacture(),
                'user_id' => $user->getId(),
                'calendar_id' => $calendarId,
                'event_id' => $eventId
            ]);

            return $eventId;
        } catch (\Exception $e) {
            $this->logger->error('Erreur création événement facturation automatique', [
                'facture_id' => $facture->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Récupère ou crée les préférences utilisateur
     */
    private function getUserPreferences(User $user): ?UserPreferences
    {
        return $this->entityManager->getRepository(UserPreferences::class)->findOneBy(['user' => $user]);
    }
}