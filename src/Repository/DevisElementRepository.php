<?php

namespace App\Repository;

use App\Entity\Devis;
use App\Entity\DevisElement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevisElement>
 */
class DevisElementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevisElement::class);
    }

    /**
     * Trouve tous les éléments d'un devis triés par position
     *
     * @param Devis $devis
     * @return DevisElement[]
     */
    public function findByDevisOrdered(Devis $devis): array
    {
        return $this->createQueryBuilder('de')
            ->andWhere('de.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('de.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les éléments produit d'un devis
     *
     * @param Devis $devis
     * @return DevisElement[]
     */
    public function findProductElementsByDevis(Devis $devis): array
    {
        return $this->createQueryBuilder('de')
            ->andWhere('de.devis = :devis')
            ->andWhere('de.type = :type')
            ->setParameter('devis', $devis)
            ->setParameter('type', 'product')
            ->orderBy('de.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les éléments de mise en page d'un devis
     *
     * @param Devis $devis
     * @return DevisElement[]
     */
    public function findLayoutElementsByDevis(Devis $devis): array
    {
        return $this->createQueryBuilder('de')
            ->andWhere('de.devis = :devis')
            ->andWhere('de.type IN (:types)')
            ->setParameter('devis', $devis)
            ->setParameter('types', ['line_break', 'page_break', 'subtotal', 'section_title', 'separator'])
            ->orderBy('de.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la prochaine position disponible pour un devis
     *
     * @param Devis $devis
     * @return int
     */
    public function getNextPositionForDevis(Devis $devis): int
    {
        $maxPosition = $this->createQueryBuilder('de')
            ->select('MAX(de.position)')
            ->andWhere('de.devis = :devis')
            ->setParameter('devis', $devis)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Réorganise les positions des éléments d'un devis
     *
     * @param Devis $devis
     * @param array $elementIds Array des IDs dans l'ordre voulu
     * @return int Nombre d'éléments mis à jour
     */
    public function updateElementsOrder(Devis $devis, array $elementIds): int
    {
        $updated = 0;
        
        foreach ($elementIds as $position => $elementId) {
            $element = $this->find($elementId);
            if ($element && $element->getDevis()->getId() === $devis->getId()) {
                $element->setPosition($position + 1); // Position 1-indexed
                $this->getEntityManager()->persist($element);
                $updated++;
            }
        }
        
        $this->getEntityManager()->flush();
        
        return $updated;
    }

    /**
     * Insère un élément à une position donnée et décale les suivants
     *
     * @param Devis $devis
     * @param int $position Position d'insertion (1-indexed)
     * @return void
     */
    public function makeSpaceAtPosition(Devis $devis, int $position): void
    {
        $this->createQueryBuilder('de')
            ->update()
            ->set('de.position', 'de.position + 1')
            ->andWhere('de.devis = :devis')
            ->andWhere('de.position >= :position')
            ->setParameter('devis', $devis)
            ->setParameter('position', $position)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime un élément et compacte les positions
     *
     * @param DevisElement $element
     * @return void
     */
    public function removeAndCompact(DevisElement $element): void
    {
        $devis = $element->getDevis();
        $position = $element->getPosition();
        
        // Supprimer l'élément
        $this->getEntityManager()->remove($element);
        
        // Décaler les éléments suivants
        $this->createQueryBuilder('de')
            ->update()
            ->set('de.position', 'de.position - 1')
            ->andWhere('de.devis = :devis')
            ->andWhere('de.position > :position')
            ->setParameter('devis', $devis)
            ->setParameter('position', $position)
            ->getQuery()
            ->execute();
            
        $this->getEntityManager()->flush();
    }

    /**
     * Calcule le sous-total jusqu'à une position donnée
     *
     * @param Devis $devis
     * @param int $upToPosition
     * @return float
     */
    public function calculateSubtotalUpTo(Devis $devis, int $upToPosition): float
    {
        $result = $this->createQueryBuilder('de')
            ->select('SUM(CAST(de.totalLigneHt AS DECIMAL(10,2)))')
            ->andWhere('de.devis = :devis')
            ->andWhere('de.type = :type')
            ->andWhere('de.position < :position')
            ->setParameter('devis', $devis)
            ->setParameter('type', 'product')
            ->setParameter('position', $upToPosition)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}