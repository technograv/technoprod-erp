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
        // Récupérer tous les codes clients existants et trouver le plus grand numéro
        $existingCodes = $this->createQueryBuilder('c')
            ->select('c.code')
            ->where('c.code LIKE :pattern')
            ->setParameter('pattern', 'CLI%')
            ->getQuery()
            ->getArrayResult();

        $maxNumber = 0;
        foreach ($existingCodes as $codeData) {
            $code = $codeData['code'];
            // Extraire le numéro (CLI001 -> 001, CLI123 -> 123)
            if (preg_match('/^CLI(\d+)$/', $code, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        // Générer le prochain code disponible
        $nextNumber = $maxNumber + 1;
        $nextCode = 'CLI' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        // Vérifier que le code n'existe pas (sécurité supplémentaire)
        while ($this->findOneBy(['code' => $nextCode])) {
            $nextNumber++;
            $nextCode = 'CLI' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }
        
        return $nextCode;
    }

    /**
     * Trouve le prochain code prospect disponible
     */
    public function getNextProspectCode(): string
    {
        // Récupérer tous les codes prospects existants et trouver le plus grand numéro
        $existingCodes = $this->createQueryBuilder('c')
            ->select('c.code')
            ->where('c.code LIKE :pattern')
            ->setParameter('pattern', 'P%')
            ->getQuery()
            ->getArrayResult();

        $maxNumber = 0;
        foreach ($existingCodes as $codeData) {
            $code = $codeData['code'];
            // Extraire le numéro (P001 -> 001, P123 -> 123)
            if (preg_match('/^P(\d+)$/', $code, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        // Générer le prochain code disponible
        $nextNumber = $maxNumber + 1;
        $nextCode = 'P' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        // Vérifier que le code n'existe pas (sécurité supplémentaire)
        while ($this->findOneBy(['code' => $nextCode])) {
            $nextNumber++;
            $nextCode = 'P' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }
        
        return $nextCode;
    }
}