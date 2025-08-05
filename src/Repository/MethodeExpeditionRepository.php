<?php

namespace App\Repository;

use App\Entity\MethodeExpedition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MethodeExpedition>
 */
class MethodeExpeditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MethodeExpedition::class);
    }

    /**
     * Récupère toutes les méthodes d'expédition actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère la méthode d'expédition par défaut
     */
    public function findDefault(): ?MethodeExpedition
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.methodeParDefaut = :defaut')
            ->andWhere('m.actif = :actif')
            ->setParameter('defaut', true)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réorganise automatiquement les ordres après modification d'une méthode d'expédition
     * pour éviter les doublons et maintenir une séquence continue
     */
    public function reorganizeOrdres(MethodeExpedition $modifiedEntity, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Récupérer l'ancien ordre de l'entité modifiée
        $oldOrdre = $modifiedEntity->getOrdre();
        
        // Si pas de changement d'ordre, rien à faire
        if ($oldOrdre === $newOrdre) {
            return;
        }
        
        // Récupérer toutes les méthodes ordonnées (sauf celle modifiée)
        $allMethodes = $this->createQueryBuilder('m')
            ->andWhere('m.id != :excludeId')
            ->setParameter('excludeId', $modifiedEntity->getId())
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Créer un tableau temporaire pour réorganiser les ordres
        $reorderedMethodes = [];
        $targetPosition = $newOrdre - 1; // Convertir en index (base 0)
        
        // Parcourir les méthodes existantes et les placer dans le nouveau tableau
        $currentIndex = 0;
        foreach ($allMethodes as $methode) {
            // Si on atteint la position cible, insérer l'entité modifiée
            if ($currentIndex === $targetPosition) {
                $reorderedMethodes[] = $modifiedEntity;
                $currentIndex++;
            }
            
            // Ajouter la méthode courante
            $reorderedMethodes[] = $methode;
            $currentIndex++;
        }
        
        // Si la position cible est à la fin, ajouter l'entité modifiée
        if ($targetPosition >= count($allMethodes)) {
            $reorderedMethodes[] = $modifiedEntity;
        }
        
        // Réassigner les ordres de 1 à N
        foreach ($reorderedMethodes as $index => $methode) {
            $methode->setOrdre($index + 1);
        }
        
        // Persister les changements
        $entityManager->flush();
    }
}