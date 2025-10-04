<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fournisseur>
 */
class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fournisseur::class);
    }

    /**
     * Recherche fournisseurs actifs par nom ou code
     */
    public function searchActifs(string $query): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.statut = :actif')
            ->andWhere('f.raisonSociale LIKE :query OR f.code LIKE :query')
            ->setParameter('actif', 'actif')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('f.raisonSociale', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste tous les fournisseurs actifs
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.statut = :actif')
            ->setParameter('actif', 'actif')
            ->orderBy('f.raisonSociale', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
