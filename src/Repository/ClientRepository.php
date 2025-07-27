<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Trouve les clients par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prospects convertis ce mois
     */
    public function findConversionsThisMonth(): array
    {
        $thisMonth = new \DateTimeImmutable('first day of this month');
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.dateConversionClient >= :thisMonth')
            ->setParameter('statut', 'client')
            ->setParameter('thisMonth', $thisMonth)
            ->orderBy('c.dateConversionClient', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche clients par critères
     */
    public function findByCriteria(?string $statut = null, ?string $famille = null, ?int $secteurId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.contacts', 'contacts')
            ->leftJoin('c.adresses', 'a')
            ->leftJoin('c.secteur', 's')
            ->leftJoin('c.commercial', 'co');

        if ($statut) {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($famille) {
            $qb->andWhere('c.famille LIKE :famille')
               ->setParameter('famille', '%' . $famille . '%');
        }

        if ($secteurId) {
            $qb->andWhere('c.secteur = :secteur')
               ->setParameter('secteur', $secteurId);
        }

        return $qb->orderBy('c.updatedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte les clients par statut
     */
    public function countByStatut(string $statut): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve le prochain code client disponible
     */
    public function getNextClientCode(): string
    {
        $lastClient = $this->createQueryBuilder('c')
            ->where('c.code LIKE :pattern')
            ->setParameter('pattern', 'CLI%')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastClient) {
            return 'CLI001';
        }

        // Extraire le numéro du code (CLI001 -> 001)
        $lastNumber = (int) substr($lastClient->getCode(), 3);
        $nextNumber = $lastNumber + 1;

        return 'CLI' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Trouve le prochain code prospect disponible
     */
    public function getNextProspectCode(): string
    {
        $lastProspect = $this->createQueryBuilder('c')
            ->where('c.code LIKE :pattern')
            ->setParameter('pattern', 'P%')
            ->orderBy('c.id', 'DESC')
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