<?php

namespace App\Service;

use App\Entity\Consent;
use App\Entity\User;
use App\Repository\ConsentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service de gestion des consentements GDPR
 * Conforme au Règlement Général sur la Protection des Données
 */
class ConsentService
{
    // Objectifs de traitement des données définis selon GDPR
    public const PURPOSE_ACCOUNT_MANAGEMENT = 'account_management';
    public const PURPOSE_COMMERCIAL_COMMUNICATION = 'commercial_communication';
    public const PURPOSE_ANALYTICS = 'analytics';
    public const PURPOSE_MARKETING = 'marketing';
    public const PURPOSE_COOKIES_FUNCTIONAL = 'cookies_functional';
    public const PURPOSE_COOKIES_ANALYTICS = 'cookies_analytics';
    public const PURPOSE_DATA_EXPORT = 'data_export';
    public const PURPOSE_NEWSLETTER = 'newsletter';

    // Bases légales selon Article 6 GDPR
    public const LEGAL_BASIS_CONSENT = 'Article 6(1)(a) - Consentement';
    public const LEGAL_BASIS_CONTRACT = 'Article 6(1)(b) - Exécution contrat';
    public const LEGAL_BASIS_LEGAL_OBLIGATION = 'Article 6(1)(c) - Obligation légale';
    public const LEGAL_BASIS_VITAL_INTERESTS = 'Article 6(1)(d) - Intérêts vitaux';
    public const LEGAL_BASIS_PUBLIC_TASK = 'Article 6(1)(e) - Mission service public';
    public const LEGAL_BASIS_LEGITIMATE_INTERESTS = 'Article 6(1)(f) - Intérêts légitimes';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConsentRepository $consentRepository,
        private LoggerInterface $logger,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Collecte le consentement d'un utilisateur pour un objectif spécifique
     */
    public function grantConsent(
        User $user, 
        string $purpose, 
        string $legalBasis = self::LEGAL_BASIS_CONSENT,
        bool $logAction = true
    ): Consent {
        // Retirer le consentement précédent s'il existe
        $existingConsent = $this->consentRepository->findCurrentConsent($user, $purpose);
        if ($existingConsent && $existingConsent->isActive()) {
            $existingConsent->setGranted(false);
            $existingConsent->setWithdrawnAt(new \DateTimeImmutable());
        }

        // Créer nouveau consentement
        $consent = new Consent();
        $consent->setUser($user);
        $consent->setPurpose($purpose);
        $consent->setGranted(true);
        $consent->setLegalBasis($legalBasis);
        
        // Capturer informations contextuelles pour preuve
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $consent->setIpAddress($request->getClientIp());
            $consent->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($consent);
        $this->entityManager->flush();

        if ($logAction) {
            $this->logger->info('Consentement GDPR accordé', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'purpose' => $purpose,
                'legal_basis' => $legalBasis,
                'ip_address' => $consent->getIpAddress(),
                'consent_id' => $consent->getId()
            ]);
        }

