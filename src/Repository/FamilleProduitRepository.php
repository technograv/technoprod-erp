<?php

namespace App\Repository;

use App\Entity\FamilleProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FamilleProduit>
 */
class FamilleProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilleProduit::class);
    }

    /**
     * Retourne toutes les familles racines (sans parent) triées par ordre
     */
    public function findRacines(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.parent IS NULL')
            ->andWhere('f.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les enfants d'une famille donnée
     */
    public function findEnfants(FamilleProduit $parent): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.parent = :parent')
            ->andWhere('f.actif = :actif')
            ->setParameter('parent', $parent)
            ->setParameter('actif', true)
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne l'arborescence complète des familles (pour navigation)
     */
    public function findArborescenceComplete(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
