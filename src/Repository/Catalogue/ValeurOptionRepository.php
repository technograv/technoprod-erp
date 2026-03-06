<?php

namespace App\Repository\Catalogue;

use App\Entity\Catalogue\ValeurOption;
use App\Entity\Catalogue\OptionProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ValeurOption>
 */
class ValeurOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ValeurOption::class);
    }

    /**
     * Trouve toutes les valeurs d'une option triées par ordre
     *
     * @return ValeurOption[]
     */
    public function findByOptionOrdered(OptionProduit $option): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.option = :option')
            ->setParameter('option', $option)
            ->orderBy('v.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les valeurs disponibles d'une option
     *
     * @return ValeurOption[]
     */
    public function findDisponibles(OptionProduit $option): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.option = :option')
            ->andWhere('v.disponible = :disponible')
            ->setParameter('option', $option)
            ->setParameter('disponible', true)
            ->orderBy('v.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la valeur par défaut d'une option
     */
    public function findParDefaut(OptionProduit $option): ?ValeurOption
    {
        return $this->createQueryBuilder('v')
            ->where('v.option = :option')
            ->andWhere('v.parDefaut = :defaut')
            ->setParameter('option', $option)
            ->setParameter('defaut', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les valeurs en stock
     *
     * @return ValeurOption[]
     */
    public function findEnStock(OptionProduit $option): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.option = :option')
            ->andWhere('v.stock IS NULL OR v.stock > 0')
            ->setParameter('option', $option)
            ->orderBy('v.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
