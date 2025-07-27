<?php

namespace App\Repository;

use App\Entity\ComptePCG;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ComptePCG>
 */
class ComptePCGRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ComptePCG::class);
    }

    /**
     * Trouve les comptes par classe comptable
     */
    public function findByClasse(string $classe): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.classe = :classe')
            ->setParameter('classe', $classe)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes par nature
     */
    public function findByNature(string $nature): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.nature = :nature')
            ->setParameter('nature', $nature)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes actifs seulement
     */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes pour lettrage (clients/fournisseurs)
     */
    public function findPourLettrage(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPourLettrage = :lettrage')
            ->andWhere('c.isActif = :actif')
            ->setParameter('lettrage', true)
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de comptes par numéro ou libellé
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.numeroCompte LIKE :term OR c.libelle LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes racines (sans parent)
     */
    public function findRacines(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.compteParent IS NULL')
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les sous-comptes d'un compte parent
     */
    public function findSousComptes(ComptePCG $parent): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.compteParent = :parent')
            ->setParameter('parent', $parent)
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes de bilan (classes 1 à 5)
     */
    public function findComptesBilan(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.classe IN (:classes)')
            ->setParameter('classes', ['1', '2', '3', '4', '5'])
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes de gestion (classes 6 et 7)
     */
    public function findComptesGestion(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.classe IN (:classes)')
            ->setParameter('classes', ['6', '7'])
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un compte par son numéro exact
     */
    public function findOneByNumero(string $numeroCompte): ?ComptePCG
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.numeroCompte = :numero')
            ->setParameter('numero', $numeroCompte)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Statistiques des comptes par classe
     */
    public function getStatistiquesParClasse(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.classe, COUNT(c.numeroCompte) as nombre')
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->groupBy('c.classe')
            ->orderBy('c.classe', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes avec un solde non nul
     */
    public function findAvecSolde(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.soldeDebiteur != :zero OR c.soldeCrediteur != :zero')
            ->setParameter('zero', '0.00')
            ->andWhere('c.isActif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.numeroCompte', 'ASC')
            ->getQuery()
            ->getResult();
    }
}