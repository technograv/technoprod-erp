<?php

namespace App\Repository;

use App\Entity\ModePaiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModePaiement>
 */
class ModePaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModePaiement::class);
    }

    /**
     * Récupère tous les modes de paiement triés par ordre
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.ordre', 'ASC')
            ->addOrderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère tous les modes de paiement actifs
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
     * Récupère le mode de paiement par défaut
     */
    public function findDefault(): ?ModePaiement
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.modePaiementParDefaut = :defaut')
            ->andWhere('m.actif = :actif')
            ->setParameter('defaut', true)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un mode de paiement par son code
     */
    public function findByCode(string $code): ?ModePaiement
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réorganise automatiquement les ordres après modification d'un mode de paiement
     * pour éviter les doublons et maintenir une séquence continue
     */
    public function reorganizeOrdres(ModePaiement $modifiedEntity, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Récupérer l'ancien ordre de l'entité modifiée
        $oldOrdre = $modifiedEntity->getOrdre();
        
        // Si pas de changement d'ordre, rien à faire
        if ($oldOrdre === $newOrdre) {
            return;
        }
        
        // Récupérer tous les modes ordonnés (sauf celui modifié)
        $allModes = $this->createQueryBuilder('m')
            ->andWhere('m.id != :excludeId')
            ->setParameter('excludeId', $modifiedEntity->getId())
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Créer un tableau temporaire pour réorganiser les ordres
        $reorderedModes = [];
        $targetPosition = $newOrdre - 1; // Convertir en index (base 0)
        
        // Parcourir les modes existants et les placer dans le nouveau tableau
        $currentIndex = 0;
        foreach ($allModes as $mode) {
            // Si on atteint la position cible, insérer l'entité modifiée
            if ($currentIndex === $targetPosition) {
                $reorderedModes[] = $modifiedEntity;
                $currentIndex++;
            }
            
            // Ajouter le mode courant
            $reorderedModes[] = $mode;
            $currentIndex++;
        }
        
        // Si la position cible est à la fin, ajouter l'entité modifiée
        if ($targetPosition >= count($allModes)) {
            $reorderedModes[] = $modifiedEntity;
        }
        
        // Réassigner les ordres de 1 à N
        foreach ($reorderedModes as $index => $mode) {
            $mode->setOrdre($index + 1);
        }
        
        // Persister les changements
        $entityManager->flush();
    }
}