<?php

namespace App\Repository;

use App\Entity\CommuneGeometryCache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion du cache des géométries des communes
 */
class CommuneGeometryCacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommuneGeometryCache::class);
    }

    /**
     * Récupère la géométrie d'une commune depuis le cache
     */
    public function findByCodeInsee(string $codeInsee): ?CommuneGeometryCache
    {
        return $this->findOneBy(['codeInsee' => $codeInsee, 'isValid' => true]);
    }

    /**
     * Récupère plusieurs géométries de communes en une seule requête
     */
    public function findByCodes(array $codesInsee): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.codeInsee IN (:codes)')
            ->andWhere('c.isValid = true')
            ->setParameter('codes', $codesInsee)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les géométries d'un EPCI
     */
    public function findByEpciCommunes(array $communesData): array
    {
        $codesInsee = array_map(fn($commune) => $commune['codeInseeCommune'], $communesData);
        
        return $this->createQueryBuilder('c')
            ->where('c.codeInsee IN (:codes)')
            ->andWhere('c.isValid = true')
            ->setParameter('codes', $codesInsee)
            ->indexBy('c', 'c.codeInsee')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les entrées expirées (plus de 30 jours)
     */
    public function findExpired(): array
    {
        $expiredDate = new \DateTimeImmutable('-30 days');
        
        return $this->createQueryBuilder('c')
            ->where('c.lastUpdated < :expiredDate')
            ->setParameter('expiredDate', $expiredDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les entrées invalides (erreurs)
     */
    public function findInvalid(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isValid = false')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques du cache
     */
    public function getCacheStats(): array
    {
        $qb = $this->createQueryBuilder('c');
        
        $total = $qb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $valid = $qb->select('COUNT(c.id)')
            ->where('c.isValid = true')
            ->getQuery()
            ->getSingleScalarResult();

        $expired = $qb->select('COUNT(c.id)')
            ->where('c.lastUpdated < :expiredDate')
            ->setParameter('expiredDate', new \DateTimeImmutable('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'valid' => (int) $valid,
            'invalid' => (int) ($total - $valid),
            'expired' => (int) $expired,
            'coverage_rate' => $total > 0 ? round(($valid / $total) * 100, 2) : 0
        ];
    }

    /**
     * Nettoie le cache (supprime les entrées expirées)
     */
    public function cleanExpiredEntries(): int
    {
        $expiredDate = new \DateTimeImmutable('-30 days');
        
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.lastUpdated < :expiredDate')
            ->setParameter('expiredDate', $expiredDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les communes manquantes pour un EPCI
     */
    public function findMissingCommunes(array $codesInsee): array
    {
        $cached = $this->createQueryBuilder('c')
            ->select('c.codeInsee')
            ->where('c.codeInsee IN (:codes)')
            ->andWhere('c.isValid = true')
            ->setParameter('codes', $codesInsee)
            ->getQuery()
            ->getArrayResult();

        $cachedCodes = array_column($cached, 'codeInsee');
        
        return array_diff($codesInsee, $cachedCodes);
    }
}