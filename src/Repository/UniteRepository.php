<?php

namespace App\Repository;

use App\Entity\Unite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unite>
 */
class UniteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unite::class);
    }

    /**
     * Trouve toutes les unités ordonnées
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.ordre', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les unités actives ordonnées
     */
    public function findAllActiveOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('u.ordre', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les unités par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.type = :type')
            ->andWhere('u.actif = :actif')
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->orderBy('u.ordre', 'ASC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les types d'unités distincts
     */
    public function findAllTypes(): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('DISTINCT u.type')
            ->where('u.type IS NOT NULL')
            ->andWhere('u.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('u.type', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($results, 'type');
    }

    /**
     * Réorganise les ordres automatiquement pour éviter les doublons
     */
    public function reorganizeOrdres(Unite $unite, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Si le nouvel ordre est différent de l'ancien
        $oldOrdre = $unite->getOrdre();
        if ($oldOrdre !== $newOrdre) {
            
            // Décaler tous les éléments qui ont un ordre >= au nouvel ordre
            $qb = $entityManager->createQueryBuilder();
            $qb->update(Unite::class, 'u')
               ->set('u.ordre', 'u.ordre + 1')
               ->where('u.ordre >= :newOrdre')
               ->andWhere('u.id != :currentId')
               ->setParameter('newOrdre', $newOrdre)
               ->setParameter('currentId', $unite->getId());
            $qb->getQuery()->execute();
            
            // Définir le nouvel ordre
            $unite->setOrdre($newOrdre);
            $entityManager->persist($unite);
        }
        
        $entityManager->flush();
        
        // Réorganiser pour enlever les trous
        $this->compactOrdres();
    }

    /**
     * Compacte les ordres pour enlever les trous (1,2,3,4... sans saut)
     */
    private function compactOrdres(): void
    {
        $entityManager = $this->getEntityManager();
        
        // Récupérer tous les éléments ordonnés
        $elements = $this->findAllOrdered();
        
        $ordre = 1;
        foreach ($elements as $element) {
            if ($element->getOrdre() !== $ordre) {
                $element->setOrdre($ordre);
                $entityManager->persist($element);
            }
            $ordre++;
        }
        
        $entityManager->flush();
    }

    /**
     * Trouve une unité par son code
     */
    public function findByCode(string $code): ?Unite
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un code d'unité existe déjà
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('u.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}