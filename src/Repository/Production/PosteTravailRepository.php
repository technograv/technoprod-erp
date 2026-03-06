<?php

namespace App\Repository\Production;

use App\Entity\Production\CategoriePoste;
use App\Entity\Production\PosteTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PosteTravail>
 */
class PosteTravailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PosteTravail::class);
    }

    /**
     * Trouve tous les postes actifs triés par catégorie et libellé
     *
     * @return PosteTravail[]
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.categorie', 'c')
            ->where('p.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les postes d'une catégorie
     *
     * @return PosteTravail[]
     */
    public function findByCategorie(CategoriePoste $categorie, bool $actifsOnly = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.categorie = :categorie')
            ->setParameter('categorie', $categorie);

        if ($actifsOnly) {
            $qb->andWhere('p.actif = :actif')
               ->setParameter('actif', true);
        }

        return $qb->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de postes par terme (code ou libellé)
     *
     * @return PosteTravail[]
     */
    public function search(string $term, bool $actifsOnly = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.categorie', 'c')
            ->where('p.code LIKE :term OR p.libelle LIKE :term')
            ->setParameter('term', '%' . $term . '%');

        if ($actifsOnly) {
            $qb->andWhere('p.actif = :actif')
               ->setParameter('actif', true);
        }

        return $qb->orderBy('c.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les postes nécessitant un opérateur
     *
     * @return PosteTravail[]
     */
    public function findNecessitantOperateur(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.categorie', 'c')
            ->where('p.actif = :actif')
            ->andWhere('p.necessiteOperateur = :necessite')
            ->setParameter('actif', true)
            ->setParameter('necessite', true)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les postes polyvalents
     *
     * @return PosteTravail[]
     */
    public function findPolyvalents(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.categorie', 'c')
            ->where('p.actif = :actif')
            ->andWhere('p.polyvalent = :polyvalent')
            ->setParameter('actif', true)
            ->setParameter('polyvalent', true)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le coût horaire moyen de tous les postes actifs
     */
    public function calculerCoutHoraireMoyen(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.coutHoraire) as moyenne')
            ->where('p.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)$result, 2);
    }

    /**
     * Statistiques des postes par catégorie
     *
     * @return array [categorie_libelle => [total, actifs, cout_moyen]]
     */
    public function getStatistiquesParCategorie(): array
    {
        $results = $this->createQueryBuilder('p')
            ->select(
                'c.libelle as categorie',
                'COUNT(p.id) as total',
                'SUM(CASE WHEN p.actif = true THEN 1 ELSE 0 END) as actifs',
                'AVG(p.coutHoraire) as cout_moyen'
            )
            ->join('p.categorie', 'c')
            ->groupBy('c.id', 'c.libelle')
            ->orderBy('c.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['categorie']] = [
                'total' => (int)$result['total'],
                'actifs' => (int)$result['actifs'],
                'cout_moyen' => round((float)$result['cout_moyen'], 2)
            ];
        }

        return $stats;
    }
}
