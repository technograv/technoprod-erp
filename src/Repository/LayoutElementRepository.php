<?php

namespace App\Repository;

use App\Entity\LayoutElement;
use App\Entity\Devis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LayoutElement>
 */
class LayoutElementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LayoutElement::class);
    }

    /**
     * Trouve tous les éléments de mise en page d'un devis, triés par ordre d'affichage
     *
     * @param Devis $devis
     * @return LayoutElement[]
     */
    public function findByDevisOrdered(Devis $devis): array
    {
        return $this->createQueryBuilder('le')
            ->andWhere('le.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('le.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les éléments de mise en page d'un devis d'un type donné
     *
     * @param Devis $devis
     * @param string $type
     * @return LayoutElement[]
     */
    public function findByDevisAndType(Devis $devis, string $type): array
    {
        return $this->createQueryBuilder('le')
            ->andWhere('le.devis = :devis')
            ->andWhere('le.type = :type')
            ->setParameter('devis', $devis)
            ->setParameter('type', $type)
            ->orderBy('le.ordreAffichage', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le prochain ordre d'affichage disponible pour un devis
     *
     * @param Devis $devis
     * @return int
     */
    public function getNextOrderForDevis(Devis $devis): int
    {
        $maxOrder = $this->createQueryBuilder('le')
            ->select('MAX(le.ordreAffichage)')
            ->andWhere('le.devis = :devis')
            ->setParameter('devis', $devis)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Réorganise l'ordre des éléments de mise en page après insertion
     *
     * @param Devis $devis
     * @param int $insertPosition
     * @return int Nombre d'éléments réorganisés
     */
    public function reorganizeOrderAfterInsert(Devis $devis, int $insertPosition): int
    {
        return $this->createQueryBuilder('le')
            ->update()
            ->set('le.ordreAffichage', 'le.ordreAffichage + 1')
            ->set('le.updatedAt', ':now')
            ->andWhere('le.devis = :devis')
            ->andWhere('le.ordreAffichage >= :position')
            ->setParameter('devis', $devis)
            ->setParameter('position', $insertPosition)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Réorganise l'ordre des éléments après suppression
     *
     * @param Devis $devis
     * @param int $deletedPosition
     * @return int Nombre d'éléments réorganisés
     */
    public function reorganizeOrderAfterDelete(Devis $devis, int $deletedPosition): int
    {
        return $this->createQueryBuilder('le')
            ->update()
            ->set('le.ordreAffichage', 'le.ordreAffichage - 1')
            ->set('le.updatedAt', ':now')
            ->andWhere('le.devis = :devis')
            ->andWhere('le.ordreAffichage > :position')
            ->setParameter('devis', $devis)
            ->setParameter('position', $deletedPosition)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Met à jour les ordres d'affichage selon un tableau de positions
     *
     * @param Devis $devis
     * @param array $newOrders Array avec id => nouvelOrdre
     * @return int Nombre d'éléments mis à jour
     */
    public function updateOrdersFromArray(Devis $devis, array $newOrders): int
    {
        $updated = 0;
        $now = new \DateTimeImmutable();

        foreach ($newOrders as $elementId => $newOrder) {
            $result = $this->createQueryBuilder('le')
                ->update()
                ->set('le.ordreAffichage', ':newOrder')
                ->set('le.updatedAt', ':now')
                ->andWhere('le.id = :id')
                ->andWhere('le.devis = :devis')
                ->setParameter('newOrder', $newOrder)
                ->setParameter('now', $now)
                ->setParameter('id', $elementId)
                ->setParameter('devis', $devis)
                ->getQuery()
                ->execute();
            
            $updated += $result;
        }

        return $updated;
    }

    /**
     * Supprime tous les éléments de mise en page d'un devis
     *
     * @param Devis $devis
     * @return int Nombre d'éléments supprimés
     */
    public function deleteAllForDevis(Devis $devis): int
    {
        return $this->createQueryBuilder('le')
            ->delete()
            ->andWhere('le.devis = :devis')
            ->setParameter('devis', $devis)
            ->getQuery()
            ->execute();
    }

    /**
     * Compte le nombre d'éléments de mise en page par type pour un devis
     *
     * @param Devis $devis
     * @return array Associatif type => count
     */
    public function countByTypeForDevis(Devis $devis): array
    {
        $results = $this->createQueryBuilder('le')
            ->select('le.type, COUNT(le.id) as count')
            ->andWhere('le.devis = :devis')
            ->setParameter('devis', $devis)
            ->groupBy('le.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['type']] = (int) $result['count'];
        }

        return $counts;
    }

    public function save(LayoutElement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LayoutElement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}