        return $consent;
    }

    /**
     * Retire le consentement d'un utilisateur (droit de retrait)
     */
    public function withdrawConsent(User $user, string $purpose): bool
    {
        $consent = $this->consentRepository->findCurrentConsent($user, $purpose);
        
        if (!$consent || !$consent->isActive()) {
            return false;
        }

        $consent->setGranted(false);
        $consent->setWithdrawnAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();

        $this->logger->info('Consentement GDPR retiré', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'purpose' => $purpose,
            'consent_id' => $consent->getId()
        ]);

        return true;
    }

    /**
     * Vérifie si un utilisateur a donné son consentement pour un objectif
     */
    public function hasConsent(User $user, string $purpose): bool
    {
        return $this->consentRepository->hasActiveConsent($user, $purpose);
    }

    /**
     * Récupère tous les consentements actifs d'un utilisateur
     */
    public function getUserConsents(User $user): array
    {
        return $this->consentRepository->findActiveConsents($user);
    }

    /**
     * Exporte toutes les données de consentement d'un utilisateur (portabilité GDPR)
     */
    public function exportUserConsentData(User $user): array
    {
        $consents = $this->consentRepository->findConsentHistory($user);
        
        $exportData = [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'export_date' => (new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601),
            'consents' => []
        ];

        foreach ($consents as $consent) {
            $exportData['consents'][] = [
                'purpose' => $consent->getPurpose(),
                'granted' => $consent->isGranted(),
                'granted_at' => $consent->getGrantedAt()?->format(\DateTimeInterface::ISO8601),
                'withdrawn_at' => $consent->getWithdrawnAt()?->format(\DateTimeInterface::ISO8601),
                'legal_basis' => $consent->getLegalBasis(),
                'is_active' => $consent->isActive(),
                'ip_address' => $consent->getIpAddress()
            ];
        }

        $this->logger->info('Export données consentement GDPR', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'total_consents' => count($exportData['consents'])
        ]);

        return $exportData;
    }

    /**
     * Supprime toutes les données de consentement d'un utilisateur (droit à l'oubli)
     */
    public function deleteUserConsentData(User $user): int
    {
        $deletedCount = $this->consentRepository->deleteUserConsents($user);

        $this->logger->warning('Suppression données consentement GDPR (droit à l\'oubli)', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'deleted_consents' => $deletedCount
        ]);

        return $deletedCount;
    }

    /**
     * Initialise les consentements par défaut pour un nouvel utilisateur
     */
    public function initializeDefaultConsents(User $user): void
    {
        // Consentements obligatoires (base légale: contrat)
        $this->grantConsent(
            $user, 
            self::PURPOSE_ACCOUNT_MANAGEMENT, 
            self::LEGAL_BASIS_CONTRACT,
            false
        );

        // Cookies fonctionnels (base légale: intérêts légitimes)
        $this->grantConsent(
            $user, 
            self::PURPOSE_COOKIES_FUNCTIONAL, 
            self::LEGAL_BASIS_LEGITIMATE_INTERESTS,
            false
        );

        $this->logger->info('Consentements par défaut initialisés', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail()
        ]);
    }

    /**
     * Obtient la liste des objectifs de traitement avec descriptions
     */
    public function getPurposeDescriptions(): array
    {
        return [
            self::PURPOSE_ACCOUNT_MANAGEMENT => [
                'title' => 'Gestion du compte utilisateur',
                'description' => 'Traitement nécessaire pour la création, gestion et sécurisation de votre compte utilisateur.',
                'required' => true,
                'legal_basis' => self::LEGAL_BASIS_CONTRACT,
                'category' => 'Obligatoire'
            ],
            self::PURPOSE_COMMERCIAL_COMMUNICATION => [
                'title' => 'Communications commerciales',
                'description' => 'Envoi d\'informations sur nos produits, services et offres commerciales.',
                'required' => false,
                'legal_basis' => self::LEGAL_BASIS_CONSENT,
                'category' => 'Marketing'
            ],
            self::PURPOSE_ANALYTICS => [
                'title' => 'Analyses et statistiques',
                'description' => 'Analyse de l\'utilisation de l\'application pour améliorer nos services.',
                'required' => false,
                'legal_basis' => self::LEGAL_BASIS_LEGITIMATE_INTERESTS,
                'category' => 'Fonctionnel'
            ],
            self::PURPOSE_MARKETING => [
                'title' => 'Marketing personnalisé',
                'description' => 'Personnalisation des contenus et publicités selon vos préférences.',
                'required' => false,
                'legal_basis' => self::LEGAL_BASIS_CONSENT,
                'category' => 'Marketing'
            ],
            self::PURPOSE_COOKIES_FUNCTIONAL => [
                'title' => 'Cookies fonctionnels',
                'description' => 'Cookies nécessaires au bon fonctionnement de l\'application.',
                'required' => true,
                'legal_basis' => self::LEGAL_BASIS_LEGITIMATE_INTERESTS,
                'category' => 'Obligatoire'
            ],
            self::PURPOSE_COOKIES_ANALYTICS => [
                'title' => 'Cookies d\'analyse',
                'description' => 'Cookies pour analyser l\'utilisation et améliorer l\'expérience utilisateur.',
                'required' => false,
                'legal_basis' => self::LEGAL_BASIS_CONSENT,
                'category' => 'Fonctionnel'
            ],
            self::PURPOSE_NEWSLETTER => [
                'title' => 'Newsletter',
                'description' => 'Envoi de notre newsletter avec actualités et nouveautés.',
                'required' => false,
                'legal_basis' => self::LEGAL_BASIS_CONSENT,
                'category' => 'Marketing'
            ]
        ];
    }

    /**
     * Vérifie les consentements expirant et envoie des rappels
     */
    public function checkExpiringConsents(): int
    {
        $expiringConsents = $this->consentRepository->findExpiringConsents(30);
        
        foreach ($expiringConsents as $consent) {
            // TODO: Implémenter notification utilisateur pour renouvellement
            $this->logger->info('Consentement expirant détecté', [
                'user_id' => $consent->getUser()->getId(),
                'purpose' => $consent->getPurpose(),
                'granted_at' => $consent->getGrantedAt()->format('Y-m-d')
            ]);
        }
        
        return count($expiringConsents);
    }

    /**
     * Obtient les statistiques de consentement pour le dashboard admin
     */
    public function getConsentStatistics(): array
    {
        $stats = $this->consentRepository->getConsentStatistics();
        
        return [
            'by_purpose' => $stats,
            'total_users_with_consents' => $this->entityManager
                ->createQuery('SELECT COUNT(DISTINCT c.user) FROM App\Entity\Consent c')
                ->getSingleScalarResult(),
            'total_active_consents' => $this->entityManager
                ->createQuery('SELECT COUNT(c) FROM App\Entity\Consent c WHERE c.granted = true AND c.withdrawnAt IS NULL')
                ->getSingleScalarResult(),
            'total_withdrawn_consents' => $this->entityManager
                ->createQuery('SELECT COUNT(c) FROM App\Entity\Consent c WHERE c.granted = false OR c.withdrawnAt IS NOT NULL')
                ->getSingleScalarResult()
        ];
    }
}