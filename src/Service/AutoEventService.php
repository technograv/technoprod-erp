<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Commande;
use App\Entity\Facture;
use App\Entity\UserPreferences;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AutoEventService
{
    public function __construct(
        private GoogleCalendarService $calendarService,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $params
    ) {}

    /**
     * Crée un événement de livraison lors de la définition d'une date de livraison
     * L'événement est créé dans l'agenda partagé de l'administrateur (calendrier "livraisons")
     * Supprime l'ancien événement s'il existe avant d'en créer un nouveau
     */
    public function createLivraisonEvent(Commande $commande): ?string
    {
        $dateReference = $commande->getDateLivraisonReelle() ?? $commande->getDateLivraisonPrevue();

        if (!$dateReference || !$commande->getCommercial()) {
            return null;
        }

        // Récupérer l'utilisateur administrateur pour utiliser son calendrier partagé
        $adminUser = $this->getAdminUser();
        if (!$adminUser) {
            $this->logger->warning('Aucun administrateur trouvé pour créer l\'événement de livraison', [
                'commande' => $commande->getNumeroCommande()
            ]);
            return null;
        }

        $preferences = $this->getUserPreferences($adminUser);

        if (!$preferences || !$adminUser->isGoogleAccount() || !$adminUser->getGoogleAccessToken()) {
            $this->logger->warning('L\'administrateur n\'a pas de compte Google configuré', [
                'admin_id' => $adminUser->getId(),
                'commande' => $commande->getNumeroCommande()
            ]);
            return null;
        }

        $calendarId = $preferences->getWriteCalendarForType('livraisons');
        if (!$calendarId || $calendarId === 'primary') {
            $this->logger->warning('Aucun calendrier de livraison configuré pour l\'administrateur', [
                'admin_id' => $adminUser->getId(),
                'commande' => $commande->getNumeroCommande()
            ]);
            return null;
        }

        // Supprimer l'ancien événement s'il existe
        $oldEventId = $commande->getGoogleCalendarEventId();
        if ($oldEventId) {
            $this->calendarService->deleteEvent($adminUser, $calendarId, $oldEventId);
            $this->logger->info('Ancien événement de livraison supprimé', [
                'commande_id' => $commande->getId(),
                'old_event_id' => $oldEventId
            ]);
        }
        
        $isRealDelivery = $commande->getDateLivraisonReelle() !== null;
        $client = $commande->getClient();
        $commercial = $commande->getCommercial();
        $commercialInfo = $commercial->getPrenom() . ' ' . $commercial->getNom();

        // Titre : nom du client
        $eventTitle = $client->getNomComplet();

        // Si pas d'heure définie, mettre 9h par défaut
        $startDateTime = clone $dateReference;
        if ($startDateTime->format('H:i') === '00:00') {
            $startDateTime->setTime(9, 0);
        }

        // Durée de 2 heures
        $endDateTime = clone $startDateTime;
        $endDateTime->modify('+2 hours');

        // Générer le lien vers la commande
        $baseUrl = $this->params->get('app.base_url');
        $commandePath = $this->urlGenerator->generate('app_commande_show',
            ['id' => $commande->getId()]
        );
        $commandeUrl = $baseUrl . $commandePath;

        $eventData = [
            'summary' => $eventTitle,
            'description' => "Commande: {$commande->getNumeroCommande()}\n" .
                           "Client: {$client->getNomComplet()}\n" .
                           "Commercial: {$commercialInfo}\n" .
                           "Montant: {$commande->getTotalTtc()}€\n" .
                           "Statut: " . ($isRealDelivery ? 'Livraison réelle' : 'Livraison prévue') . "\n\n" .
                           "🔗 Voir la commande: {$commandeUrl}",
            'start' => [
                'dateTime' => $startDateTime->format('c'),
                'timeZone' => 'Europe/Paris'
            ],
            'end' => [
                'dateTime' => $endDateTime->format('c'),
                'timeZone' => 'Europe/Paris'
            ],
            'source' => [
                'title' => 'Voir la commande',
                'url' => $commandeUrl
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

        // Ajouter l'adresse de livraison si disponible (depuis le client)
        $adresse = $client->getAdresseLivraison();
        if ($adresse) {
            $eventData['location'] = implode(', ', array_filter([
                $adresse->getAdresse(),
                $adresse->getCodePostal(),
                $adresse->getVille()
            ]));
        }

        try {
            // Créer l'événement dans le calendrier de l'administrateur (agenda partagé)
            $eventId = $this->calendarService->createEvent($adminUser, $calendarId, $eventData);

            // Stocker l'ID de l'événement dans la commande
            if ($eventId) {
                $commande->setGoogleCalendarEventId($eventId);
                $this->entityManager->flush();
            }

            $this->logger->info('Événement livraison créé automatiquement dans l\'agenda administrateur', [
                'commande_id' => $commande->getId(),
                'commande_numero' => $commande->getNumeroCommande(),
                'commercial_id' => $commercial->getId(),
                'commercial_nom' => $commercialInfo,
                'admin_id' => $adminUser->getId(),
                'calendar_id' => $calendarId,
                'event_id' => $eventId
            ]);

            return $eventId;
        } catch (\Exception $e) {
            $this->logger->error('Erreur création événement livraison automatique', [
                'commande_id' => $commande->getId(),
                'commercial_id' => $commercial->getId(),
                'admin_id' => $adminUser->getId(),
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

    /**
     * Récupère le premier utilisateur administrateur actif avec compte Google et calendrier livraisons configuré
     */
    private function getAdminUser(): ?User
    {
        // Récupérer tous les utilisateurs actifs avec compte Google
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->andWhere('u.isGoogleAccount = :googleAccount')
            ->andWhere('u.googleAccessToken IS NOT NULL')
            ->setParameter('active', true)
            ->setParameter('googleAccount', true)
            ->getQuery()
            ->getResult();

        // Filtrer pour trouver un administrateur avec calendrier livraisons configuré
        foreach ($users as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                $preferences = $this->getUserPreferences($user);
                if ($preferences) {
                    $calendarId = $preferences->getWriteCalendarForType('livraisons');
                    // Vérifier qu'un calendrier spécifique est configuré (pas juste 'primary')
                    if ($calendarId && $calendarId !== 'primary') {
                        return $user;
                    }
                }
            }
        }

        return null;
    }
}