<?php

namespace App\Repository;

use App\Entity\Transporteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transporteur>
 */
class TransporteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transporteur::class);
    }

    /**
     * Trouve tous les transporteurs ordonnés
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
     * Trouve tous les transporteurs actifs ordonnés
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
     * Réorganise les ordres automatiquement pour éviter les doublons
     */
    public function reorganizeOrdres(Transporteur $transporteur, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Si le nouvel ordre est différent de l'ancien
        $oldOrdre = $transporteur->getOrdre();
        if ($oldOrdre !== $newOrdre) {
            
            // Décaler tous les éléments qui ont un ordre >= au nouvel ordre
            $qb = $entityManager->createQueryBuilder();
            $qb->update(Transporteur::class, 't')
               ->set('t.ordre', 't.ordre + 1')
               ->where('t.ordre >= :newOrdre')
               ->andWhere('t.id != :currentId')
               ->setParameter('newOrdre', $newOrdre)
               ->setParameter('currentId', $transporteur->getId());
            $qb->getQuery()->execute();
            
            // Définir le nouvel ordre
            $transporteur->setOrdre($newOrdre);
            $entityManager->persist($transporteur);
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
     * Trouve un transporteur par son code
     */
    public function findByCode(string $code): ?Transporteur
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un code de transporteur existe déjà
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('t.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
