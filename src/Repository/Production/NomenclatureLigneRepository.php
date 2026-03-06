<?php

namespace App\Repository\Production;

use App\Entity\Production\NomenclatureLigne;
use App\Entity\Production\Nomenclature;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NomenclatureLigne>
 */
class NomenclatureLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NomenclatureLigne::class);
    }

    /**
     * Trouve toutes les lignes d'une nomenclature triées par ordre
     *
     * @return NomenclatureLigne[]
     */
    public function findByNomenclatureOrdered(Nomenclature $nomenclature): array
    {
        return $this->createQueryBuilder('nl')
            ->where('nl.nomenclature = :nomenclature')
            ->setParameter('nomenclature', $nomenclature)
            ->orderBy('nl.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les lignes utilisant un produit spécifique
     * Utile pour savoir où un produit est utilisé
     *
     * @return NomenclatureLigne[]
     */
    public function findByProduit(Produit $produit): array
    {
        return $this->createQueryBuilder('nl')
            ->join('nl.nomenclature', 'n')
            ->where('nl.produitSimple = :produit')
            ->setParameter('produit', $produit)
            ->orderBy('n.code', 'ASC')
            ->addOrderBy('nl.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte combien de nomenclatures utilisent un produit
     */
    public function countNomenclaturesUtilisantProduit(Produit $produit): int
    {
        return (int) $this->createQueryBuilder('nl')
            ->select('COUNT(DISTINCT nl.nomenclature)')
            ->where('nl.produitSimple = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les lignes avec formules de calcul
     *
     * @return NomenclatureLigne[]
     */
    public function findAvecFormules(): array
    {
        return $this->createQueryBuilder('nl')
            ->join('nl.nomenclature', 'n')
            ->where('nl.formuleQuantite IS NOT NULL')
            ->orderBy('n.code', 'ASC')
            ->addOrderBy('nl.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes avec conditions d'affichage
     *
     * @return NomenclatureLigne[]
     */
    public function findAvecConditions(): array
    {
        return $this->createQueryBuilder('nl')
            ->join('nl.nomenclature', 'n')
            ->where('nl.conditionAffichage IS NOT NULL')
            ->orderBy('n.code', 'ASC')
            ->addOrderBy('nl.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par type de ligne
     */
    public function getStatistiquesParType(): array
    {
        $results = $this->createQueryBuilder('nl')
            ->select('nl.type', 'COUNT(nl.id) as total')
            ->groupBy('nl.type')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['type']] = (int)$result['total'];
        }

        return $stats;
    }
}
