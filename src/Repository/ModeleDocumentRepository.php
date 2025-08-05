<?php

namespace App\Repository;

use App\Entity\ModeleDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModeleDocument>
 */
class ModeleDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeleDocument::class);
    }

    /**
     * Récupère tous les modèles de document actifs
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
     * Récupère les modèles par type de document
     */
    public function findByType(string $typeDocument): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.typeDocument = :type')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', $typeDocument)
            ->setParameter('actif', true)
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le modèle par défaut pour un type de document
     */
    public function findDefaultForType(string $typeDocument): ?ModeleDocument
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.typeDocument = :type')
            ->andWhere('m.modeleParDefaut = :defaut')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', $typeDocument)
            ->setParameter('defaut', true)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réorganise automatiquement les ordres après modification d'un modèle de document
     * pour éviter les doublons et maintenir une séquence continue
     */
    public function reorganizeOrdres(ModeleDocument $modifiedEntity, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Récupérer l'ancien ordre de l'entité modifiée
        $oldOrdre = $modifiedEntity->getOrdre();
        
        // Si pas de changement d'ordre, rien à faire
        if ($oldOrdre === $newOrdre) {
            return;
        }
        
        // Récupérer tous les modèles ordonnés (sauf celui modifié)
        $allModeles = $this->createQueryBuilder('m')
            ->andWhere('m.id != :excludeId')
            ->setParameter('excludeId', $modifiedEntity->getId())
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Créer un tableau temporaire pour réorganiser les ordres
        $reorderedModeles = [];
        $targetPosition = $newOrdre - 1; // Convertir en index (base 0)
        
        // Parcourir les modèles existants et les placer dans le nouveau tableau
        $currentIndex = 0;
        foreach ($allModeles as $modele) {
            // Si on atteint la position cible, insérer l'entité modifiée
            if ($currentIndex === $targetPosition) {
                $reorderedModeles[] = $modifiedEntity;
                $currentIndex++;
            }
            
            // Ajouter le modèle courant
            $reorderedModeles[] = $modele;
            $currentIndex++;
        }
        
        // Si la position cible est à la fin, ajouter l'entité modifiée
        if ($targetPosition >= count($allModeles)) {
            $reorderedModeles[] = $modifiedEntity;
        }
        
        // Réassigner les ordres de 1 à N
        foreach ($reorderedModeles as $index => $modele) {
            $modele->setOrdre($index + 1);
        }
        
        // Persister les changements
        $entityManager->flush();
    }
}