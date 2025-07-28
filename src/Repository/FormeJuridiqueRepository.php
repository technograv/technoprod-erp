<?php

namespace App\Repository;

use App\Entity\FormeJuridique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormeJuridique>
 */
class FormeJuridiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormeJuridique::class);
    }

    /**
     * Récupère toutes les formes juridiques actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère la forme juridique "Particulier"
     */
    public function findParticulier(): ?FormeJuridique
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.nom = :nom')
            ->setParameter('nom', 'Particulier')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
