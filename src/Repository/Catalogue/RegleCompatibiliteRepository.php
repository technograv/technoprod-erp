<?php

namespace App\Repository\Catalogue;

use App\Entity\Catalogue\RegleCompatibilite;
use App\Entity\Catalogue\ProduitCatalogue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegleCompatibilite>
 */
class RegleCompatibiliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegleCompatibilite::class);
    }

    /**
     * Trouve toutes les règles actives d'un produit triées par priorité
     *
     * @return RegleCompatibilite[]
     */
    public function findByProduitActives(ProduitCatalogue $produit): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.produitCatalogue = :produit')
            ->andWhere('r.actif = :actif')
            ->setParameter('produit', $produit)
            ->setParameter('actif', true)
            ->orderBy('r.priorite', 'DESC')
            ->addOrderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les règles bloquantes (erreur)
     *
     * @return RegleCompatibilite[]
     */
    public function findBloquantes(ProduitCatalogue $produit): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.produitCatalogue = :produit')
            ->andWhere('r.actif = :actif')
            ->andWhere('r.severite = :severite')
            ->setParameter('produit', $produit)
            ->setParameter('actif', true)
            ->setParameter('severite', RegleCompatibilite::SEVERITE_ERREUR)
            ->orderBy('r.priorite', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve par type de règle
     *
     * @return RegleCompatibilite[]
     */
    public function findByType(ProduitCatalogue $produit, string $type): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.produitCatalogue = :produit')
            ->andWhere('r.actif = :actif')
            ->andWhere('r.typeRegle = :type')
            ->setParameter('produit', $produit)
            ->setParameter('actif', true)
            ->setParameter('type', $type)
            ->orderBy('r.priorite', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par type
     */
    public function getStatistiquesParType(): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.typeRegle', 'COUNT(r.id) as total')
            ->where('r.actif = :actif')
            ->setParameter('actif', true)
            ->groupBy('r.typeRegle')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['typeRegle']] = (int)$result['total'];
        }

        return $stats;
    }
}
