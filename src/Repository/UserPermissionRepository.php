<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Societe;
use App\Entity\UserPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPermission>
 */
class UserPermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPermission::class);
    }

    /**
     * Trouve les permissions d'un utilisateur dans une société
     */
    public function findByUserAndSociete(User $user, Societe $societe): ?UserPermission
    {
        return $this->createQueryBuilder('up')
            ->where('up.user = :user')
            ->andWhere('up.societe = :societe')
            ->andWhere('up.actif = true')
            ->setParameter('user', $user)
            ->setParameter('societe', $societe)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les permissions d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('up')
            ->leftJoin('up.societe', 's')
            ->addSelect('s')
            ->where('up.user = :user')
            ->andWhere('up.actif = true')
            ->setParameter('user', $user)
            ->orderBy('up.niveau', 'DESC')
            ->addOrderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les permissions d'une société
     */
    public function findBySociete(Societe $societe): array
    {
        return $this->createQueryBuilder('up')
            ->leftJoin('up.user', 'u')
            ->addSelect('u')
            ->where('up.societe = :societe')
            ->andWhere('up.actif = true')
            ->setParameter('societe', $societe)
            ->orderBy('up.niveau', 'DESC')
            ->addOrderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs avec une permission spécifique dans une société
     */
    public function findUsersWithPermissionInSociete(string $permission, Societe $societe): array
    {
        return $this->createQueryBuilder('up')
            ->leftJoin('up.user', 'u')
            ->addSelect('u')
            ->where('up.societe = :societe')
            ->andWhere('JSON_CONTAINS(up.permissions, :permission) = 1')
            ->andWhere('up.actif = true')
            ->setParameter('societe', $societe)
            ->setParameter('permission', json_encode($permission))
            ->orderBy('up.niveau', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les permissions disponibles (combiné avec les groupes)
     */
    public function getAllUserPermissionsInSociete(User $user, Societe $societe): array
    {
        // Permissions individuelles
        $userPermission = $this->findByUserAndSociete($user, $societe);
        $individualPermissions = $userPermission ? $userPermission->getPermissions() : [];

        // Permissions des groupes (via les méthodes de User)
        $groupPermissions = $user->getPermissionsViaGroupes();

        // Combiner et dédupliquer
        return array_unique(array_merge($individualPermissions, $groupPermissions));
    }

    /**
     * Récupère le niveau maximum d'un utilisateur dans une société
     */
    public function getUserMaxLevelInSociete(User $user, Societe $societe): int
    {
        $maxLevel = 0;

        // Niveau via permission individuelle
        $userPermission = $this->findByUserAndSociete($user, $societe);
        if ($userPermission) {
            $maxLevel = max($maxLevel, $userPermission->getNiveau());
        }

        // Niveau via groupes (via la méthode de User)
        $maxLevel = max($maxLevel, $user->getMaxGroupLevel());

        return $maxLevel;
    }

    /**
     * Statistiques des permissions par société
     */
    public function getPermissionStatsBySociete(Societe $societe): array
    {
        $result = $this->createQueryBuilder('up')
            ->select('up.niveau, COUNT(up.id) as count')
            ->where('up.societe = :societe')
            ->andWhere('up.actif = true')
            ->setParameter('societe', $societe)
            ->groupBy('up.niveau')
            ->orderBy('up.niveau', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['niveau']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Supprime ou désactive les permissions d'un utilisateur dans une société
     */
    public function removeUserPermissionsInSociete(User $user, Societe $societe): void
    {
        $this->createQueryBuilder('up')
            ->update()
            ->set('up.actif', ':actif')
            ->where('up.user = :user')
            ->andWhere('up.societe = :societe')
            ->setParameter('actif', false)
            ->setParameter('user', $user)
            ->setParameter('societe', $societe)
            ->getQuery()
            ->execute();
    }

    /**
     * Recherche textuelle dans les permissions
     */
    public function searchPermissions(string $search, ?Societe $societe = null): array
    {
        $qb = $this->createQueryBuilder('up')
            ->leftJoin('up.user', 'u')
            ->leftJoin('up.societe', 's')
            ->addSelect('u', 's')
            ->where('up.actif = true')
            ->andWhere(
                'LOWER(u.nom) LIKE :search OR ' .
                'LOWER(u.prenom) LIKE :search OR ' .
                'LOWER(s.nom) LIKE :search OR ' .
                'LOWER(up.notes) LIKE :search'
            )
            ->setParameter('search', '%' . strtolower($search) . '%');

        if ($societe) {
            $qb->andWhere('up.societe = :societe')
               ->setParameter('societe', $societe);
        }

        return $qb->orderBy('up.niveau', 'DESC')
                  ->addOrderBy('u.nom', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}