<?php

namespace App\Repository;

use App\Entity\ModeReglement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModeReglement>
 */
class ModeReglementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeReglement::class);
    }

    /**
     * Récupère tous les modes de règlement actifs
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
     * Récupère le mode de règlement par défaut
     */
    public function findDefault(): ?ModeReglement
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.modeParDefaut = :defaut')
            ->andWhere('m.actif = :actif')
            ->setParameter('defaut', true)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un mode de règlement par son code
     */
    public function findByCode(string $code): ?ModeReglement
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère tous les modes de règlement avec leur mode de paiement
     */
    public function findAllWithModePaiement(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.modePaiement', 'mp')
            ->addSelect('mp')
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les modes de règlement par type
     */
    public function findByType(string $typeReglement): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.typeReglement = :type')
            ->andWhere('m.actif = :actif')
            ->setParameter('type', $typeReglement)
            ->setParameter('actif', true)
            ->orderBy('m.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réorganise automatiquement les ordres après modification d'un mode de règlement
     * pour éviter les doublons et maintenir une séquence continue
     */
    public function reorganizeOrdres(ModeReglement $modifiedEntity, int $newOrdre): void
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