<?php

namespace App\Repository;

use App\Entity\FormeJuridique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormeJuridique>
 */
class FormeJuridiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormeJuridique::class);
    }

    /**
     * Récupère toutes les formes juridiques triées par ordre
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les formes juridiques actives
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère la forme juridique "Particulier"
     */
    public function findParticulier(): ?FormeJuridique
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.nom = :nom')
            ->setParameter('nom', 'Particulier')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réorganise automatiquement les ordres après modification d'une forme juridique
     * pour éviter les doublons et maintenir une séquence continue
     */
    public function reorganizeOrdres(FormeJuridique $modifiedEntity, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Récupérer l'ancien ordre de l'entité modifiée
        $oldOrdre = $modifiedEntity->getOrdre();
        
        // Si pas de changement d'ordre, rien à faire
        if ($oldOrdre === $newOrdre) {
            return;
        }
        
        // Récupérer toutes les formes juridiques ordonnées (sauf celle modifiée)
        $allFormes = $this->createQueryBuilder('f')
            ->andWhere('f.id != :excludeId')
            ->setParameter('excludeId', $modifiedEntity->getId())
            ->orderBy('f.ordre', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Créer un tableau temporaire pour réorganiser les ordres
        $reorderedFormes = [];
        $targetPosition = $newOrdre - 1; // Convertir en index (base 0)
        
        // Parcourir les formes existantes et les placer dans le nouveau tableau
        $currentIndex = 0;
        foreach ($allFormes as $forme) {
            // Si on atteint la position cible, insérer l'entité modifiée
            if ($currentIndex === $targetPosition) {
                $reorderedFormes[] = $modifiedEntity;
                $currentIndex++;
            }
            
            // Ajouter la forme courante
            $reorderedFormes[] = $forme;
            $currentIndex++;
        }
        
        // Si la position cible est à la fin, ajouter l'entité modifiée
        if ($targetPosition >= count($allFormes)) {
            $reorderedFormes[] = $modifiedEntity;
        }
        
        // Réassigner les ordres de 1 à N
        foreach ($reorderedFormes as $index => $forme) {
            $forme->setOrdre($index + 1);
        }
        
        // Persister les changements
        $entityManager->flush();
    }
}
