<?php

namespace App\Repository;

use App\Entity\FraisPort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FraisPort>
 */
class FraisPortRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FraisPort::class);
    }

    /**
     * Trouve tous les frais de port ordonnés avec leurs relations
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.tauxTva', 't')
            ->leftJoin('f.transporteur', 'tr')
            ->leftJoin('f.paliers', 'p')
            ->addSelect('t', 'tr', 'p')
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les frais de port actifs ordonnés
     */
    public function findAllActiveOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.tauxTva', 't')
            ->leftJoin('f.transporteur', 'tr')
            ->addSelect('t', 'tr')
            ->andWhere('f.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un frais de port avec ses paliers
     */
    public function findWithPaliers(int $id): ?FraisPort
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.paliers', 'p')
            ->leftJoin('f.tauxTva', 't')
            ->leftJoin('f.transporteur', 'tr')
            ->addSelect('p', 't', 'tr')
            ->andWhere('f.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réorganise les ordres automatiquement pour éviter les doublons
     */
    public function reorganizeOrdres(FraisPort $fraisPort, int $newOrdre): void
    {
        $entityManager = $this->getEntityManager();
        
        // Si le nouvel ordre est différent de l'ancien
        $oldOrdre = $fraisPort->getOrdre();
        if ($oldOrdre !== $newOrdre) {
            
            // Décaler tous les éléments qui ont un ordre >= au nouvel ordre
            $qb = $entityManager->createQueryBuilder();
            $qb->update(FraisPort::class, 'f')
               ->set('f.ordre', 'f.ordre + 1')
               ->where('f.ordre >= :newOrdre')
               ->andWhere('f.id != :currentId')
               ->setParameter('newOrdre', $newOrdre)
               ->setParameter('currentId', $fraisPort->getId());
            $qb->getQuery()->execute();
            
            // Définir le nouvel ordre
            $fraisPort->setOrdre($newOrdre);
            $entityManager->persist($fraisPort);
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
        
        // Récupérer tous les éléments ordonnés (sans les relations pour optimiser)
        $elements = $this->createQueryBuilder('f')
            ->orderBy('f.ordre', 'ASC')
            ->addOrderBy('f.nom', 'ASC')
            ->getQuery()
            ->getResult();
        
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
     * Trouve un frais de port par son code
     */
    public function findByCode(string $code): ?FraisPort
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un code de frais de port existe déjà
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('f.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Trouve les frais de port par mode de calcul
     */
    public function findByModeCalcul(string $modeCalcul): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.modeCalcul = :mode')
            ->andWhere('f.actif = :actif')
            ->setParameter('mode', $modeCalcul)
            ->setParameter('actif', true)
            ->orderBy('f.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les frais de port avec paliers
     */
    public function findWithPaliersActifs(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.paliers', 'p')
            ->addSelect('p')
            ->andWhere('f.actif = :actif')
            ->andWhere('f.modeCalcul LIKE :palier')
            ->setParameter('actif', true)
            ->setParameter('palier', 'palier_%')
            ->orderBy('f.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
