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
     * Recherche de produits par terme (insensible à la casse)
     */
    public function search(string $terme): array
    {
        $terme = strtolower($terme); // Convertir en minuscules
        
        return $this->createQueryBuilder('p')
            ->andWhere('p.actif = :actif')
            ->andWhere('
                LOWER(p.designation) LIKE :terme OR 
                LOWER(p.reference) LIKE :terme OR 
                LOWER(p.description) LIKE :terme
            ')
            ->setParameter('actif', true)
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('p.designation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de produits par champ spécifique pour autocomplétion
     */
    public function searchByField(string $query, string $field = 'designation', int $limit = 10): array
    {
        $query = strtolower($query);
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.actif = :actif')
            ->setParameter('actif', true)
            ->setMaxResults($limit);

        switch ($field) {
            case 'reference':
                $qb->andWhere('LOWER(p.reference) LIKE :query')
                   ->orderBy('p.reference', 'ASC');
                break;
            case 'designation':
                $qb->andWhere('LOWER(p.designation) LIKE :query')
                   ->orderBy('p.designation', 'ASC');
                break;
            default:
                $qb->andWhere('
                    LOWER(p.designation) LIKE :query OR 
                    LOWER(p.reference) LIKE :query
                ')
                   ->orderBy('p.designation', 'ASC');
        }

        $qb->setParameter('query', '%' . $query . '%');

        return $qb->getQuery()->getResult();
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
