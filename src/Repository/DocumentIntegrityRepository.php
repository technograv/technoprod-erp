<?php

namespace App\Repository;

use App\Entity\DocumentIntegrity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentIntegrity>
 */
class DocumentIntegrityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentIntegrity::class);
    }

    /**
     * Trouve l'intégrité d'un document spécifique
     */
    public function findByDocument(string $documentType, int $documentId): ?DocumentIntegrity
    {
        return $this->findOneBy([
            'documentType' => $documentType,
            'documentId' => $documentId
        ]);
    }

    /**
     * Trouve le dernier hash d'un type de document pour chaînage
     */
    public function findLastHashForDocumentType(string $documentType): ?string
    {
        $result = $this->createQueryBuilder('di')
            ->select('di.documentHash')
            ->where('di.documentType = :documentType')
            ->setParameter('documentType', $documentType)
            ->orderBy('di.timestampCreation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['documentHash'] ?? null;
    }

    /**
     * Compte les documents par statut
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('di')
            ->select('di.status, COUNT(di.id) as count')
            ->groupBy('di.status')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents avec intégrité compromise
     */
    public function findCompromisedDocuments(): array
    {
        return $this->createQueryBuilder('di')
            ->where('di.integrityValid = false')
            ->orderBy('di.lastVerification', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents non vérifiés depuis X jours
     */
    public function findUnverifiedDocuments(int $daysSinceLastVerification = 30): array
    {
        $date = new \DateTime();
        $date->modify("-{$daysSinceLastVerification} days");

        return $this->createQueryBuilder('di')
            ->where('di.lastVerification IS NULL OR di.lastVerification < :date')
            ->setParameter('date', $date)
            ->orderBy('di.timestampCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'intégrité par période
     */
    public function getIntegrityStatsByPeriod(\DateTime $dateFrom, \DateTime $dateTo): array
    {
        return $this->createQueryBuilder('di')
            ->select([
                'COUNT(di.id) as total_documents',
                'SUM(CASE WHEN di.integrityValid = true THEN 1 ELSE 0 END) as valid_documents',
                'SUM(CASE WHEN di.integrityValid = false THEN 1 ELSE 0 END) as compromised_documents',
                'SUM(CASE WHEN di.integrityValid IS NULL THEN 1 ELSE 0 END) as unverified_documents',
                'SUM(CASE WHEN di.blockchainTxHash IS NOT NULL THEN 1 ELSE 0 END) as blockchain_anchored'
            ])
            ->where('di.timestampCreation BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Vérifie la chaîne d'intégrité pour un type de document
     */
    public function verifyIntegrityChain(string $documentType): array
    {
        $documents = $this->createQueryBuilder('di')
            ->where('di.documentType = :documentType')
            ->setParameter('documentType', $documentType)
            ->orderBy('di.timestampCreation', 'ASC')
            ->getQuery()
            ->getResult();

        $errors = [];
        $previousHash = null;

        foreach ($documents as $doc) {
            if ($previousHash !== $doc->getPreviousHash()) {
                $errors[] = [
                    'document_id' => $doc->getId(),
                    'document_number' => $doc->getDocumentNumber(),
                    'error' => 'Rupture de chaînage détectée',
                    'expected_previous_hash' => $previousHash,
                    'actual_previous_hash' => $doc->getPreviousHash()
                ];
            }
            $previousHash = $doc->getDocumentHash();
        }

        return [
            'valid' => empty($errors),
            'total_documents' => count($documents),
            'errors' => $errors
        ];
    }

    /**
     * Recherche par critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('di');

        if (isset($criteria['document_type'])) {
            $qb->andWhere('di.documentType = :documentType')
               ->setParameter('documentType', $criteria['document_type']);
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('di.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['date_from'])) {
            $qb->andWhere('di.timestampCreation >= :dateFrom')
               ->setParameter('dateFrom', $criteria['date_from']);
        }

        if (isset($criteria['date_to'])) {
            $qb->andWhere('di.timestampCreation <= :dateTo')
               ->setParameter('dateTo', $criteria['date_to']);
        }

        if (isset($criteria['user_id'])) {
            $qb->andWhere('di.createdBy = :userId')
               ->setParameter('userId', $criteria['user_id']);
        }

        if (isset($criteria['blockchain_only']) && $criteria['blockchain_only']) {
            $qb->andWhere('di.blockchainTxHash IS NOT NULL');
        }

        return $qb->orderBy('di.timestampCreation', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}