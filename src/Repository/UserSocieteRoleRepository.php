<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Societe;
use App\Entity\UserSocieteRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSocieteRole>
 *
 * @method UserSocieteRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserSocieteRole|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserSocieteRole[]    findAll()
 * @method UserSocieteRole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserSocieteRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSocieteRole::class);
    }

    /**
     * Trouve les rôles d'un utilisateur dans toutes les sociétés
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('usr')
            ->leftJoin('usr.societe', 's')
            ->addSelect('s')
            ->andWhere('usr.user = :user')
            ->andWhere('usr.active = true')
            ->setParameter('user', $user)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs d'une société avec leurs rôles
     */
    public function findBySociete(Societe $societe): array
    {
        return $this->createQueryBuilder('usr')
            ->leftJoin('usr.user', 'u')
            ->addSelect('u')
            ->andWhere('usr.societe = :societe')
            ->andWhere('usr.active = true')
            ->setParameter('societe', $societe)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un rôle spécifique utilisateur/société
     */
    public function findUserRoleInSociete(User $user, Societe $societe): ?UserSocieteRole
    {
        return $this->createQueryBuilder('usr')
            ->andWhere('usr.user = :user')
            ->andWhere('usr.societe = :societe')
            ->andWhere('usr.active = true')
            ->setParameter('user', $user)
            ->setParameter('societe', $societe)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte les utilisateurs par rôle dans une société
     */
    public function countUsersByRoleInSociete(Societe $societe): array
    {
        $result = $this->createQueryBuilder('usr')
            ->select('usr.role, COUNT(usr.id) as count')
            ->andWhere('usr.societe = :societe')
            ->andWhere('usr.active = true')
            ->setParameter('societe', $societe)
            ->groupBy('usr.role')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['role']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Trouve les administrateurs d'une société
     */
    public function findAdminsBySociete(Societe $societe): array
    {
        return $this->createQueryBuilder('usr')
            ->leftJoin('usr.user', 'u')
            ->addSelect('u')
            ->andWhere('usr.societe = :societe')
            ->andWhere('usr.role = :role')
            ->andWhere('usr.active = true')
            ->setParameter('societe', $societe)
            ->setParameter('role', UserSocieteRole::ROLE_ADMIN)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a un rôle spécifique dans une société
     */
    public function hasRole(User $user, Societe $societe, string $role): bool
    {
        $count = $this->createQueryBuilder('usr')
            ->select('COUNT(usr.id)')
            ->andWhere('usr.user = :user')
            ->andWhere('usr.societe = :societe')
            ->andWhere('usr.role = :role')
            ->andWhere('usr.active = true')
            ->setParameter('user', $user)
            ->setParameter('societe', $societe)
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve toutes les sociétés accessibles par un utilisateur
     */
    public function findAccessibleSocietesByUser(User $user): array
    {
        return $this->createQueryBuilder('usr')
            ->select('s')
            ->join('usr.societe', 's')
            ->andWhere('usr.user = :user')
            ->andWhere('usr.active = true')
            ->andWhere('s.active = true')
            ->setParameter('user', $user)
            ->orderBy('s.ordre', 'ASC') // Ordre personnalisé d'abord
            ->addOrderBy('s.type', 'DESC') // Puis mères en premier
            ->addOrderBy('s.nom', 'ASC') // Puis nom alphabétique
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime tous les rôles d'un utilisateur dans une société
     */
    public function removeAllUserRolesInSociete(User $user, Societe $societe): void
    {
        $this->createQueryBuilder('usr')
            ->delete()
            ->andWhere('usr.user = :user')
            ->andWhere('usr.societe = :societe')
            ->setParameter('user', $user)
            ->setParameter('societe', $societe)
            ->getQuery()
            ->execute();
    }

    /**
     * Statistiques globales des rôles
     */
    public function getRoleStatistics(): array
    {
        return $this->createQueryBuilder('usr')
            ->select('usr.role, COUNT(DISTINCT usr.user) as user_count, COUNT(DISTINCT usr.societe) as societe_count')
            ->andWhere('usr.active = true')
            ->groupBy('usr.role')
            ->orderBy('user_count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}