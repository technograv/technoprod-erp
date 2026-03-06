<?php

namespace App\Repository\Catalogue;

use App\Entity\Catalogue\OptionProduit;
use App\Entity\Catalogue\ProduitCatalogue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OptionProduit>
 */
class OptionProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OptionProduit::class);
    }

    /**
     * Trouve toutes les options d'un produit triées par ordre
     *
     * @return OptionProduit[]
     */
    public function findByProduitOrdered(ProduitCatalogue $produit): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.produitCatalogue = :produit')
            ->setParameter('produit', $produit)
            ->orderBy('o.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les options obligatoires d'un produit
     *
     * @return OptionProduit[]
     */
    public function findObligatoires(ProduitCatalogue $produit): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.produitCatalogue = :produit')
            ->andWhere('o.obligatoire = :obligatoire')
            ->setParameter('produit', $produit)
            ->setParameter('obligatoire', true)
            ->orderBy('o.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les options avec conditions d'affichage
     *
     * @return OptionProduit[]
     */
    public function findAvecConditions(ProduitCatalogue $produit): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.produitCatalogue = :produit')
            ->andWhere('o.conditionAffichage IS NOT NULL')
            ->setParameter('produit', $produit)
            ->orderBy('o.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par type de champ
     */
    public function getStatistiquesParType(): array
    {
        $results = $this->createQueryBuilder('o')
            ->select('o.typeChamp', 'COUNT(o.id) as total')
            ->groupBy('o.typeChamp')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['typeChamp']] = (int)$result['total'];
        }

        return $stats;
    }
}
