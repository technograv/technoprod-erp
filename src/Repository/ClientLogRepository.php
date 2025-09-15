<?php

namespace App\Repository;

use App\Entity\ClientLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientLog>
 */
class ClientLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientLog::class);
    }

    /**
     * Récupère les logs d'un client
     */
    public function findByClient($client, int $limit = 50): array
    {
        return $this->createQueryBuilder('cl')
            ->leftJoin('cl.user', 'u')
            ->addSelect('u')
            ->where('cl.client = :client')
            ->setParameter('client', $client)
            ->orderBy('cl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de logs pour un client
     */
    public function countByClient($client): int
    {
        return $this->createQueryBuilder('cl')
            ->select('COUNT(cl.id)')
            ->where('cl.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();
    }
}