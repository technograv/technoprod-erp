<?php

namespace App\Repository;

use App\Entity\SecteurZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecteurZone>
 */
class SecteurZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecteurZone::class);
    }

    public function findByCodePostal(string $codePostal): ?SecteurZone
    {
        return $this->createQueryBuilder('sz')
            ->andWhere('sz.codePostal = :codePostal')
            ->setParameter('codePostal', $codePostal)
            ->getQuery()
            ->getOneOrNullResult();
    }
}