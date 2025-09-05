<?php

namespace App\Repository;

use App\Entity\DevisVersion;
use App\Entity\Devis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevisVersion>
 */
class DevisVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevisVersion::class);
    }

    /**
     * Récupère toutes les versions d'un devis ordonnées par numéro de version
     */
    public function findVersionsByDevis(Devis $devis): array
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('dv.versionNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère la dernière version d'un devis
     */
    public function findLatestVersion(Devis $devis): ?DevisVersion
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('dv.versionNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le prochain numéro de version pour un devis
     */
    public function getNextVersionNumber(Devis $devis): int
    {
        $lastVersion = $this->findLatestVersion($devis);
        return $lastVersion ? $lastVersion->getVersionNumber() + 1 : 1;
    }

    /**
     * Récupère une version spécifique d'un devis
     */
    public function findVersionByNumber(Devis $devis, int $versionNumber): ?DevisVersion
    {
        return $this->createQueryBuilder('dv')
            ->where('dv.devis = :devis')
            ->andWhere('dv.versionNumber = :versionNumber')
            ->setParameter('devis', $devis)
            ->setParameter('versionNumber', $versionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre de versions d'un devis
     */
    public function countVersionsForDevis(Devis $devis): int
    {
        return $this->createQueryBuilder('dv')
            ->select('COUNT(dv.id)')
            ->where('dv.devis = :devis')
            ->setParameter('devis', $devis)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les versions récentes pour un utilisateur (dashboard)
     */
    public function findRecentVersionsByUser(\App\Entity\User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('dv')
            ->join('dv.devis', 'd')
            ->where('d.commercial = :user')
            ->setParameter('user', $user)
            ->orderBy('dv.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des versions par période
     */
    public function getVersionStatsForPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('dv')
            ->select('COUNT(dv.id) as totalVersions')
            ->addSelect('COUNT(DISTINCT dv.devis) as uniqueDevis')
            ->addSelect('AVG(dv.versionNumber) as avgVersionNumber')
            ->where('dv.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(DevisVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DevisVersion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}