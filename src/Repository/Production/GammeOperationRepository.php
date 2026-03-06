<?php

namespace App\Repository\Production;

use App\Entity\Production\GammeOperation;
use App\Entity\Production\Gamme;
use App\Entity\Production\PosteTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GammeOperation>
 */
class GammeOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GammeOperation::class);
    }

    /**
     * Trouve toutes les opérations d'une gamme triées par ordre
     *
     * @return GammeOperation[]
     */
    public function findByGammeOrdered(Gamme $gamme): array
    {
        return $this->createQueryBuilder('go')
            ->where('go.gamme = :gamme')
            ->setParameter('gamme', $gamme)
            ->orderBy('go.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les opérations utilisant un poste spécifique
     *
     * @return GammeOperation[]
     */
    public function findByPoste(PosteTravail $poste): array
    {
        return $this->createQueryBuilder('go')
            ->join('go.gamme', 'g')
            ->where('go.posteTravail = :poste')
            ->setParameter('poste', $poste)
            ->orderBy('g.code', 'ASC')
            ->addOrderBy('go.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte combien de gammes utilisent un poste
     */
    public function countGammesUtilisantPoste(PosteTravail $poste): int
    {
        return (int) $this->createQueryBuilder('go')
            ->select('COUNT(DISTINCT go.gamme)')
            ->where('go.posteTravail = :poste')
            ->setParameter('poste', $poste)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les opérations avec formules de temps
     *
     * @return GammeOperation[]
     */
    public function findAvecFormules(): array
    {
        return $this->createQueryBuilder('go')
            ->join('go.gamme', 'g')
            ->where('go.typeTemps = :type')
            ->andWhere('go.formuleTemps IS NOT NULL')
            ->setParameter('type', GammeOperation::TYPE_TEMPS_FORMULE)
            ->orderBy('g.code', 'ASC')
            ->addOrderBy('go.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les opérations avec conditions d'exécution
     *
     * @return GammeOperation[]
     */
    public function findAvecConditions(): array
    {
        return $this->createQueryBuilder('go')
            ->join('go.gamme', 'g')
            ->where('go.conditionExecution IS NOT NULL')
            ->orderBy('g.code', 'ASC')
            ->addOrderBy('go.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les opérations parallèles
     *
     * @return GammeOperation[]
     */
    public function findParalleles(): array
    {
        return $this->createQueryBuilder('go')
            ->join('go.gamme', 'g')
            ->where('go.tempsParallele = :parallele')
            ->setParameter('parallele', true)
            ->orderBy('g.code', 'ASC')
            ->addOrderBy('go.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par type de temps
     */
    public function getStatistiquesParType(): array
    {
        $results = $this->createQueryBuilder('go')
            ->select('go.typeTemps', 'COUNT(go.id) as total')
            ->groupBy('go.typeTemps')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['typeTemps']] = (int)$result['total'];
        }

        return $stats;
    }

    /**
     * Calcule le temps moyen des opérations fixes
     */
    public function calculerTempsMoyenFixe(): float
    {
        $result = $this->createQueryBuilder('go')
            ->select('AVG(go.tempsFixe) as moyenne')
            ->where('go.typeTemps = :type')
            ->setParameter('type', GammeOperation::TYPE_TEMPS_FIXE)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)$result, 2);
    }
}
