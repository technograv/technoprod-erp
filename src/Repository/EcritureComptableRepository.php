<?php

namespace App\Repository;

use App\Entity\EcritureComptable;
use App\Entity\ExerciceComptable;
use App\Entity\JournalComptable;
use App\Entity\ComptePCG;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EcritureComptable>
 */
class EcritureComptableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EcritureComptable::class);
    }

    /**
     * Trouve les écritures par journal
     */
    public function findByJournal(JournalComptable $journal): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.journal = :journal')
            ->setParameter('journal', $journal)
            ->orderBy('e.dateEcriture', 'DESC')
            ->addOrderBy('e.numeroEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écritures par exercice
     */
    public function findByExercice(ExerciceComptable $exercice): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exerciceComptable = :exercice')
            ->setParameter('exercice', $exercice)
            ->orderBy('e.dateEcriture', 'ASC')
            ->addOrderBy('e.numeroEcriture', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écritures sur une période
     */
    public function findByPeriode(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.dateEcriture >= :dateDebut')
            ->andWhere('e.dateEcriture <= :dateFin')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->orderBy('e.dateEcriture', 'ASC')
            ->addOrderBy('e.numeroEcriture', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écritures non validées
     */
    public function findNonValidees(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('validee', false)
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écritures déséquilibrées
     */
    public function findDesequilibrees(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isEquilibree = :equilibree')
            ->setParameter('equilibree', false)
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écritures liées à un document
     */
    public function findByDocument(string $documentType, int $documentId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.documentType = :documentType')
            ->andWhere('e.documentId = :documentId')
            ->setParameter('documentType', $documentType)
            ->setParameter('documentId', $documentId)
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche d'écritures par critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.journal', 'j')
            ->leftJoin('e.exerciceComptable', 'ex');

        if (!empty($criteria['journal'])) {
            $qb->andWhere('e.journal = :journal')
               ->setParameter('journal', $criteria['journal']);
        }

        if (!empty($criteria['exercice'])) {
            $qb->andWhere('e.exerciceComptable = :exercice')
               ->setParameter('exercice', $criteria['exercice']);
        }

        if (!empty($criteria['dateDebut'])) {
            $qb->andWhere('e.dateEcriture >= :dateDebut')
               ->setParameter('dateDebut', $criteria['dateDebut']);
        }

        if (!empty($criteria['dateFin'])) {
            $qb->andWhere('e.dateEcriture <= :dateFin')
               ->setParameter('dateFin', $criteria['dateFin']);
        }

        if (!empty($criteria['numeroEcriture'])) {
            $qb->andWhere('e.numeroEcriture LIKE :numeroEcriture')
               ->setParameter('numeroEcriture', '%' . $criteria['numeroEcriture'] . '%');
        }

        if (!empty($criteria['libelle'])) {
            $qb->andWhere('e.libelleEcriture LIKE :libelle')
               ->setParameter('libelle', '%' . $criteria['libelle'] . '%');
        }

        if (isset($criteria['validee'])) {
            $qb->andWhere('e.isValidee = :validee')
               ->setParameter('validee', $criteria['validee']);
        }

        return $qb->orderBy('e.dateEcriture', 'DESC')
                  ->addOrderBy('e.numeroEcriture', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve la dernière écriture d'un journal
     */
    public function findDerniereParJournal(JournalComptable $journal): ?EcritureComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.journal = :journal')
            ->setParameter('journal', $journal)
            ->orderBy('e.numeroEcriture', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve une écriture par son numéro
     */
    public function findOneByNumero(string $numeroEcriture): ?EcritureComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.numeroEcriture = :numero')
            ->setParameter('numero', $numeroEcriture)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Statistiques des écritures par journal
     */
    public function getStatistiquesParJournal(): array
    {
        return $this->createQueryBuilder('e')
            ->select('j.code as journal_code, j.libelle as journal_libelle, COUNT(e.id) as nombre_ecritures, SUM(e.totalDebit) as total_debit, SUM(e.totalCredit) as total_credit')
            ->join('e.journal', 'j')
            ->groupBy('e.journal')
            ->orderBy('j.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des écritures par exercice
     */
    public function getStatistiquesParExercice(): array
    {
        return $this->createQueryBuilder('e')
            ->select('ex.anneeExercice, COUNT(e.id) as nombre_ecritures, SUM(e.totalDebit) as total_debit, SUM(e.totalCredit) as total_credit')
            ->join('e.exerciceComptable', 'ex')
            ->groupBy('e.exerciceComptable')
            ->orderBy('ex.anneeExercice', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écritures pour export FEC
     */
    public function findForFEC(ExerciceComptable $exercice, ?\DateTimeInterface $dateDebut = null, ?\DateTimeInterface $dateFin = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e, j, le, c')
            ->join('e.journal', 'j')
            ->join('e.lignesEcriture', 'le')
            ->join('le.comptePCG', 'c')
            ->andWhere('e.exerciceComptable = :exercice')
            ->setParameter('exercice', $exercice)
            ->andWhere('e.isValidee = :validee')
            ->setParameter('validee', true);

        if ($dateDebut) {
            $qb->andWhere('e.dateEcriture >= :dateDebut')
               ->setParameter('dateDebut', $dateDebut);
        }

        if ($dateFin) {
            $qb->andWhere('e.dateEcriture <= :dateFin')
               ->setParameter('dateFin', $dateFin);
        }

        return $qb->orderBy('e.dateEcriture', 'ASC')
                  ->addOrderBy('e.numeroEcriture', 'ASC')
                  ->addOrderBy('le.ordre', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Calcule les totaux pour une période
     */
    public function getTotauxPeriode(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('e')
            ->select('SUM(e.totalDebit) as total_debit, SUM(e.totalCredit) as total_credit, COUNT(e.id) as nombre_ecritures')
            ->andWhere('e.dateEcriture >= :dateDebut')
            ->andWhere('e.dateEcriture <= :dateFin')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->setParameter('validee', true)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Vérifie si un numéro d'écriture existe déjà
     */
    public function numeroExists(string $numeroEcriture, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.numeroEcriture = :numero')
            ->setParameter('numero', $numeroEcriture);

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}