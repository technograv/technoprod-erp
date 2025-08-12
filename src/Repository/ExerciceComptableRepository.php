<?php

namespace App\Repository;

use App\Entity\ExerciceComptable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciceComptable>
 */
class ExerciceComptableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciceComptable::class);
    }

    /**
     * Trouve l'exercice actuel (ouvert)
     */
    public function findExerciceActuel(): ?ExerciceComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', 'ouvert')
            ->andWhere('e.dateDebut <= :maintenant')
            ->andWhere('e.dateFin >= :maintenant')
            ->setParameter('maintenant', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un exercice par année
     */
    public function findByAnnee(int $annee): ?ExerciceComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.anneeExercice = :annee')
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve l'exercice pour une date donnée
     */
    public function findByDate(\DateTimeInterface $date): ?ExerciceComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.dateDebut <= :date')
            ->andWhere('e.dateFin >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les exercices par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('e.anneeExercice', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les exercices ouverts
     */
    public function findOuverts(): array
    {
        return $this->findByStatut('ouvert');
    }

    /**
     * Trouve les exercices clos
     */
    public function findClos(): array
    {
        return $this->findByStatut('cloture');
    }

    /**
     * Trouve les exercices archivés
     */
    public function findArchives(): array
    {
        return $this->findByStatut('archive');
    }

    /**
     * Trouve tous les exercices triés par année décroissante
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.anneeExercice', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le dernier exercice créé
     */
    public function findDernier(): ?ExerciceComptable
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.anneeExercice', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve l'exercice précédent par rapport à une année donnée
     */
    public function findPrecedent(int $annee): ?ExerciceComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.anneeExercice < :annee')
            ->setParameter('annee', $annee)
            ->orderBy('e.anneeExercice', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve l'exercice suivant par rapport à une année donnée
     */
    public function findSuivant(int $annee): ?ExerciceComptable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.anneeExercice > :annee')
            ->setParameter('annee', $annee)
            ->orderBy('e.anneeExercice', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un exercice existe pour une année donnée
     */
    public function exerciceExists(int $annee, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.anneeExercice = :annee')
            ->setParameter('annee', $annee);

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Statistiques globales des exercices
     */
    public function getStatistiques(): array
    {
        return [
            'total' => $this->count([]),
            'ouverts' => $this->count(['statut' => 'ouvert']),
            'clos' => $this->count(['statut' => 'cloture']),
            'archives' => $this->count(['statut' => 'archive'])
        ];
    }

    /**
     * Trouve les exercices avec leurs totaux
     */
    public function findAvecTotaux(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e, SUM(ec.totalDebit) as total_debit_global, SUM(ec.totalCredit) as total_credit_global')
            ->leftJoin('e.ecrituresComptables', 'ec')
            ->groupBy('e.id')
            ->orderBy('e.anneeExercice', 'DESC')
            ->getQuery()
            ->getResult();
    }
}