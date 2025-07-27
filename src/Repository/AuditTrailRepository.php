<?php

namespace App\Repository;

use App\Entity\AuditTrail;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditTrail>
 */
class AuditTrailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditTrail::class);
    }

    /**
     * Trouve les audits d'une entité spécifique
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('at')
            ->where('at.entityType = :entityType')
            ->andWhere('at.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('at.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les audits d'un utilisateur
     */
    public function findByUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('at')
            ->where('at.user = :user')
            ->setParameter('user', $user)
            ->orderBy('at.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les audits par action
     */
    public function findByAction(string $action, int $limit = 100): array
    {
        return $this->createQueryBuilder('at')
            ->where('at.action = :action')
            ->setParameter('action', $action)
            ->orderBy('at.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'audit par période
     */
    public function getAuditStatsByPeriod(\DateTime $dateFrom, \DateTime $dateTo): array
    {
        return $this->createQueryBuilder('at')
            ->select([
                'at.action',
                'COUNT(at.id) as count',
                'COUNT(DISTINCT at.user) as unique_users',
                'COUNT(DISTINCT at.entityType) as entity_types'
            ])
            ->where('at.timestamp BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->groupBy('at.action')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les activités suspectes
     */
    public function findSuspiciousActivities(): array
    {
        // Activités en dehors des heures normales
        $suspiciousHours = $this->createQueryBuilder('at')
            ->where('HOUR(at.timestamp) < 6 OR HOUR(at.timestamp) > 22')
            ->andWhere('at.timestamp >= :since')
            ->setParameter('since', new \DateTime('-7 days'))
            ->orderBy('at.timestamp', 'DESC')
            ->getQuery()
            ->getResult();

        // Multiples actions DELETE en peu de temps
        $bulkDeletes = $this->createQueryBuilder('at')
            ->select('at.user, COUNT(at.id) as delete_count')
            ->where('at.action = :action')
            ->andWhere('at.timestamp >= :since')
            ->setParameter('action', 'DELETE')
            ->setParameter('since', new \DateTime('-1 hour'))
            ->groupBy('at.user')
            ->having('delete_count > 5')
            ->getQuery()
            ->getResult();

        return [
            'out_of_hours' => $suspiciousHours,
            'bulk_deletes' => $bulkDeletes
        ];
    }

    /**
     * Trouve le dernier hash d'audit pour chaînage
     */
    public function findLastAuditHash(): ?string
    {
        $result = $this->createQueryBuilder('at')
            ->select('at.recordHash')
            ->orderBy('at.timestamp', 'DESC')
            ->addOrderBy('at.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['recordHash'] ?? null;
    }

    /**
     * Trouve le dernier hash d'audit excluant un ID spécifique (pour éviter l'auto-référencement)
     */
    public function findLastAuditHashExcluding(?int $excludeId): ?string
    {
        $qb = $this->createQueryBuilder('at')
            ->select('at.recordHash')
            ->orderBy('at.timestamp', 'DESC')
            ->addOrderBy('at.id', 'DESC')
            ->setMaxResults(1);

        if ($excludeId !== null) {
            $qb->andWhere('at.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        return $result['recordHash'] ?? null;
    }

    /**
     * Vérifie la chaîne d'audit
     */
    public function verifyAuditChain(int $limit = 1000): array
    {
        $audits = $this->createQueryBuilder('at')
            ->orderBy('at.timestamp', 'ASC')
            ->addOrderBy('at.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $errors = [];
        $previousHash = null;

        foreach ($audits as $key => $audit) {
            // Pour le premier enregistrement, previousHash doit être null
            if ($key === 0) {
                if ($audit->getPreviousRecordHash() !== null) {
                    $errors[] = [
                        'audit_id' => $audit->getId(),
                        'timestamp' => $audit->getTimestamp(),
                        'error' => 'Le premier enregistrement d\'audit devrait avoir un previousRecordHash null',
                        'expected_previous_hash' => null,
                        'actual_previous_hash' => $audit->getPreviousRecordHash()
                    ];
                }
            } else {
                // Pour les enregistrements suivants, vérifier le chaînage
                if ($previousHash !== $audit->getPreviousRecordHash()) {
                    $errors[] = [
                        'audit_id' => $audit->getId(),
                        'timestamp' => $audit->getTimestamp(),
                        'error' => 'Rupture de chaînage audit détectée',
                        'expected_previous_hash' => $previousHash,
                        'actual_previous_hash' => $audit->getPreviousRecordHash()
                    ];
                }
            }
            $previousHash = $audit->getRecordHash();
        }

        return [
            'valid' => empty($errors),
            'total_records' => count($audits),
            'errors' => $errors
        ];
    }

    /**
     * Recherche avancée dans les audits
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('at')
            ->leftJoin('at.user', 'u');

        if (isset($criteria['entity_type'])) {
            $qb->andWhere('at.entityType = :entityType')
               ->setParameter('entityType', $criteria['entity_type']);
        }

        if (isset($criteria['action'])) {
            $qb->andWhere('at.action = :action')
               ->setParameter('action', $criteria['action']);
        }

        if (isset($criteria['user_id'])) {
            $qb->andWhere('at.user = :userId')
               ->setParameter('userId', $criteria['user_id']);
        }

        if (isset($criteria['date_from'])) {
            $qb->andWhere('at.timestamp >= :dateFrom')
               ->setParameter('dateFrom', $criteria['date_from']);
        }

        if (isset($criteria['date_to'])) {
            $qb->andWhere('at.timestamp <= :dateTo')
               ->setParameter('dateTo', $criteria['date_to']);
        }

        if (isset($criteria['ip_address'])) {
            $qb->andWhere('at.ipAddress = :ipAddress')
               ->setParameter('ipAddress', $criteria['ip_address']);
        }

        if (isset($criteria['criticality'])) {
            $actions = match($criteria['criticality']) {
                'HIGH' => ['DELETE', 'ADMIN_UPDATE'],
                'MEDIUM' => ['UPDATE', 'VALIDATE', 'REJECT'],
                'LOW' => ['CREATE', 'VIEW', 'EXPORT'],
                default => []
            };
            
            if (!empty($actions)) {
                $qb->andWhere('at.action IN (:actions)')
                   ->setParameter('actions', $actions);
            }
        }

        return $qb->orderBy('at.timestamp', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Statistiques utilisateur par période
     */
    public function getUserActivityStats(\DateTime $dateFrom, \DateTime $dateTo): array
    {
        return $this->createQueryBuilder('at')
            ->select([
                'u.id as user_id',
                'u.nom',
                'u.prenom',
                'COUNT(at.id) as total_actions',
                'COUNT(DISTINCT at.entityType) as entity_types_accessed',
                'MAX(at.timestamp) as last_activity'
            ])
            ->leftJoin('at.user', 'u')
            ->where('at.timestamp BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->groupBy('u.id')
            ->orderBy('total_actions', 'DESC')
            ->getQuery()
            ->getResult();
    }
}