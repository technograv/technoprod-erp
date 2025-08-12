<?php

namespace App\Repository;

use App\Entity\Consent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consent>
 */
class ConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consent::class);
    }

    /**
     * Trouve le consentement actuel d'un utilisateur pour un objectif donné
     */
    public function findCurrentConsent(User $user, string $purpose): ?Consent
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.purpose = :purpose')
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un utilisateur a donné son consentement pour un objectif
     */
    public function hasActiveConsent(User $user, string $purpose): bool
    {
        $consent = $this->findCurrentConsent($user, $purpose);
        return $consent && $consent->isActive();
    }

    /**
     * Récupère tous les consentements actifs d'un utilisateur
     */
    public function findActiveConsents(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.granted = true')
            ->andWhere('c.withdrawnAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'historique des consentements d'un utilisateur
     */
    public function findConsentHistory(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques de consentement par objectif
     */
    public function getConsentStatistics(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('c.purpose', 'COUNT(c.id) as total_consents')
            ->addSelect('SUM(CASE WHEN c.granted = true AND c.withdrawnAt IS NULL THEN 1 ELSE 0 END) as active_consents')
            ->addSelect('SUM(CASE WHEN c.granted = false OR c.withdrawnAt IS NOT NULL THEN 1 ELSE 0 END) as withdrawn_consents')
            ->groupBy('c.purpose')
            ->orderBy('total_consents', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Trouve les consentements qui expirent bientôt (pour rappel de renouvellement)
     */
    public function findExpiringConsents(int $daysBeforeExpiry = 30): array
    {
        $expiryDate = new \DateTimeImmutable("+{$daysBeforeExpiry} days");
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.granted = true')
            ->andWhere('c.withdrawnAt IS NULL')
            ->andWhere('c.grantedAt < :expiryDate')
            ->setParameter('expiryDate', $expiryDate->modify('-2 years')) // Consentement GDPR expire après 2 ans
            ->orderBy('c.grantedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime tous les consentements d'un utilisateur (pour droit à l'oubli)
     */
    public function deleteUserConsents(User $user): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Archive les consentements anciens pour conformité légale
     */
    public function archiveOldConsents(\DateTimeImmutable $beforeDate): int
    {
        // Marquer comme archivé plutôt que supprimer pour traçabilité légale
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.legalBasis', ':archived')
            ->andWhere('c.createdAt < :beforeDate')
            ->andWhere('c.legalBasis != :archived')
            ->setParameter('beforeDate', $beforeDate)
            ->setParameter('archived', 'ARCHIVED')
            ->getQuery()
            ->execute();
    }
}