<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Trouve les produits actifs
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('p.designation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les produits par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.type = :type')
            ->andWhere('p.actif = :actif')
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->orderBy('p.designation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de produits par terme
     */
    public function search(string $terme): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.actif = :actif')
            ->andWhere('
                p.designation LIKE :terme OR 
                p.reference LIKE :terme OR 
                p.description LIKE :terme
            ')
            ->setParameter('actif', true)
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('p.designation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des produits
     */
    public function getStatistiques(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('
                COUNT(p.id) as total,
                SUM(CASE WHEN p.actif = true THEN 1 ELSE 0 END) as actifs,
                SUM(CASE WHEN p.type = \'produit\' THEN 1 ELSE 0 END) as produits,
                SUM(CASE WHEN p.type = \'service\' THEN 1 ELSE 0 END) as services,
                SUM(CASE WHEN p.type = \'forfait\' THEN 1 ELSE 0 END) as forfaits
            ');

        return $qb->getQuery()->getSingleResult();
    }
}
