<?php

namespace App\Repository\Production;

use App\Entity\Production\Nomenclature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Nomenclature>
 */
class NomenclatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Nomenclature::class);
    }

    /**
     * Trouve toutes les nomenclatures racines (sans parent)
     *
     * @return Nomenclature[]
     */
    public function findRacines(bool $actifsOnly = true): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.parent IS NULL');

        if ($actifsOnly) {
            $qb->andWhere('n.actif = :actif')
               ->setParameter('actif', true);
        }

        return $qb->orderBy('n.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les nomenclatures validées
     *
     * @return Nomenclature[]
     */
    public function findValidees(): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.statut = :statut')
            ->andWhere('n.actif = :actif')
            ->setParameter('statut', Nomenclature::STATUT_VALIDEE)
            ->setParameter('actif', true)
            ->orderBy('n.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par terme (code ou libellé)
     *
     * @return Nomenclature[]
     */
    public function search(string $term, bool $actifsOnly = true): array
    {
        $qb = $this->createQueryBuilder('n')
            ->where('n.code LIKE :term OR n.libelle LIKE :term')
            ->setParameter('term', '%' . $term . '%');

        if ($actifsOnly) {
            $qb->andWhere('n.actif = :actif')
               ->setParameter('actif', true);
        }

        return $qb->orderBy('n.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les nomenclatures orphelines (sans produit catalogue associé)
     * Utile pour nettoyer les nomenclatures inutilisées
     *
     * @return Nomenclature[]
     */
    public function findOrphelines(): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('App\Entity\Catalogue\ProduitCatalogue', 'pc', 'WITH', 'pc.nomenclature = n.id')
            ->where('pc.id IS NULL')
            ->andWhere('n.parent IS NULL')
            ->orderBy('n.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des nomenclatures
     */
    public function getStatistiques(): array
    {
        $result = $this->createQueryBuilder('n')
            ->select(
                'COUNT(n.id) as total',
                'SUM(CASE WHEN n.statut = :valide THEN 1 ELSE 0 END) as validees',
                'SUM(CASE WHEN n.statut = :brouillon THEN 1 ELSE 0 END) as brouillons',
                'SUM(CASE WHEN n.actif = true THEN 1 ELSE 0 END) as actives'
            )
            ->setParameter('valide', Nomenclature::STATUT_VALIDEE)
            ->setParameter('brouillon', Nomenclature::STATUT_BROUILLON)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'validees' => (int)$result['validees'],
            'brouillons' => (int)$result['brouillons'],
            'actives' => (int)$result['actives']
        ];
    }
}
