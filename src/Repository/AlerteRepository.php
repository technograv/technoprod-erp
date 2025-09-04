<?php

namespace App\Repository;

use App\Entity\Alerte;
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
     * Exclut les alertes fermées par l'utilisateur
     */
    public function findActiveAlertsForUser(User $user): array
    {
        // Première requête simple - récupérer toutes les alertes actives
        $alertesActives = $this->createQueryBuilder('a')
            ->where('a.isActive = true')
            ->andWhere('a.dateExpiration IS NULL OR a.dateExpiration > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.ordre', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Deuxième requête - récupérer les alertes fermées par cet utilisateur
        $alertesFermees = $this->getEntityManager()
            ->getRepository(AlerteUtilisateur::class)
            ->createQueryBuilder('au')
            ->select('IDENTITY(au.alerte)')
            ->where('au.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        // Filtrer côté PHP pour éviter les problèmes DQL
        $alertesFiltered = [];
        foreach ($alertesActives as $alerte) {
            if (!in_array($alerte->getId(), $alertesFermees)) {
                $alertesFiltered[] = $alerte;
            }
        }

        return $alertesFiltered;
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
}
