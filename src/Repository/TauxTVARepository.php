<?php

namespace App\Repository;

use App\Entity\TauxTVA;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TauxTVA>
 */
class TauxTVARepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TauxTVA::class);
    }

    /**
     * Trouve tous les taux de TVA ordonnés
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les taux de TVA actifs ordonnés
     */
    public function findAllActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le taux de TVA par défaut
     */
    public function findDefault(): ?TauxTVA
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.parDefaut = :defaut')
            ->setParameter('defaut', true)
            ->andWhere('t.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réorganise les ordres automatiquement pour éviter les doublons
     */
    public function reorganizeOrdres(TauxTVA $tauxTVA, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Si le nouvel ordre est différent de l'ancien
        $oldOrdre = $tauxTVA->getOrdre();
        if ($oldOrdre !== $newOrdre) {
            
            // Décaler tous les éléments qui ont un ordre >= au nouvel ordre
            $qb = $entityManager->createQueryBuilder();
            $qb->update(TauxTVA::class, 't')
               ->set('t.ordre', 't.ordre + 1')
               ->where('t.ordre >= :newOrdre')
               ->andWhere('t.id != :currentId')
               ->setParameter('newOrdre', $newOrdre)
               ->setParameter('currentId', $tauxTVA->getId());
            $qb->getQuery()->execute();
            
            // Définir le nouvel ordre
            $tauxTVA->setOrdre($newOrdre);
            $entityManager->persist($tauxTVA);
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
     * Définit un nouveau taux par défaut (désactive les autres)
     */
    public function setAsDefault(TauxTVA $tauxTVA): void
    {
        $entityManager = $this->getEntityManager();
        
        // Désactiver tous les autres par défaut
        $qb = $entityManager->createQueryBuilder();
        $qb->update(TauxTVA::class, 't')
           ->set('t.parDefaut', 'false')
           ->where('t.id != :currentId')
           ->setParameter('currentId', $tauxTVA->getId());
        $qb->getQuery()->execute();
        
        // Activer celui-ci
        $tauxTVA->setParDefaut(true);
        $entityManager->persist($tauxTVA);
        $entityManager->flush();
    }
}
