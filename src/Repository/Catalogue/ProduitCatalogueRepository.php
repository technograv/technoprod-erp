<?php

namespace App\Repository\Catalogue;

use App\Entity\Catalogue\ProduitCatalogue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProduitCatalogue>
 */
class ProduitCatalogueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduitCatalogue::class);
    }

    /**
     * Trouve tous les produits catalogue actifs
     *
     * @return ProduitCatalogue[]
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('pc')
            ->join('pc.produit', 'p')
            ->where('pc.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les produits affichables sur devis
     *
     * @return ProduitCatalogue[]
     */
    public function findPourDevis(): array
    {
        return $this->createQueryBuilder('pc')
            ->join('pc.produit', 'p')
            ->where('pc.actif = :actif')
            ->andWhere('pc.afficherSurDevis = :afficher')
            ->setParameter('actif', true)
            ->setParameter('afficher', true)
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par terme (référence ou désignation produit)
     *
     * @return ProduitCatalogue[]
     */
    public function search(string $term, bool $actifsOnly = true): array
    {
        $qb = $this->createQueryBuilder('pc')
            ->join('pc.produit', 'p')
            ->where('p.reference LIKE :term OR p.designation LIKE :term')
            ->setParameter('term', '%' . $term . '%');

        if ($actifsOnly) {
            $qb->andWhere('pc.actif = :actif')
               ->setParameter('actif', true);
        }

        return $qb->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve par nomenclature
     *
     * @return ProduitCatalogue[]
     */
    public function findByNomenclature($nomenclature): array
    {
        return $this->createQueryBuilder('pc')
            ->where('pc.nomenclature = :nomenclature')
            ->setParameter('nomenclature', $nomenclature)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve par gamme
     *
     * @return ProduitCatalogue[]
     */
    public function findByGamme($gamme): array
    {
        return $this->createQueryBuilder('pc')
            ->where('pc.gamme = :gamme')
            ->setParameter('gamme', $gamme)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques
     */
    public function getStatistiques(): array
    {
        $result = $this->createQueryBuilder('pc')
            ->select(
                'COUNT(pc.id) as total',
                'SUM(CASE WHEN pc.actif = true THEN 1 ELSE 0 END) as actifs',
                'SUM(CASE WHEN pc.personnalisable = true THEN 1 ELSE 0 END) as personnalisables',
                'SUM(CASE WHEN pc.afficherSurDevis = true THEN 1 ELSE 0 END) as sur_devis'
            )
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'actifs' => (int)$result['actifs'],
            'personnalisables' => (int)$result['personnalisables'],
            'sur_devis' => (int)$result['sur_devis']
        ];
    }
}
