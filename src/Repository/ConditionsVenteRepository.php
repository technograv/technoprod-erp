<?php

namespace App\Repository;

use App\Entity\ConditionsVente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConditionsVente>
 */
class ConditionsVenteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConditionsVente::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySociete($societeId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.societe = :societe')
            ->setParameter('societe', $societeId)
            ->orderBy('c.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
