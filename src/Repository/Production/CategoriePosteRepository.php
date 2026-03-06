<?php

namespace App\Repository\Production;

use App\Entity\Production\CategoriePoste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoriePoste>
 */
class CategoriePosteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoriePoste::class);
    }

    /**
     * Trouve toutes les catégories actives triées par ordre
     *
     * @return CategoriePoste[]
     */
    public function findActives(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les catégories triées par ordre
     *
     * @return CategoriePoste[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de postes actifs par catégorie
     *
     * @return array Tableau associatif [categorie_id => nombre_postes]
     */
    public function countPostesActifsParCategorie(): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.id', 'COUNT(p.id) as nb_postes')
            ->leftJoin('c.postes', 'p')
            ->where('p.actif = :actif')
            ->setParameter('actif', true)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['id']] = (int)$result['nb_postes'];
        }

        return $counts;
    }
}
