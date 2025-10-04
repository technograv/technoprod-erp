<?php

namespace App\Repository;

use App\Entity\FraisGeneraux;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FraisGeneraux>
 */
class FraisGenerauxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FraisGeneraux::class);
    }

    /**
     * Récupère les frais généraux actifs pour une période donnée
     */
    public function findActifsPourPeriode(?string $periode = null): array
    {
        if ($periode === null) {
            $periode = (new \DateTimeImmutable())->format('Y-m');
        }

        return $this->createQueryBuilder('f')
            ->where('f.actif = :actif')
            ->andWhere('f.periode = :periode')
            ->setParameter('actif', true)
            ->setParameter('periode', $periode)
            ->orderBy('f.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
