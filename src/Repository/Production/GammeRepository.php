<?php

namespace App\Repository\Production;

use App\Entity\Production\Gamme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gamme>
 */
class GammeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gamme::class);
    }

    /**
     * Trouve toutes les gammes actives triées par code
     *
     * @return Gamme[]
     */
    public function findActives(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les gammes validées
     *
     * @return Gamme[]
     */
    public function findValidees(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.statut = :statut')
            ->andWhere('g.actif = :actif')
            ->setParameter('statut', Gamme::STATUT_VALIDEE)
            ->setParameter('actif', true)
            ->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par terme (code ou libellé)
     *
     * @return Gamme[]
     */
    public function search(string $term, bool $actifsOnly = true): array
    {
        $qb = $this->createQueryBuilder('g')
            ->where('g.code LIKE :term OR g.libelle LIKE :term')
            ->setParameter('term', '%' . $term . '%');

        if ($actifsOnly) {
            $qb->andWhere('g.actif = :actif')
               ->setParameter('actif', true);
        }

        return $qb->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les gammes orphelines (sans produit catalogue associé)
     *
     * @return Gamme[]
     */
    public function findOrphelines(): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('App\Entity\Catalogue\ProduitCatalogue', 'pc', 'WITH', 'pc.gamme = g.id')
            ->where('pc.id IS NULL')
            ->orderBy('g.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des gammes
     */
    public function getStatistiques(): array
    {
        $result = $this->createQueryBuilder('g')
            ->select(
                'COUNT(g.id) as total',
                'SUM(CASE WHEN g.statut = :valide THEN 1 ELSE 0 END) as validees',
                'SUM(CASE WHEN g.statut = :brouillon THEN 1 ELSE 0 END) as brouillons',
                'SUM(CASE WHEN g.actif = true THEN 1 ELSE 0 END) as actives',
                'AVG(g.tempsTotalTheorique) as temps_moyen'
            )
            ->setParameter('valide', Gamme::STATUT_VALIDEE)
            ->setParameter('brouillon', Gamme::STATUT_BROUILLON)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'validees' => (int)$result['validees'],
            'brouillons' => (int)$result['brouillons'],
            'actives' => (int)$result['actives'],
            'temps_moyen' => $result['temps_moyen'] ? round((float)$result['temps_moyen'], 2) : 0
        ];
    }
}
