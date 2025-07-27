<?php

namespace App\Service;

use App\Entity\ComptePCG;
use App\Entity\EcritureComptable;
use App\Entity\ExerciceComptable;
use App\Entity\LigneEcriture;
use App\Repository\ComptePCGRepository;
use App\Repository\EcritureComptableRepository;
use App\Repository\ExerciceComptableRepository;
use App\Repository\LigneEcritureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de calculs de balance et grand livre
 * Implémente les méthodes pour générer les états comptables
 * conformément au PCG français
 */
class BalanceService
{
    private EntityManagerInterface $em;
    private ComptePCGRepository $compteRepo;
    private LigneEcritureRepository $ligneEcritureRepo;
    private EcritureComptableRepository $ecritureRepo;
    private ExerciceComptableRepository $exerciceRepo;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        ComptePCGRepository $compteRepo,
        LigneEcritureRepository $ligneEcritureRepo,
        EcritureComptableRepository $ecritureRepo,
        ExerciceComptableRepository $exerciceRepo,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->compteRepo = $compteRepo;
        $this->ligneEcritureRepo = $ligneEcritureRepo;
        $this->ecritureRepo = $ecritureRepo;
        $this->exerciceRepo = $exerciceRepo;
        $this->logger = $logger;
    }

    /**
     * Génère la balance générale pour une période donnée
     */
    public function genererBalanceGenerale(
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        ?ExerciceComptable $exercice = null,
        array $options = []
    ): array {
        try {
            $exercice = $exercice ?? $this->exerciceRepo->findExerciceActuel();
            
            if (!$exercice) {
                throw new \Exception("Aucun exercice comptable spécifié ou trouvé");
            }

            // Options par défaut
            $options = array_merge([
                'inclure_comptes_sans_mouvement' => false,
                'niveau_detail' => 'tous', // 'tous', 'principaux', 'auxiliaires'
                'classes' => [], // Filtrer par classes comptables (1,2,3,4,5,6,7,8)
                'format_montants' => 'decimal' // 'decimal', 'entier'
            ], $options);

            // Requête simplifiée pour récupérer tous les comptes actifs
            $qb = $this->em->createQueryBuilder();
            $qb->select('c')
               ->from(ComptePCG::class, 'c')
               ->where('c.isActif = true')
               ->orderBy('c.numeroCompte', 'ASC');

            // Filtrage par classes si demandé
            if (!empty($options['classes'])) {
                $qb->andWhere('c.classe IN (:classes)')
                   ->setParameter('classes', $options['classes']);
            }

            $comptes = $qb->getQuery()->getResult();

            // Traitement des résultats
            $balance = [];
            $totauxGeneraux = [
                'total_debit' => '0.00',
                'total_credit' => '0.00',
                'nombre_comptes' => 0,
                'comptes_debiteurs' => 0,
                'comptes_crediteurs' => 0
            ];

            foreach ($comptes as $compte) {
                // Calculer les totaux pour ce compte
                $totauxCompte = $this->calculerTotauxCompte($compte, $dateDebut, $dateFin, $exercice);
                $totalDebit = $totauxCompte['debit'];
                $totalCredit = $totauxCompte['credit'];
                
                // Calculer le solde
                $solde = bcsub($totalDebit, $totalCredit, 2);
                $soldeDebiteur = bccomp($solde, '0.00', 2) > 0 ? $solde : '0.00';
                $soldeCrediteur = bccomp($solde, '0.00', 2) < 0 ? bcmul($solde, '-1', 2) : '0.00';

                // Exclure les comptes sans mouvement si demandé
                if (!$options['inclure_comptes_sans_mouvement'] && 
                    bccomp($totalDebit, '0.00', 2) === 0 && 
                    bccomp($totalCredit, '0.00', 2) === 0) {
                    continue;
                }

                $compteBalance = [
                    'numero_compte' => $compte->getNumeroCompte(),
                    'libelle' => $compte->getLibelle(),
                    'classe' => $compte->getClasse(),
                    'nature' => $compte->getNature(),
                    'type' => $compte->getType(),
                    'mouvements' => [
                        'debit' => $this->formatMontant($totalDebit, $options['format_montants']),
                        'credit' => $this->formatMontant($totalCredit, $options['format_montants'])
                    ],
                    'soldes' => [
                        'debiteur' => $this->formatMontant($soldeDebiteur, $options['format_montants']),
                        'crediteur' => $this->formatMontant($soldeCrediteur, $options['format_montants']),
                        'solde_brut' => $this->formatMontant($solde, $options['format_montants'])
                    ],
                    'sens_solde' => bccomp($solde, '0.00', 2) > 0 ? 'debiteur' : 
                                   (bccomp($solde, '0.00', 2) < 0 ? 'crediteur' : 'equilibre')
                ];

                $balance[] = $compteBalance;

                // Mise à jour des totaux généraux
                $totauxGeneraux['total_debit'] = bcadd($totauxGeneraux['total_debit'], $totalDebit, 2);
                $totauxGeneraux['total_credit'] = bcadd($totauxGeneraux['total_credit'], $totalCredit, 2);
                $totauxGeneraux['nombre_comptes']++;
                
                if (bccomp($solde, '0.00', 2) > 0) {
                    $totauxGeneraux['comptes_debiteurs']++;
                } elseif (bccomp($solde, '0.00', 2) < 0) {
                    $totauxGeneraux['comptes_crediteurs']++;
                }
            }

            // Vérification équilibre
            $equilibre = bccomp($totauxGeneraux['total_debit'], $totauxGeneraux['total_credit'], 2) === 0;

            $this->logger->info('Balance générale générée', [
                'periode' => $dateDebut->format('Y-m-d') . ' - ' . $dateFin->format('Y-m-d'),
                'exercice' => $exercice->getAnneeExercice(),
                'nombre_comptes' => $totauxGeneraux['nombre_comptes'],
                'equilibre' => $equilibre
            ]);

            return [
                'success' => true,
                'balance' => $balance,
                'totaux' => [
                    'total_debit' => $this->formatMontant($totauxGeneraux['total_debit'], $options['format_montants']),
                    'total_credit' => $this->formatMontant($totauxGeneraux['total_credit'], $options['format_montants']),
                    'nombre_comptes' => $totauxGeneraux['nombre_comptes'],
                    'comptes_debiteurs' => $totauxGeneraux['comptes_debiteurs'],
                    'comptes_crediteurs' => $totauxGeneraux['comptes_crediteurs'],
                    'equilibre' => $equilibre
                ],
                'periode' => [
                    'debut' => $dateDebut,
                    'fin' => $dateFin
                ],
                'exercice' => $exercice,
                'options' => $options,
                'genere_le' => new \DateTime()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur génération balance générale', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'balance' => [],
                'totaux' => []
            ];
        }
    }

    /**
     * Génère le grand livre d'un compte pour une période
     */
    public function genererGrandLivre(
        ComptePCG $compte,
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        ?ExerciceComptable $exercice = null,
        array $options = []
    ): array {
        try {
            $exercice = $exercice ?? $this->exerciceRepo->findExerciceActuel();

            // Options par défaut
            $options = array_merge([
                'inclure_lettrage' => true,
                'ordre_tri' => 'date_asc', // 'date_asc', 'date_desc', 'piece_asc'
                'inclure_solde_initial' => true,
                'format_montants' => 'decimal'
            ], $options);

            // Calcul du solde initial (avant la période)
            $soldeInitial = '0.00';
            if ($options['inclure_solde_initial']) {
                $soldeInitial = $this->calculerSoldeInitial($compte, $dateDebut, $exercice);
            }

            // Récupération des mouvements de la période
            $qb = $this->em->createQueryBuilder();
            $qb->select('l', 'e')
               ->from(LigneEcriture::class, 'l')
               ->join('l.ecriture', 'e')
               ->where('l.compte = :compte')
               ->andWhere('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
               ->andWhere('e.exerciceComptable = :exercice')
               ->andWhere('e.isValidee = true')
               ->setParameter('compte', $compte)
               ->setParameter('dateDebut', $dateDebut)
               ->setParameter('dateFin', $dateFin)
               ->setParameter('exercice', $exercice);

            // Tri selon les options
            switch ($options['ordre_tri']) {
                case 'date_desc':
                    $qb->orderBy('e.dateEcriture', 'DESC')
                       ->addOrderBy('e.numeroEcriture', 'DESC');
                    break;
                case 'piece_asc':
                    $qb->orderBy('e.numeroPiece', 'ASC')
                       ->addOrderBy('e.dateEcriture', 'ASC');
                    break;
                default: // date_asc
                    $qb->orderBy('e.dateEcriture', 'ASC')
                       ->addOrderBy('e.numeroEcriture', 'ASC');
            }

            $mouvements = $qb->getQuery()->getResult();

            // Construction du grand livre
            $grandLivre = [];
            $soldeCourant = $soldeInitial;
            $totauxPeriode = [
                'total_debit' => '0.00',
                'total_credit' => '0.00',
                'nombre_mouvements' => 0
            ];

            // Ligne solde initial si demandée
            if ($options['inclure_solde_initial'] && bccomp($soldeInitial, '0.00', 2) !== 0) {
                $grandLivre[] = [
                    'type' => 'solde_initial',
                    'date' => $dateDebut,
                    'libelle' => 'Solde initial au ' . $dateDebut->format('d/m/Y'),
                    'debit' => bccomp($soldeInitial, '0.00', 2) > 0 ? 
                              $this->formatMontant($soldeInitial, $options['format_montants']) : '0.00',
                    'credit' => bccomp($soldeInitial, '0.00', 2) < 0 ? 
                               $this->formatMontant(bcmul($soldeInitial, '-1', 2), $options['format_montants']) : '0.00',
                    'solde' => $this->formatMontant($soldeCourant, $options['format_montants']),
                    'sens_solde' => bccomp($soldeCourant, '0.00', 2) >= 0 ? 'debiteur' : 'crediteur'
                ];
            }

            // Traitement des mouvements
            foreach ($mouvements as $ligne) {
                $ecriture = $ligne->getEcriture();
                
                $mouvement = [
                    'type' => 'mouvement',
                    'date' => $ecriture->getDateEcriture(),
                    'date_piece' => $ecriture->getDatePiece(),
                    'numero_ecriture' => $ecriture->getNumeroEcriture(),
                    'numero_piece' => $ecriture->getNumeroPiece(),
                    'journal_code' => $ecriture->getJournalCode(),
                    'journal_libelle' => $ecriture->getJournalLibelle(),
                    'libelle' => $ligne->getLibelle(),
                    'debit' => $this->formatMontant($ligne->getMontantDebit(), $options['format_montants']),
                    'credit' => $this->formatMontant($ligne->getMontantCredit(), $options['format_montants']),
                    'compte_auxiliaire' => $ligne->getCompteAuxiliaire(),
                    'compte_auxiliaire_libelle' => $ligne->getCompteAuxiliaireLibelle(),
                    'date_echeance' => $ligne->getDateEcheance()
                ];

                // Lettrage si demandé
                if ($options['inclure_lettrage']) {
                    $mouvement['lettrage'] = $ligne->getLettrage();
                    $mouvement['date_lettrage'] = $ligne->getDateLettrage();
                }

                // Calcul du solde progressif
                $soldeCourant = bcadd($soldeCourant, $ligne->getMontantDebit(), 2);
                $soldeCourant = bcsub($soldeCourant, $ligne->getMontantCredit(), 2);
                
                $mouvement['solde'] = $this->formatMontant($soldeCourant, $options['format_montants']);
                $mouvement['sens_solde'] = bccomp($soldeCourant, '0.00', 2) >= 0 ? 'debiteur' : 'crediteur';

                $grandLivre[] = $mouvement;

                // Mise à jour des totaux
                $totauxPeriode['total_debit'] = bcadd($totauxPeriode['total_debit'], $ligne->getMontantDebit(), 2);
                $totauxPeriode['total_credit'] = bcadd($totauxPeriode['total_credit'], $ligne->getMontantCredit(), 2);
                $totauxPeriode['nombre_mouvements']++;
            }

            // Solde final
            $soldeFinal = $soldeCourant;

            $this->logger->info('Grand livre généré', [
                'compte' => $compte->getNumeroCompte(),
                'periode' => $dateDebut->format('Y-m-d') . ' - ' . $dateFin->format('Y-m-d'),
                'nombre_mouvements' => $totauxPeriode['nombre_mouvements']
            ]);

            return [
                'success' => true,
                'compte' => [
                    'numero' => $compte->getNumeroCompte(),
                    'libelle' => $compte->getLibelle(),
                    'classe' => $compte->getClasse(),
                    'nature' => $compte->getNature()
                ],
                'soldes' => [
                    'initial' => $this->formatMontant($soldeInitial, $options['format_montants']),
                    'final' => $this->formatMontant($soldeFinal, $options['format_montants']),
                    'variation' => $this->formatMontant(bcsub($soldeFinal, $soldeInitial, 2), $options['format_montants'])
                ],
                'totaux' => [
                    'total_debit' => $this->formatMontant($totauxPeriode['total_debit'], $options['format_montants']),
                    'total_credit' => $this->formatMontant($totauxPeriode['total_credit'], $options['format_montants']),
                    'nombre_mouvements' => $totauxPeriode['nombre_mouvements']
                ],
                'mouvements' => $grandLivre,
                'periode' => [
                    'debut' => $dateDebut,
                    'fin' => $dateFin
                ],
                'exercice' => $exercice,
                'options' => $options,
                'genere_le' => new \DateTime()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur génération grand livre', [
                'compte' => $compte->getNumeroCompte(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'mouvements' => []
            ];
        }
    }

    /**
     * Génère la balance par classe comptable
     */
    public function genererBalanceParClasse(
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        ?ExerciceComptable $exercice = null
    ): array {
        try {
            $exercice = $exercice ?? $this->exerciceRepo->findExerciceActuel();
            
            $balanceClasses = [];
            $totalGeneral = ['debit' => '0.00', 'credit' => '0.00'];

            // Générer la balance pour chaque classe (1 à 8)
            for ($classe = 1; $classe <= 8; $classe++) {
                $balanceClasse = $this->genererBalanceGenerale(
                    $dateDebut, 
                    $dateFin, 
                    $exercice, 
                    ['classes' => [(string)$classe], 'inclure_comptes_sans_mouvement' => false]
                );

                if ($balanceClasse['success'] && !empty($balanceClasse['balance'])) {
                    $totalClasse = $balanceClasse['totaux'];
                    
                    $balanceClasses[$classe] = [
                        'classe' => $classe,
                        'libelle' => $this->getLibelleClasse($classe),
                        'nombre_comptes' => count($balanceClasse['balance']),
                        'total_debit' => $totalClasse['total_debit'],
                        'total_credit' => $totalClasse['total_credit'],
                        'comptes' => $balanceClasse['balance']
                    ];

                    $totalGeneral['debit'] = bcadd($totalGeneral['debit'], str_replace(',', '.', $totalClasse['total_debit']), 2);
                    $totalGeneral['credit'] = bcadd($totalGeneral['credit'], str_replace(',', '.', $totalClasse['total_credit']), 2);
                }
            }

            return [
                'success' => true,
                'balance_par_classe' => $balanceClasses,
                'total_general' => $totalGeneral,
                'equilibre' => bccomp($totalGeneral['debit'], $totalGeneral['credit'], 2) === 0,
                'periode' => ['debut' => $dateDebut, 'fin' => $dateFin],
                'exercice' => $exercice
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calcule le solde initial d'un compte avant une date donnée
     */
    private function calculerSoldeInitial(ComptePCG $compte, \DateTimeInterface $dateDebut, ExerciceComptable $exercice): string
    {
        $qb = $this->em->createQueryBuilder();
        $result = $qb->select('COALESCE(SUM(l.montantDebit), 0) as totalDebit, COALESCE(SUM(l.montantCredit), 0) as totalCredit')
                    ->from(LigneEcriture::class, 'l')
                    ->join('l.ecriture', 'e')
                    ->where('l.comptePCG = :compte')
                    ->andWhere('e.dateEcriture < :dateDebut')
                    ->andWhere('e.exerciceComptable = :exercice')
                    ->andWhere('e.isValidee = true')
                    ->setParameter('compte', $compte)
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('exercice', $exercice)
                    ->getQuery()
                    ->getSingleResult();

        return bcsub($result['totalDebit'] ?? '0.00', $result['totalCredit'] ?? '0.00', 2);
    }

    /**
     * Formate un montant selon le format demandé
     */
    private function formatMontant(string $montant, string $format): string
    {
        return match($format) {
            'entier' => number_format((float)$montant, 0, ',', ' '),
            default => number_format((float)$montant, 2, ',', ' ')
        };
    }

    /**
     * Retourne le libellé d'une classe comptable
     */
    private function getLibelleClasse(int $classe): string
    {
        return match($classe) {
            1 => 'Comptes de capitaux',
            2 => 'Comptes d\'immobilisations',
            3 => 'Comptes de stocks et en-cours',
            4 => 'Comptes de tiers',
            5 => 'Comptes financiers',
            6 => 'Comptes de charges',
            7 => 'Comptes de produits',
            8 => 'Comptes spéciaux',
            default => 'Classe inconnue'
        };
    }

    /**
     * Exporte la balance au format CSV
     */
    public function exporterBalanceCSV(array $balance, string $nomFichier = null): string
    {
        $nomFichier = $nomFichier ?? 'balance_' . date('Y-m-d_H-i-s') . '.csv';
        
        $csv = "Numero Compte;Libelle;Classe;Nature;Total Debit;Total Credit;Solde Debiteur;Solde Crediteur\n";
        
        foreach ($balance['balance'] as $ligne) {
            $csv .= sprintf(
                "%s;%s;%s;%s;%s;%s;%s;%s\n",
                $ligne['numero_compte'],
                str_replace(';', ',', $ligne['libelle']),
                $ligne['classe'],
                $ligne['nature'],
                str_replace(' ', '', $ligne['mouvements']['debit']),
                str_replace(' ', '', $ligne['mouvements']['credit']),
                str_replace(' ', '', $ligne['soldes']['debiteur']),
                str_replace(' ', '', $ligne['soldes']['crediteur'])
            );
        }
        
        return $csv;
    }

    /**
     * Calcule les totaux débit/crédit pour un compte sur une période
     */
    private function calculerTotauxCompte(ComptePCG $compte, \DateTimeInterface $dateDebut, \DateTimeInterface $dateFin, ExerciceComptable $exercice): array
    {
        $qb = $this->em->createQueryBuilder();
        $result = $qb->select('COALESCE(SUM(l.montantDebit), 0) as totalDebit, COALESCE(SUM(l.montantCredit), 0) as totalCredit')
                    ->from(LigneEcriture::class, 'l')
                    ->join('l.ecriture', 'e')
                    ->where('l.comptePCG = :compte')
                    ->andWhere('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
                    ->andWhere('e.exerciceComptable = :exercice')
                    ->andWhere('e.isValidee = true')
                    ->setParameter('compte', $compte)
                    ->setParameter('dateDebut', $dateDebut)
                    ->setParameter('dateFin', $dateFin)
                    ->setParameter('exercice', $exercice)
                    ->getQuery()
                    ->getSingleResult();

        return [
            'debit' => $result['totalDebit'] ?? '0.00',
            'credit' => $result['totalCredit'] ?? '0.00'
        ];
    }
}