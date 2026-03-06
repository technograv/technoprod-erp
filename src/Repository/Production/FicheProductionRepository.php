<?php

namespace App\Repository\Production;

use App\Entity\Production\FicheProduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FicheProduction>
 */
class FicheProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheProduction::class);
    }

    /**
     * Trouve toutes les fiches par statut
     *
     * @return FicheProduction[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('f.priorite', 'ASC')
            ->addOrderBy('f.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les fiches en cours
     *
     * @return FicheProduction[]
     */
    public function findEnCours(): array
    {
        return $this->findByStatut(FicheProduction::STATUT_EN_COURS);
    }

    /**
     * Trouve les fiches validées (prêtes à démarrer)
     *
     * @return FicheProduction[]
     */
    public function findValidees(): array
    {
        return $this->findByStatut(FicheProduction::STATUT_VALIDEE);
    }

    /**
     * Trouve les fiches par devis
     *
     * @return FicheProduction[]
     */
    public function findByDevis($devis): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.devis = :devis')
            ->setParameter('devis', $devis)
            ->orderBy('f.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les fiches par produit catalogue
     *
     * @return FicheProduction[]
     */
    public function findByProduitCatalogue($produit): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.produitCatalogue = :produit')
            ->setParameter('produit', $produit)
            ->orderBy('f.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les fiches à livrer aujourd'hui
     *
     * @return FicheProduction[]
     */
    public function findALivrerAujourdhui(): array
    {
        $debut = new \DateTimeImmutable('today');
        $fin = new \DateTimeImmutable('tomorrow');

        return $this->createQueryBuilder('f')
            ->where('f.dateLivraisonPrevue >= :debut')
            ->andWhere('f.dateLivraisonPrevue < :fin')
            ->andWhere('f.statut != :annulee')
            ->andWhere('f.statut != :terminee')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->setParameter('annulee', FicheProduction::STATUT_ANNULEE)
            ->setParameter('terminee', FicheProduction::STATUT_TERMINEE)
            ->orderBy('f.priorite', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les fiches en retard
     *
     * @return FicheProduction[]
     */
    public function findEnRetard(): array
    {
        $maintenant = new \DateTimeImmutable();

        return $this->createQueryBuilder('f')
            ->where('f.dateLivraisonPrevue < :maintenant')
            ->andWhere('f.statut != :annulee')
            ->andWhere('f.statut != :terminee')
            ->setParameter('maintenant', $maintenant)
            ->setParameter('annulee', FicheProduction::STATUT_ANNULEE)
            ->setParameter('terminee', FicheProduction::STATUT_TERMINEE)
            ->orderBy('f.dateLivraisonPrevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques
     */
    public function getStatistiques(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select(
                'COUNT(f.id) as total',
                'SUM(CASE WHEN f.statut = :brouillon THEN 1 ELSE 0 END) as brouillons',
                'SUM(CASE WHEN f.statut = :validee THEN 1 ELSE 0 END) as validees',
                'SUM(CASE WHEN f.statut = :en_cours THEN 1 ELSE 0 END) as en_cours',
                'SUM(CASE WHEN f.statut = :terminee THEN 1 ELSE 0 END) as terminees'
            )
            ->setParameter('brouillon', FicheProduction::STATUT_BROUILLON)
            ->setParameter('validee', FicheProduction::STATUT_VALIDEE)
            ->setParameter('en_cours', FicheProduction::STATUT_EN_COURS)
            ->setParameter('terminee', FicheProduction::STATUT_TERMINEE)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'brouillons' => (int)$result['brouillons'],
            'validees' => (int)$result['validees'],
            'en_cours' => (int)$result['en_cours'],
            'terminees' => (int)$result['terminees']
        ];
    }
}
