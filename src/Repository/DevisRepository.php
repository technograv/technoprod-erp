<?php

namespace App\Repository;

use App\Entity\Devis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Devis>
 */
class DevisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Devis::class);
    }

    public function findByCommercial(int $commercialId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.commercial = :commercial')
            ->setParameter('commercial', $commercialId)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.client = :client')
            ->setParameter('client', $clientId)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByNumero(string $numero): ?Devis
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.numeroDevis = :numero')
            ->setParameter('numero', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextId(): int
    {
        $qb = $this->createQueryBuilder('d');
        $qb->select('MAX(d.id) as maxId');
        $result = $qb->getQuery()->getSingleScalarResult();
        
        return $result ? $result + 1 : 1;
    }
}