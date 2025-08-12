<?php

namespace App\Repository;

use App\Entity\Prospect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prospect>
 */
class ProspectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prospect::class);
    }

    /**
     * Trouve les prospects par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prospects convertis ce mois
     */
    public function findConversionsThisMonth(): array
    {
        $thisMonth = new \DateTimeImmutable('first day of this month');
        
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateConversionClient >= :thisMonth')
            ->setParameter('statut', 'client')
            ->setParameter('thisMonth', $thisMonth)
            ->orderBy('p.dateConversionClient', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche prospects par critères
     */
    public function findByCriteria(?string $statut = null, ?string $famille = null, ?int $secteurId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.adresseFacturation', 'af')
            ->leftJoin('p.contactFacturation', 'cf')
            ->leftJoin('p.secteur', 's')
            ->leftJoin('p.commercial', 'c');

        if ($statut) {
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($famille) {
            $qb->andWhere('p.famille LIKE :famille')
               ->setParameter('famille', '%' . $famille . '%');
        }

        if ($secteurId) {
            $qb->andWhere('p.secteur = :secteur')
               ->setParameter('secteur', $secteurId);
        }

        return $qb->orderBy('p.updatedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte les prospects par statut
     */
    public function countByStatut(string $statut): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve le prochain code prospect disponible
     */
    public function getNextProspectCode(): string
    {
        $lastProspect = $this->createQueryBuilder('p')
            ->where('p.code LIKE :pattern')
            ->setParameter('pattern', 'P%')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastProspect) {
            return 'P001';
        }

        // Extraire le numéro du code (P001 -> 001)
        $lastNumber = (int) substr($lastProspect->getCode(), 1);
        $nextNumber = $lastNumber + 1;

        return 'P' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}