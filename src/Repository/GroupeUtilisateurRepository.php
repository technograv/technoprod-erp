<?php

namespace App\Repository;

use App\Entity\GroupeUtilisateur;
use App\Entity\Societe;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupeUtilisateur>
 */
class GroupeUtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupeUtilisateur::class);
    }

    /**
     * Trouve tous les groupes actifs ordonnés
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('g.ordre', 'ASC')
            ->addOrderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les groupes (actifs et inactifs) ordonnés
     */
    public function findAllOrderedWithInactive(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.ordre', 'ASC')
            ->addOrderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les groupes racines (sans parent) ordonnés
     */
    public function findGroupesRacines(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.parent IS NULL')
            ->andWhere('g.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('g.ordre', 'ASC')
            ->addOrderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les enfants d'un groupe donné
     */
    public function findEnfantsOf(GroupeUtilisateur $parent): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.parent = :parent')
            ->andWhere('g.actif = :actif')
            ->setParameter('parent', $parent)
            ->setParameter('actif', true)
            ->orderBy('g.ordre', 'ASC')
            ->addOrderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les groupes qui ont accès à une société donnée
     */
    public function findGroupesWithAccessToSociete(Societe $societe): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.societes', 's')
            ->where('s = :societe')
            ->andWhere('g.actif = :actif')
            ->setParameter('societe', $societe)
            ->setParameter('actif', true)
            ->orderBy('g.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les groupes d'un utilisateur
     */
    public function findGroupesOfUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.utilisateurs', 'u')
            ->where('u = :user')
            ->andWhere('g.actif = :actif')
            ->setParameter('user', $user)
            ->setParameter('actif', true)
            ->orderBy('g.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les groupes avec une permission spécifique
     */
    public function findGroupesWithPermission(string $permission): array
    {
        return $this->createQueryBuilder('g')
            ->where('JSON_CONTAINS(g.permissions, :permission) = 1')
            ->andWhere('g.actif = :actif')
            ->setParameter('permission', json_encode($permission))
            ->setParameter('actif', true)
            ->orderBy('g.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques sur les groupes
     */
    public function getStatistiques(): array
    {
        $qb = $this->createQueryBuilder('g');
        
        $total = (int) $qb->select('COUNT(g.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('g');
        $actifs = (int) $qb->select('COUNT(g.id)')
            ->where('g.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('g');
        $racines = (int) $qb->select('COUNT(g.id)')
            ->where('g.parent IS NULL')
            ->andWhere('g.actif = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'actifs' => $actifs,
            'inactifs' => $total - $actifs,
            'racines' => $racines,
            'enfants' => $actifs - $racines
        ];
    }

    /**
     * Réorganise les ordres pour éviter les doublons
     */
    public function reorganizeOrdres(): void
    {
        $groupes = $this->createQueryBuilder('g')
            ->orderBy('g.ordre', 'ASC')
            ->addOrderBy('g.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $ordre = 1;
        foreach ($groupes as $groupe) {
            if ($groupe->getOrdre() !== $ordre) {
                $groupe->setOrdre($ordre);
                $this->getEntityManager()->persist($groupe);
            }
            $ordre++;
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Trouve le prochain ordre disponible
     */
    public function getNextOrdre(): int
    {
        $qb = $this->createQueryBuilder('g');
        $maxOrdre = (int) $qb->select('MAX(g.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        return $maxOrdre + 1;
    }

    /**
     * Recherche textuelle dans les groupes
     */
    public function searchGroupes(string $search): array
    {
        return $this->createQueryBuilder('g')
            ->where('LOWER(g.nom) LIKE :search')
            ->orWhere('LOWER(g.description) LIKE :search')
            ->setParameter('search', '%' . strtolower($search) . '%')
            ->orderBy('g.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les groupes avec le plus d'utilisateurs
     */
    public function findGroupesWithMostUsers(int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->select('g, COUNT(u.id) as HIDDEN userCount')
            ->leftJoin('g.utilisateurs', 'u')
            ->where('g.actif = :actif')
            ->setParameter('actif', true)
            ->groupBy('g.id')
            ->orderBy('userCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
