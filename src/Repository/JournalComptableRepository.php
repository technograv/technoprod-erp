<?php

namespace App\Repository;

use App\Entity\JournalComptable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalComptable>
 */
class JournalComptableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalComptable::class);
    }

    /**
     * Trouve les journaux actifs
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un journal par son code
     */
    public function findOneByCode(string $code): ?JournalComptable
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.code = :code')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les journaux par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.type = :type')
            ->setParameter('type', $type)
            ->andWhere('j.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les journaux obligatoires
     */
    public function findObligatoires(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.isObligatoire = :obligatoire')
            ->setParameter('obligatoire', true)
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de journaux par code ou libellé
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.code LIKE :term OR j.libelle LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->andWhere('j.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des journaux avec nombre d'écritures
     */
    public function getStatistiquesAvecEcritures(): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.code, j.libelle, j.type, COUNT(e.id) as nombre_ecritures')
            ->leftJoin('j.ecritures', 'e')
            ->andWhere('j.isActif = :actif')
            ->setParameter('actif', true)
            ->groupBy('j.id')
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les journaux avec contrôle de numérotation
     */
    public function findAvecControleNumero(): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.isControleNumeroEcriture = :controle')
            ->setParameter('controle', true)
            ->andWhere('j.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un code de journal existe déjà
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->andWhere('j.code = :code')
            ->setParameter('code', strtoupper($code));

        if ($excludeId !== null) {
            $qb->andWhere('j.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}