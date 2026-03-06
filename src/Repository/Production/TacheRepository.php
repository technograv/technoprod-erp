<?php

namespace App\Repository\Production;

use App\Entity\Production\Tache;
use App\Entity\Production\FicheProduction;
use App\Entity\Production\PosteTravail;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tache>
 */
class TacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tache::class);
    }

    /**
     * Trouve toutes les tâches d'une fiche triées par ordre
     *
     * @return Tache[]
     */
    public function findByFicheOrdered(FicheProduction $fiche): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.ficheProduction = :fiche')
            ->setParameter('fiche', $fiche)
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches par statut
     *
     * @return Tache[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.ficheProduction', 'f')
            ->where('t.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('f.priorite', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches à faire
     *
     * @return Tache[]
     */
    public function findAFaire(): array
    {
        return $this->findByStatut(Tache::STATUT_A_FAIRE);
    }

    /**
     * Trouve les tâches en cours
     *
     * @return Tache[]
     */
    public function findEnCours(): array
    {
        return $this->findByStatut(Tache::STATUT_EN_COURS);
    }

    /**
     * Trouve les tâches bloquées
     *
     * @return Tache[]
     */
    public function findBloquees(): array
    {
        return $this->findByStatut(Tache::STATUT_BLOQUEE);
    }

    /**
     * Trouve les tâches assignées à un opérateur
     *
     * @return Tache[]
     */
    public function findByOperateur(User $operateur, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.ficheProduction', 'f')
            ->where('t.operateurAssigne = :operateur')
            ->setParameter('operateur', $operateur);

        if ($statut) {
            $qb->andWhere('t.statut = :statut')
               ->setParameter('statut', $statut);
        }

        return $qb->orderBy('f.priorite', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches sur un poste de travail
     *
     * @return Tache[]
     */
    public function findByPoste(PosteTravail $poste, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.ficheProduction', 'f')
            ->where('t.posteTravail = :poste')
            ->setParameter('poste', $poste);

        if ($statut) {
            $qb->andWhere('t.statut = :statut')
               ->setParameter('statut', $statut);
        }

        return $qb->orderBy('f.priorite', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches en retard (temps réel > temps prévu)
     *
     * @return Tache[]
     */
    public function findEnRetard(): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.ficheProduction', 'f')
            ->where('t.tempsReelMinutes IS NOT NULL')
            ->andWhere('t.tempsReelMinutes > t.tempsPrevuMinutes')
            ->orderBy('f.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches nécessitant un contrôle qualité
     *
     * @return Tache[]
     */
    public function findNecessitantControle(bool $effectueOnly = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.ficheProduction', 'f')
            ->where('t.controleQualite = :controle')
            ->setParameter('controle', true);

        if ($effectueOnly) {
            $qb->andWhere('t.controleEffectue = :effectue')
               ->setParameter('effectue', false);
        }

        return $qb->orderBy('f.priorite', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques
     */
    public function getStatistiques(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select(
                'COUNT(t.id) as total',
                'SUM(CASE WHEN t.statut = :a_faire THEN 1 ELSE 0 END) as a_faire',
                'SUM(CASE WHEN t.statut = :en_cours THEN 1 ELSE 0 END) as en_cours',
                'SUM(CASE WHEN t.statut = :terminee THEN 1 ELSE 0 END) as terminees',
                'SUM(CASE WHEN t.statut = :bloquee THEN 1 ELSE 0 END) as bloquees',
                'AVG(CASE WHEN t.tempsReelMinutes IS NOT NULL THEN t.tempsReelMinutes ELSE NULL END) as temps_moyen_reel',
                'AVG(t.tempsPrevuMinutes) as temps_moyen_prevu'
            )
            ->setParameter('a_faire', Tache::STATUT_A_FAIRE)
            ->setParameter('en_cours', Tache::STATUT_EN_COURS)
            ->setParameter('terminee', Tache::STATUT_TERMINEE)
            ->setParameter('bloquee', Tache::STATUT_BLOQUEE)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'a_faire' => (int)$result['a_faire'],
            'en_cours' => (int)$result['en_cours'],
            'terminees' => (int)$result['terminees'],
            'bloquees' => (int)$result['bloquees'],
            'temps_moyen_reel' => $result['temps_moyen_reel'] ? round((float)$result['temps_moyen_reel'], 1) : 0,
            'temps_moyen_prevu' => round((float)$result['temps_moyen_prevu'], 1)
        ];
    }
}
