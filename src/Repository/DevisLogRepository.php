<?php

namespace App\Repository;

use App\Entity\DevisLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevisLog>
 */
class DevisLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevisLog::class);
    }

    /**
     * Find logs for a specific devis
     */
    public function findByDevis($devis): array
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('dl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent logs across all devis
     */
    public function findRecentLogs(int $limit = 50): array
    {
        return $this->createQueryBuilder('dl')
            ->orderBy('dl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by action type
     */
    public function findByAction(string $action): array
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.action = :action')
            ->setParameter('action', $action)
            ->orderBy('dl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by user
     */
    public function findByUser($user): array
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.user = :user')
            ->setParameter('user', $user)
            ->orderBy('dl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count logs for a specific devis
     */
    public function countByDevis($devis): int
    {
        return $this->createQueryBuilder('dl')
            ->select('COUNT(dl.id)')
            ->where('dl.devis = :devis')
            ->setParameter('devis', $devis)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find logs within a date range
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('dl')
            ->where('dl.createdAt >= :startDate')
            ->andWhere('dl.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('dl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(): array
    {
        $qb = $this->createQueryBuilder('dl');
        
        $result = $qb
            ->select('dl.action', 'COUNT(dl.id) as count')
            ->groupBy('dl.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['action']] = (int)$row['count'];
        }

        return $stats;
    }
}
