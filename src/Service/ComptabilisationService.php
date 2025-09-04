<?php

namespace App\Service;

use App\Entity\ComptePCG;
use App\Entity\EcritureComptable;
use App\Entity\ExerciceComptable;
use App\Entity\Facture;
use App\Entity\JournalComptable;
use App\Entity\LigneEcriture;
use App\Entity\User;
use App\Repository\ComptePCGRepository;
use App\Repository\ExerciceComptableRepository;
use App\Repository\JournalComptableRepository;
use App\Repository\LigneEcritureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service principal de comptabilisation automatique
 * Implémente les méthodes définies dans l'architecture pour la comptabilisation
 * automatique des documents commerciaux selon le PCG français
 */
class ComptabilisationService
{
    private EntityManagerInterface $em;
    private ComptePCGRepository $compteRepo;
    private JournalComptableRepository $journalRepo;
    private ExerciceComptableRepository $exerciceRepo;
    private LigneEcritureRepository $ligneEcritureRepo;
    private DocumentIntegrityService $integrityService;
    private AuditService $auditService;
    private Security $security;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        ComptePCGRepository $compteRepo,
        JournalComptableRepository $journalRepo,
        ExerciceComptableRepository $exerciceRepo,
        LigneEcritureRepository $ligneEcritureRepo,
        DocumentIntegrityService $integrityService,
        AuditService $auditService,
        Security $security,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->compteRepo = $compteRepo;
        $this->journalRepo = $journalRepo;
        $this->exerciceRepo = $exerciceRepo;
        $this->ligneEcritureRepo = $ligneEcritureRepo;
        $this->integrityService = $integrityService;
        $this->auditService = $auditService;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Comptabilise automatiquement une facture selon le PCG français
     */
    public function comptabiliserFacture(Facture $facture): array
    {
        try {
            $this->em->beginTransaction();

            $exercice = $this->getExerciceActuel();
            $journal = $this->getJournalVentes();
            $numeroEcriture = $journal->generateNextNumeroEcriture();
            
            // Création de l'écriture comptable principale
            $ecriture = new EcritureComptable();
            $ecriture->setJournal($journal);
            $ecriture->setNumeroEcriture($numeroEcriture);
            $ecriture->setDateEcriture($facture->getDateFacture());
            $ecriture->setDatePiece($facture->getDateFacture());
            $ecriture->setNumeroPiece($facture->getNumeroFacture());
            $ecriture->setLibelleEcriture('Facture n° ' . $facture->getNumeroFacture() . ' - ' . $facture->getClient()->getNomEntreprise());
            $ecriture->setDocumentType('facture');
            $ecriture->setDocumentId($facture->getId());
            $ecriture->setExerciceComptable($exercice);
            if ($user = $this->security->getUser()) {
                $ecriture->setCreatedBy($user);
            }

            $lignes = [];

            // 1. DÉBIT - Compte client (411xxx)
            $compteClient = $this->getCompteClient();
            $ligneClient = $this->creerLigneEcriture([
                'ecriture' => $ecriture,
                'compte' => $compteClient,
                'montantDebit' => $facture->getTotalTtc(),
                'montantCredit' => '0.00',
                'libelle' => 'Fact. ' . $facture->getNumeroFacture() . ' - ' . $facture->getClient()->getNomEntreprise(),
                'compteAuxiliaire' => $this->generateCompteAuxiliaireClient($facture->getClient()),
                'compteAuxiliaireLibelle' => $facture->getClient()->getNomEntreprise(),
                'dateEcheance' => $facture->getDateEcheance()
            ]);
            $lignes[] = $ligneClient;

            // 2. CRÉDIT - Comptes de vente par taux TVA
            $ventilationTVA = $this->calculerVentilationTVA($facture);
            
            foreach ($ventilationTVA as $tauxTva => $montants) {
                // Compte de vente (701xxx)
                $compteVente = $this->determinerCompteVente($facture, $tauxTva);
                $ligneVente = $this->creerLigneEcriture([
                    'ecriture' => $ecriture,
                    'compte' => $compteVente,
                    'montantDebit' => '0.00',
                    'montantCredit' => $montants['ht'],
                    'libelle' => 'Fact. ' . $facture->getNumeroFacture() . ' - Vente HT ' . $tauxTva . '%'
                ]);
                $lignes[] = $ligneVente;

                // Compte TVA collectée (44571x) si TVA > 0
                if (bccomp($montants['tva'], '0.00', 2) > 0) {
                    $compteTva = $this->determinerCompteTVA($tauxTva);
                    $ligneTva = $this->creerLigneEcriture([
                        'ecriture' => $ecriture,
                        'compte' => $compteTva,
                        'montantDebit' => '0.00',
                        'montantCredit' => $montants['tva'],
                        'libelle' => 'Fact. ' . $facture->getNumeroFacture() . ' - TVA ' . $tauxTva . '%'
                    ]);
                    $lignes[] = $ligneTva;
                }
            }

            // 3. Sauvegarde de l'écriture et des lignes
            $this->em->persist($journal); // Met à jour le dernier numéro
            $this->em->persist($ecriture);
            
            foreach ($lignes as $ligne) {
                $this->em->persist($ligne);
                $ecriture->addLignesEcriture($ligne);
            }

            // 4. Calcul des totaux et vérification équilibre
            $ecriture->calculateTotaux();
            if (!$ecriture->checkEquilibre()) {
                throw new \Exception("Écriture comptable déséquilibrée pour la facture {$facture->getNumeroFacture()}");
            }

            // Flush pour obtenir l'ID avant sécurisation
            $this->em->flush();

            // 5. Sécurisation selon NF203 (si utilisateur connecté)
            $user = $this->security->getUser();
            if ($user) {
                $this->integrityService->secureDocument(
                    $ecriture,
                    $user,
                    $this->requestStack->getCurrentRequest()?->getClientIp() ?? '127.0.0.1'
                );
            }

            // 6. Audit trail (si utilisateur connecté)
            if ($user) {
                $this->auditService->logEntityChange(
                    $ecriture,
                    'CREATE',
                    [],
                    [
                        'numeroEcriture' => $ecriture->getNumeroEcriture(),
                        'montantTtc' => $facture->getTotalTtc(),
                        'nombreLignes' => count($lignes)
                    ],
                    "Comptabilisation automatique de la facture {$facture->getNumeroFacture()}"
                );
            }

            $this->em->flush();
            $this->em->commit();

            $this->logger->info('Facture comptabilisée avec succès', [
                'facture_id' => $facture->getId(),
                'numero_facture' => $facture->getNumeroFacture(),
                'numero_ecriture' => $numeroEcriture,
                'montant_ttc' => $facture->getTotalTtc()
            ]);

            return [
                'success' => true,
                'ecriture' => $ecriture,
                'lignes' => $lignes,
                'numeroEcriture' => $numeroEcriture,
                'message' => "Facture {$facture->getNumeroFacture()} comptabilisée avec succès"
            ];

        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur lors de la comptabilisation de facture', [
                'facture_id' => $facture->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Erreur lors de la comptabilisation de la facture {$facture->getNumeroFacture()}"
            ];
        }
    }

    /**
     * Annule la comptabilisation d'une facture
     */
    public function annulerComptabilisationFacture(Facture $facture): array
    {
        try {
            // Recherche de l'écriture liée à cette facture
            $ecriture = $this->em->getRepository(EcritureComptable::class)->findOneBy([
                'documentType' => 'facture',
                'documentId' => $facture->getId()
            ]);

            if (!$ecriture) {
                return [
                    'success' => false,
                    'message' => "Aucune écriture comptable trouvée pour la facture {$facture->getNumeroFacture()}"
                ];
            }

            if ($ecriture->isIsValidee()) {
                return [
                    'success' => false,
                    'message' => "Impossible d'annuler une écriture validée"
                ];
            }

            $this->em->beginTransaction();

            // Audit avant suppression
            $this->auditService->logEntityChange(
                $ecriture,
                'DELETE',
                [
                    'numeroEcriture' => $ecriture->getNumeroEcriture(),
                    'totalDebit' => $ecriture->getTotalDebit(),
                    'totalCredit' => $ecriture->getTotalCredit()
                ],
                [],
                "Annulation comptabilisation facture {$facture->getNumeroFacture()}"
            );

            // Suppression de l'écriture (cascade sur les lignes)
            $this->em->remove($ecriture);
            $this->em->flush();
            $this->em->commit();

            $this->logger->info('Comptabilisation annulée', [
                'facture_id' => $facture->getId(),
                'numero_facture' => $facture->getNumeroFacture(),
                'numero_ecriture' => $ecriture->getNumeroEcriture()
            ]);

            return [
                'success' => true,
                'message' => "Comptabilisation de la facture {$facture->getNumeroFacture()} annulée"
            ];

        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur lors de l\'annulation de comptabilisation', [
                'facture_id' => $facture->getId(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si une facture est comptabilisée
     */
    public function isFactureComptabilisee(Facture $facture): bool
    {
        $ecriture = $this->em->getRepository(EcritureComptable::class)->findOneBy([
            'documentType' => 'facture',
            'documentId' => $facture->getId()
        ]);

        return $ecriture !== null;
    }

    /**
     * Calcule la ventilation TVA d'une facture
     */
    private function calculerVentilationTVA(Facture $facture): array
    {
        $ventilation = [];

        foreach ($facture->getFactureItems() as $item) {
            $tauxTva = floatval($item->getTvaPercent() ?? '20.00'); // TVA par défaut 20%
            
            if (!isset($ventilation[$tauxTva])) {
                $ventilation[$tauxTva] = [
                    'ht' => '0.00',
                    'tva' => '0.00'
                ];
            }

            $montantHt = $item->getTotalLigneHt();
            $montantTva = bcmul($montantHt, bcdiv($tauxTva, '100', 4), 2);

            $ventilation[$tauxTva]['ht'] = bcadd($ventilation[$tauxTva]['ht'], $montantHt, 2);
            $ventilation[$tauxTva]['tva'] = bcadd($ventilation[$tauxTva]['tva'], $montantTva, 2);
        }

        return $ventilation;
    }

    /**
     * Détermine le compte de vente selon le type de produit et le taux de TVA
     */
    private function determinerCompteVente(Facture $facture, float $tauxTva): ComptePCG
    {
        // Logique de détermination du compte de vente
        // À adapter selon les règles métier de l'entreprise
        
        if ($tauxTva == 0) {
            // Ventes exonérées de TVA
            return $this->compteRepo->findOneBy(['numeroCompte' => '701200']) 
                ?? $this->getCompteVenteDefaut();
        }
        
        // Ventes normales avec TVA
        return $this->compteRepo->findOneBy(['numeroCompte' => '701000']) 
            ?? $this->getCompteVenteDefaut();
    }

    /**
     * Détermine le compte TVA selon le taux
     */
    private function determinerCompteTVA(float $tauxTva): ComptePCG
    {
        $numeroCompte = match($tauxTva) {
            20.0 => '445711', // TVA collectée 20%
            10.0 => '445712', // TVA collectée 10%
            5.5 => '445713',  // TVA collectée 5.5%
            2.1 => '445714',  // TVA collectée 2.1%
            default => '445710' // TVA collectée autres taux
        };

        return $this->compteRepo->findOneBy(['numeroCompte' => $numeroCompte]) 
            ?? $this->getCompteTvaDefaut();
    }

    /**
     * Crée une ligne d'écriture
     */
    private function creerLigneEcriture(array $data): LigneEcriture
    {
        $ligne = new LigneEcriture();
        
        $ligne->setEcriture($data['ecriture']);
        $ligne->setComptePCG($data['compte']);
        $ligne->setMontantDebit($data['montantDebit']);
        $ligne->setMontantCredit($data['montantCredit']);
        $ligne->setLibelleLigne($data['libelle']);
        
        if (isset($data['compteAuxiliaire'])) {
            $ligne->setCompteAuxiliaire($data['compteAuxiliaire']);
        }
        if (isset($data['compteAuxiliaireLibelle'])) {
            $ligne->setCompteAuxiliaireLibelle($data['compteAuxiliaireLibelle']);
        }
        if (isset($data['dateEcheance'])) {
            $ligne->setDateEcheance($data['dateEcheance']);
        }

        return $ligne;
    }

    /**
     * Génère le code auxiliaire client
     */
    private function generateCompteAuxiliaireClient($client): string
    {
        return 'C' . str_pad($client->getId(), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère l'exercice comptable actuel
     */
    private function getExerciceActuel(): ExerciceComptable
    {
        $exercice = $this->exerciceRepo->findExerciceActuel();
        
        if (!$exercice) {
            throw new \Exception("Aucun exercice comptable ouvert trouvé");
        }

        return $exercice;
    }

    /**
     * Récupère le journal des ventes
     */
    private function getJournalVentes(): JournalComptable
    {
        $journal = $this->journalRepo->findOneBy(['code' => 'VTE']);
        
        if (!$journal) {
            throw new \Exception("Journal des ventes (VTE) non trouvé");
        }

        return $journal;
    }

    /**
     * Récupère le compte client par défaut
     */
    private function getCompteClient(): ComptePCG
    {
        $compte = $this->compteRepo->findOneBy(['numeroCompte' => '411000']);
        
        if (!$compte) {
            throw new \Exception("Compte client 411000 non trouvé");
        }

        return $compte;
    }

    /**
     * Récupère le compte de vente par défaut
     */
    private function getCompteVenteDefaut(): ComptePCG
    {
        $compte = $this->compteRepo->findOneBy(['numeroCompte' => '701000']);
        
        if (!$compte) {
            throw new \Exception("Compte de vente par défaut 701000 non trouvé");
        }

        return $compte;
    }

    /**
     * Récupère le compte TVA par défaut
     */
    private function getCompteTvaDefaut(): ComptePCG
    {
        $compte = $this->compteRepo->findOneBy(['numeroCompte' => '445710']);
        
        if (!$compte) {
            throw new \Exception("Compte TVA par défaut 445710 non trouvé");
        }

        return $compte;
    }

    /**
     * Retourne les statistiques de comptabilisation
     */
    public function getStatistiquesComptabilisation(): array
    {
        $qb = $this->em->createQueryBuilder();
        
        // Nombre de factures comptabilisées ce mois
        $facturesComptabiliseesMois = $qb->select('COUNT(DISTINCT e.id)')
            ->from(EcritureComptable::class, 'e')
            ->where('e.documentType = :type')
            ->andWhere('YEAR(e.dateEcriture) = YEAR(CURRENT_DATE())')
            ->andWhere('MONTH(e.dateEcriture) = MONTH(CURRENT_DATE())')
            ->setParameter('type', 'facture')
            ->getQuery()
            ->getSingleScalarResult();

        // Montant total comptabilisé ce mois
        $montantTotalMois = $qb->select('SUM(e.totalDebit)')
            ->from(EcritureComptable::class, 'e')
            ->where('e.documentType = :type')
            ->andWhere('YEAR(e.dateEcriture) = YEAR(CURRENT_DATE())')
            ->andWhere('MONTH(e.dateEcriture) = MONTH(CURRENT_DATE())')
            ->setParameter('type', 'facture')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'factures_comptabilisees_mois' => $facturesComptabiliseesMois,
            'montant_total_mois' => $montantTotalMois ?? '0.00',
            'derniere_maj' => new \DateTime()
        ];
    }
}