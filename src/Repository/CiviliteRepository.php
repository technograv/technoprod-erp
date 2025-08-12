<?php

namespace App\Repository;

use App\Entity\Civilite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Civilite>
 */
class CiviliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Civilite::class);
    }

    /**
     * Trouve toutes les civilités ordonnées
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les civilités actives ordonnées
     */
    public function findAllActiveOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réorganise les ordres automatiquement pour éviter les doublons
     */
    public function reorganizeOrdres(Civilite $civilite, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Si le nouvel ordre est différent de l'ancien
        $oldOrdre = $civilite->getOrdre();
        if ($oldOrdre !== $newOrdre) {
            
            // Décaler tous les éléments qui ont un ordre >= au nouvel ordre
            $qb = $entityManager->createQueryBuilder();
            $qb->update(Civilite::class, 'c')
               ->set('c.ordre', 'c.ordre + 1')
               ->where('c.ordre >= :newOrdre')
               ->andWhere('c.id != :currentId')
               ->setParameter('newOrdre', $newOrdre)
               ->setParameter('currentId', $civilite->getId());
            $qb->getQuery()->execute();
            
            // Définir le nouvel ordre
            $civilite->setOrdre($newOrdre);
            $entityManager->persist($civilite);
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
     * Trouve une civilité par son code
     */
    public function findByCode(string $code): ?Civilite
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un code de civilité existe déjà
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('c.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}