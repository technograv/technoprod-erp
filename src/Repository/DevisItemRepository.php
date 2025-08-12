<?php

namespace App\Repository;

use App\Entity\DevisItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevisItem>
 */
class DevisItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevisItem::class);
    }

    public function findByDevis(int $devisId): array
    {
        return $this->createQueryBuilder('di')
            ->andWhere('di.devis = :devis')
            ->setParameter('devis', $devisId)
            ->orderBy('di.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextOrdreAffichage(int $devisId): int
    {
        $qb = $this->createQueryBuilder('di');
        $qb->select('MAX(di.ordreAffichage) as maxOrdre')
           ->andWhere('di.devis = :devis')
           ->setParameter('devis', $devisId);
        
        $result = $qb->getQuery()->getSingleScalarResult();
        
        return $result ? $result + 1 : 1;
    }
}