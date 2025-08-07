<?php

namespace App\Repository;

use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Societe>
 *
 * @method Societe|null find($id, $lockMode = null, $lockVersion = null)
 * @method Societe|null findOneBy(array $criteria, array $orderBy = null)
 * @method Societe[]    findAll()
 * @method Societe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SocieteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Societe::class);
    }

    /**
     * Trouve toutes les sociétés mères
     */
    public function findSocietesMeres(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', 'mere')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les sociétés filles d'une société mère
     */
    public function findSocietesFilles(Societe $societeParent): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.societeParent = :parent')
            ->andWhere('s.type = :type')
            ->setParameter('parent', $societeParent)
            ->setParameter('type', 'fille')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une société mère avec toutes ses filles
     */
    public function findSocieteMereWithFilles(int $id): ?Societe
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.societesFilles', 'f')
            ->addSelect('f')
            ->andWhere('s.id = :id')
            ->andWhere('s.type = :type')
            ->setParameter('id', $id)
            ->setParameter('type', 'mere')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les sociétés actives
     */
    public function findActiveSocietes(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.active = true')
            ->orderBy('s.type', 'ASC') // Mères d'abord
            ->addOrderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une société par son nom (insensible à la casse)
     */
    public function findByNomIgnoreCase(string $nom): ?Societe
    {
        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte les sociétés par type
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.type, COUNT(s.id) as count')
            ->groupBy('s.type')
            ->getQuery()
            ->getResult();

        $counts = ['mere' => 0, 'fille' => 0];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Recherche de sociétés par nom (pour autocomplete)
     */
    public function searchByNom(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nom LIKE :term')
            ->andWhere('s.active = true')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('s.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}