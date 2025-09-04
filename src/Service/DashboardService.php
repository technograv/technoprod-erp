<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\DevisRepository;
use App\Repository\CommandeRepository;
use App\Repository\FactureRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DevisRepository $devisRepository,
        private CommandeRepository $commandeRepository,
        private FactureRepository $factureRepository,
        private ClientRepository $clientRepository
    ) {}

    public function getDashboardStats(?User $user = null): array
    {
        $stats = [
            'devis_brouillons' => $this->countDevisBrouillons($user),
            'devis_a_relancer' => $this->countDevisARelancer($user),
            'commandes_sans_livraison' => $this->countCommandesSansLivraison($user),
            'livraisons_non_facturees' => $this->countLivraisonsNonFacturees($user),
            'contract_deadlines' => $this->countContractDeadlines($user),
            'visits_due' => $this->countVisitsDue($user)
        ];

        return $stats;
    }

    public function getDevisBrouillons(?User $user = null, int $limit = null): array
    {
        $queryBuilder = $this->devisRepository->createQueryBuilder('d')
            ->where('d.statut = :statut')
            ->setParameter('statut', 'brouillon')
            ->orderBy('d.updatedAt', 'DESC');

        if ($user && !$this->isUserAdmin($user)) {
            $queryBuilder->andWhere('d.commercial = :user')
                        ->setParameter('user', $user);
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getDevisARelancer(?User $user = null, int $limit = null): array
    {
        $dateLimite = new \DateTime('-2 weeks');
        
        $queryBuilder = $this->devisRepository->createQueryBuilder('d')
            ->where('d.statut = :statut')
            ->andWhere('d.dateEnvoi <= :date_limite')
            ->setParameter('statut', 'envoye')
            ->setParameter('date_limite', $dateLimite)
            ->orderBy('d.dateEnvoi', 'ASC');

        if ($user && !$this->isUserAdmin($user)) {
            $queryBuilder->andWhere('d.commercial = :user')
                        ->setParameter('user', $user);
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getCommandesSansLivraison(?User $user = null, int $limit = null): array
    {
        $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
            ->where('c.dateLivraisonPrevue IS NULL')
            ->andWhere('c.statut != :statut_livre')
            ->setParameter('statut_livre', 'livree')
            ->orderBy('c.createdAt', 'ASC');

        if ($user && !$this->isUserAdmin($user)) {
            $queryBuilder->andWhere('c.commercial = :user')
                        ->setParameter('user', $user);
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getLivraisonsNonFacturees(?User $user = null, int $limit = null): array
    {
        $queryBuilder = $this->commandeRepository->createQueryBuilder('c')
            ->leftJoin('App\Entity\Facture', 'f', 'WITH', 'f.commande = c.id')
            ->where('c.statut = :statut_livre')
            ->andWhere('f.id IS NULL')
            ->setParameter('statut_livre', 'livree')
            ->orderBy('c.dateLivraisonReelle', 'ASC');

        if ($user && !$this->isUserAdmin($user)) {
            $queryBuilder->andWhere('c.commercial = :user')
                        ->setParameter('user', $user);
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getPerformanceData(User $user, ?\DateTime $dateDebut = null, ?\DateTime $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? new \DateTime('-1 year');
        $dateFin = $dateFin ?? new \DateTime();

        $ventesMensuelle = $this->getVentesMensuelle($user, $dateDebut, $dateFin);
        $objectifsData = $this->getObjectifsData($user, $dateDebut, $dateFin);
        $performanceSemestrielle = $this->getPerformanceSemestrielle($user);

        return [
            'ventes_mensuelle' => $ventesMensuelle,
            'objectifs' => $objectifsData,
            'performance_semestrielle' => $performanceSemestrielle
        ];
    }

    private function getVentesMensuelle(User $user, \DateTime $dateDebut, \DateTime $dateFin): array
    {
        $qb = $this->factureRepository->createQueryBuilder('f')
            ->select('EXTRACT(MONTH FROM f.createdAt) as mois, SUM(f.montantTotal) as total')
            ->where('f.createdAt BETWEEN :debut AND :fin')
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->groupBy('mois')
            ->orderBy('mois', 'ASC');

        if (!$this->isUserAdmin($user)) {
            $qb->andWhere('f.commercial = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    private function getObjectifsData(User $user, \DateTime $dateDebut, \DateTime $dateFin): array
    {
        return [
            'objectif_mensuel' => 50000,
            'realise_mois_courant' => $this->getVentesMoisCourant($user),
            'progression' => $this->getProgressionObjectif($user)
        ];
    }

    private function getVentesMoisCourant(User $user): float
    {
        $debutMois = new \DateTime('first day of this month');
        $finMois = new \DateTime('last day of this month');

        $qb = $this->factureRepository->createQueryBuilder('f')
            ->select('SUM(f.montantTotal)')
            ->where('f.createdAt BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois);

        if (!$this->isUserAdmin($user)) {
            $qb->andWhere('f.commercial = :user')
               ->setParameter('user', $user);
        }

        return (float)$qb->getQuery()->getSingleScalarResult() ?? 0.0;
    }

    private function getProgressionObjectif(User $user): float
    {
        $ventesCourantes = $this->getVentesMoisCourant($user);
        $objectif = 50000;
        
        return $objectif > 0 ? round(($ventesCourantes / $objectif) * 100, 2) : 0;
    }

    private function getPerformanceSemestrielle(User $user): array
    {
        $anneeActuelle = (int)date('Y');
        $semestre1 = $this->getVentesSemestre($user, $anneeActuelle, 1);
        $semestre2 = $this->getVentesSemestre($user, $anneeActuelle, 2);

        return [
            'semestre_1' => $semestre1,
            'semestre_2' => $semestre2
        ];
    }

    private function getVentesSemestre(User $user, int $annee, int $semestre): float
    {
        if ($semestre === 1) {
            $debut = new \DateTime("$annee-01-01");
            $fin = new \DateTime("$annee-06-30");
        } else {
            $debut = new \DateTime("$annee-07-01");
            $fin = new \DateTime("$annee-12-31");
        }

        $qb = $this->factureRepository->createQueryBuilder('f')
            ->select('SUM(f.montantTotal)')
            ->where('f.createdAt BETWEEN :debut AND :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);

        if (!$this->isUserAdmin($user)) {
            $qb->andWhere('f.commercial = :user')
               ->setParameter('user', $user);
        }

        return (float)$qb->getQuery()->getSingleScalarResult() ?? 0.0;
    }

    private function countDevisBrouillons(?User $user = null): int
    {
        $qb = $this->devisRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statut = :statut')
            ->setParameter('statut', 'brouillon');

        if ($user && !$this->isUserAdmin($user)) {
            $qb->andWhere('d.commercial = :user')
               ->setParameter('user', $user);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    private function countDevisARelancer(?User $user = null): int
    {
        $dateLimite = new \DateTime('-2 weeks');
        
        $qb = $this->devisRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statut = :statut')
            ->andWhere('d.dateEnvoi <= :date_limite')
            ->setParameter('statut', 'envoye')
            ->setParameter('date_limite', $dateLimite);

        if ($user && !$this->isUserAdmin($user)) {
            $qb->andWhere('d.commercial = :user')
               ->setParameter('user', $user);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    private function countCommandesSansLivraison(?User $user = null): int
    {
        $qb = $this->commandeRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.dateLivraisonPrevue IS NULL')
            ->andWhere('c.statut != :statut_livre')
            ->setParameter('statut_livre', 'livree');

        if ($user && !$this->isUserAdmin($user)) {
            $qb->andWhere('c.commercial = :user')
               ->setParameter('user', $user);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    private function countLivraisonsNonFacturees(?User $user = null): int
    {
        $qb = $this->commandeRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->leftJoin('App\Entity\Facture', 'f', 'WITH', 'f.commande = c.id')
            ->where('c.statut = :statut_livre')
            ->andWhere('f.id IS NULL')
            ->setParameter('statut_livre', 'livree');

        if ($user && !$this->isUserAdmin($user)) {
            $qb->andWhere('c.commercial = :user')
               ->setParameter('user', $user);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    private function countContractDeadlines(?User $user = null): int
    {
        return 0;
    }

    private function countVisitsDue(?User $user = null): int
    {
        return 0;
    }

    private function isUserAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles());
    }
}