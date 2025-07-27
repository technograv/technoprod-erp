<?php

namespace App\Repository;

use App\Entity\LigneEcriture;
use App\Entity\EcritureComptable;
use App\Entity\ComptePCG;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneEcriture>
 */
class LigneEcritureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneEcriture::class);
    }

    /**
     * Trouve les lignes d'une écriture
     */
    public function findByEcriture(EcritureComptable $ecriture): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.ecriture = :ecriture')
            ->setParameter('ecriture', $ecriture)
            ->orderBy('l.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes d'un compte
     */
    public function findByCompte(ComptePCG $compte): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.comptePCG = :compte')
            ->setParameter('compte', $compte)
            ->join('l.ecriture', 'e')
            ->orderBy('e.dateEcriture', 'DESC')
            ->addOrderBy('l.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes d'un compte sur une période
     */
    public function findByComptePeriode(ComptePCG $compte, \DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.ecriture', 'e')
            ->andWhere('l.comptePCG = :compte')
            ->andWhere('e.dateEcriture >= :dateDebut')
            ->andWhere('e.dateEcriture <= :dateFin')
            ->setParameter('compte', $compte)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->orderBy('e.dateEcriture', 'ASC')
            ->addOrderBy('l.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes au débit
     */
    public function findDebits(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.montantDebit > :zero')
            ->setParameter('zero', '0.00')
            ->join('l.ecriture', 'e')
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes au crédit
     */
    public function findCredits(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.montantCredit > :zero')
            ->setParameter('zero', '0.00')
            ->join('l.ecriture', 'e')
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes avec auxiliaire (tiers)
     */
    public function findAvecAuxiliaire(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.compteAuxiliaire IS NOT NULL')
            ->join('l.ecriture', 'e')
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes d'un compte auxiliaire
     */
    public function findByCompteAuxiliaire(string $compteAuxiliaire): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.compteAuxiliaire = :compteAuxiliaire')
            ->setParameter('compteAuxiliaire', $compteAuxiliaire)
            ->join('l.ecriture', 'e')
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes lettrées
     */
    public function findLettrees(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.lettrage IS NOT NULL')
            ->andWhere('l.dateLettrage IS NOT NULL')
            ->join('l.ecriture', 'e')
            ->orderBy('l.dateLettrage', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes non lettrées
     */
    public function findNonLettrees(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.lettrage IS NULL OR l.dateLettrage IS NULL')
            ->join('l.ecriture', 'e')
            ->join('l.comptePCG', 'c')
            ->andWhere('c.isPourLettrage = :lettrage')
            ->setParameter('lettrage', true)
            ->orderBy('e.dateEcriture', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes par code de lettrage
     */
    public function findByLettrage(string $lettrage): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.lettrage = :lettrage')
            ->setParameter('lettrage', $lettrage)
            ->join('l.ecriture', 'e')
            ->orderBy('e.dateEcriture', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes avec échéance
     */
    public function findAvecEcheance(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.dateEcheance IS NOT NULL')
            ->join('l.ecriture', 'e')
            ->orderBy('l.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes échues
     */
    public function findEchues(\DateTimeInterface $dateReference = null): array
    {
        if ($dateReference === null) {
            $dateReference = new \DateTime();
        }

        return $this->createQueryBuilder('l')
            ->andWhere('l.dateEcheance IS NOT NULL')
            ->andWhere('l.dateEcheance <= :dateReference')
            ->andWhere('l.lettrage IS NULL') // Non lettrées donc non réglées
            ->setParameter('dateReference', $dateReference)
            ->join('l.ecriture', 'e')
            ->orderBy('l.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le solde d'un compte
     */
    public function getSoldeCompte(ComptePCG $compte): array
    {
        return $this->createQueryBuilder('l')
            ->select('SUM(l.montantDebit) as total_debit, SUM(l.montantCredit) as total_credit')
            ->andWhere('l.comptePCG = :compte')
            ->setParameter('compte', $compte)
            ->join('l.ecriture', 'e')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('validee', true)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Calcule le solde d'un compte sur une période
     */
    public function getSoldeComptePeriode(ComptePCG $compte, \DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('l')
            ->select('SUM(l.montantDebit) as total_debit, SUM(l.montantCredit) as total_credit')
            ->join('l.ecriture', 'e')
            ->andWhere('l.comptePCG = :compte')
            ->andWhere('e.dateEcriture >= :dateDebut')
            ->andWhere('e.dateEcriture <= :dateFin')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('compte', $compte)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->setParameter('validee', true)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Calcule le solde d'un compte auxiliaire
     */
    public function getSoldeCompteAuxiliaire(string $compteAuxiliaire): array
    {
        return $this->createQueryBuilder('l')
            ->select('SUM(l.montantDebit) as total_debit, SUM(l.montantCredit) as total_credit')
            ->andWhere('l.compteAuxiliaire = :compteAuxiliaire')
            ->setParameter('compteAuxiliaire', $compteAuxiliaire)
            ->join('l.ecriture', 'e')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('validee', true)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Recherche de lignes par critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('l')
            ->join('l.ecriture', 'e')
            ->join('l.comptePCG', 'c');

        if (!empty($criteria['compte'])) {
            $qb->andWhere('l.comptePCG = :compte')
               ->setParameter('compte', $criteria['compte']);
        }

        if (!empty($criteria['compteAuxiliaire'])) {
            $qb->andWhere('l.compteAuxiliaire LIKE :compteAuxiliaire')
               ->setParameter('compteAuxiliaire', '%' . $criteria['compteAuxiliaire'] . '%');
        }

        if (!empty($criteria['libelle'])) {
            $qb->andWhere('l.libelleLigne LIKE :libelle')
               ->setParameter('libelle', '%' . $criteria['libelle'] . '%');
        }

        if (!empty($criteria['dateDebut'])) {
            $qb->andWhere('e.dateEcriture >= :dateDebut')
               ->setParameter('dateDebut', $criteria['dateDebut']);
        }

        if (!empty($criteria['dateFin'])) {
            $qb->andWhere('e.dateEcriture <= :dateFin')
               ->setParameter('dateFin', $criteria['dateFin']);
        }

        if (isset($criteria['lettrage'])) {
            if ($criteria['lettrage'] === true) {
                $qb->andWhere('l.lettrage IS NOT NULL');
            } elseif ($criteria['lettrage'] === false) {
                $qb->andWhere('l.lettrage IS NULL');
            }
        }

        return $qb->orderBy('e.dateEcriture', 'DESC')
                  ->addOrderBy('l.ordre', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Statistiques des lignes par compte
     */
    public function getStatistiquesParCompte(): array
    {
        return $this->createQueryBuilder('l')
            ->select('c.numeroCompte, c.libelle, COUNT(l.id) as nombre_lignes, SUM(l.montantDebit) as total_debit, SUM(l.montantCredit) as total_credit')
            ->join('l.comptePCG', 'c')
            ->join('l.ecriture', 'e')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('validee', true)
            ->groupBy('l.comptePCG')
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les lignes pour la balance comptable
     */
    public function findForBalance(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): array
    {
        return $this->createQueryBuilder('l')
            ->select('c.numeroCompte, c.libelle, SUM(l.montantDebit) as total_debit, SUM(l.montantCredit) as total_credit')
            ->join('l.comptePCG', 'c')
            ->join('l.ecriture', 'e')
            ->andWhere('e.dateEcriture >= :dateDebut')
            ->andWhere('e.dateEcriture <= :dateFin')
            ->andWhere('e.isValidee = :validee')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->setParameter('validee', true)
            ->groupBy('l.comptePCG')
            ->having('SUM(l.montantDebit) > 0 OR SUM(l.montantCredit) > 0') // Exclut les comptes sans mouvement
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }
}