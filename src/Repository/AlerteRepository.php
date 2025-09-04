<?php

namespace App\Repository;

use App\Entity\Alerte;
use App\Entity\AlerteUtilisateur;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alerte>
 */
class AlerteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alerte::class);
    }

    /**
     * Réorganise les ordres des alertes pour éviter les doublons
     */
    public function reorganizeOrdres(int $nouvelOrdre): void
    {
        // Récupérer toutes les alertes avec un ordre >= au nouveau
        $alertes = $this->createQueryBuilder('a')
            ->where('a.ordre >= :ordre')
            ->setParameter('ordre', $nouvelOrdre)
            ->orderBy('a.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        // Décaler tous les ordres de +1
        foreach ($alertes as $alerte) {
            $alerte->setOrdre($alerte->getOrdre() + 1);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Récupère les alertes actives pour un utilisateur donné
     * Exclut les alertes fermées par l'utilisateur - requête optimisée avec LEFT JOIN
     */
    public function findActiveAlertsForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin(AlerteUtilisateur::class, 'au', 'WITH', 'au.alerte = a.id AND au.user = :user')
            ->where('a.isActive = true')
            ->andWhere('a.dateExpiration IS NULL OR a.dateExpiration > :now')
            ->andWhere('au.id IS NULL') // Exclure les alertes déjà fermées
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('a.ordre', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur peut voir une alerte (selon les cibles)
     */
    public function canUserSeeAlert(Alerte $alerte, User $user): bool
    {
        $cibles = $alerte->getCibles();
        
        // Si pas de cibles spécifiées, visible par tous
        if (empty($cibles)) {
            return true;
        }

        // Vérifier les rôles utilisateur
        $userRoles = $user->getRoles();
        foreach ($cibles as $cible) {
            if (in_array($cible, $userRoles)) {
                return true;
            }
        }

        // TODO: Ajouter vérification des groupes si nécessaire
        
        return false;
    }

    /**
     * Trouve l'ordre maximum des alertes
     */
    public function findMaxOrdre(): ?int
    {
        $result = $this->createQueryBuilder('a')
            ->select('MAX(a.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int)$result : null;
    }

    /**
     * Récupère toutes les alertes avec leurs statistiques d'utilisation
     * Optimisé avec fetch joins pour éviter N+1
     */
    public function findAllWithStats(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.alerteUtilisateurs', 'au')
            ->addSelect('au')
            ->orderBy('a.ordre', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales des alertes
     */
    public function getGlobalStats(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select([
                'COUNT(a.id) as total',
                'SUM(CASE WHEN a.isActive = true THEN 1 ELSE 0 END) as active',
                'SUM(CASE WHEN a.dateExpiration IS NOT NULL AND a.dateExpiration < :now THEN 1 ELSE 0 END) as expired'
            ])
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'active' => (int)$result['active'],
            'inactive' => (int)$result['total'] - (int)$result['active'],
            'expired' => (int)$result['expired']
        ];
    }
}
