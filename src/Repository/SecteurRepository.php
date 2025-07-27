<?php

namespace App\Repository;

use App\Entity\Secteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Secteur>
 */
class SecteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Secteur::class);
    }

    public function findByCommercial(int $commercialId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.commercial = :commercial')
            ->setParameter('commercial', $commercialId)
            ->andWhere('s.isActive = true')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }
}