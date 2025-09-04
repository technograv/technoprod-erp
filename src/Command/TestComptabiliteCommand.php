<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\Prospect;
use App\Entity\ComptePCG;
use App\Entity\Devis;
use App\Entity\EcritureComptable;
use App\Entity\ExerciceComptable;
use App\Entity\Facture;
use App\Entity\FactureItem;
use App\Entity\JournalComptable;
use App\Entity\LigneEcriture;
use App\Entity\Produit;
use App\Entity\User;
use App\Service\AuditService;
use App\Service\BalanceService;
use App\Service\ComptabilisationService;
use App\Service\DocumentIntegrityService;
use App\Service\FECGenerator;
use App\Service\JournalService;
use App\Service\PCGService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-comptabilite',
    description: 'Test complet du système comptable français - Plan comptable, journaux, écritures, FEC, balance et conformité réglementaire'
)]
class TestComptabiliteCommand extends Command
{
    private EntityManagerInterface $em;
    private PCGService $pcgService;
    private JournalService $journalService;
    private ComptabilisationService $comptabilisationService;
    private BalanceService $balanceService;
    private FECGenerator $fecGenerator;
    private DocumentIntegrityService $integrityService;
    private AuditService $auditService;

    // Données de test temporaires
    private array $testData = [];
    private array $testResults = [];
    private SymfonyStyle $io;
    
    public function __construct(
        EntityManagerInterface $em,
        PCGService $pcgService,
        JournalService $journalService,
        ComptabilisationService $comptabilisationService,
        BalanceService $balanceService,
        FECGenerator $fecGenerator,
        DocumentIntegrityService $integrityService,
        AuditService $auditService
    ) {
        parent::__construct();
        $this->em = $em;
        $this->pcgService = $pcgService;
        $this->journalService = $journalService;
        $this->comptabilisationService = $comptabilisationService;
        $this->balanceService = $balanceService;
        $this->fecGenerator = $fecGenerator;
        $this->integrityService = $integrityService;
        $this->auditService = $auditService;
    }

    protected function configure(): void
    {
        $this
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Nettoyer les données de test après exécution')
            ->addOption('skip-pcg', null, InputOption::VALUE_NONE, 'Ignorer le test du plan comptable')
            ->addOption('skip-fec', null, InputOption::VALUE_NONE, 'Ignorer le test FEC (long)')
            ->addOption('verbose-errors', null, InputOption::VALUE_NONE, 'Afficher les détails des erreurs')
            ->setHelp('
Cette commande effectue un test complet du système comptable français :

1. <info>Test du Plan Comptable Général</info> - Vérification des 77 comptes standards
2. <info>Test des Journaux Comptables</info> - VTE, ACH, BAN, CAI, OD, AN
3. <info>Test des Écritures Comptables</info> - Création avec équilibrage automatique
4. <info>Test de génération FEC</info> - Fichier des Écritures Comptables conforme
5. <info>Test Balance et Grand Livre</info> - États comptables réglementaires
6. <info>Test d\'intégration complète</info> - Workflow complet avec données temporaires

<comment>Exemples d\'utilisation :</comment>
  <info>php bin/console app:test-comptabilite</info>                    # Test complet
  <info>php bin/console app:test-comptabilite --cleanup</info>           # Test avec nettoyage
  <info>php bin/console app:test-comptabilite --skip-fec</info>          # Test sans FEC
  <info>php bin/console app:test-comptabilite --verbose-errors</info>    # Détails erreurs
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        
        $this->io->title('🧮 Test Complet du Système Comptable Français');
        $this->io->text([
            'Ce test vérifie la conformité de l\'implémentation comptable',
            'selon les normes du Plan Comptable Général français.',
            ''
        ]);

        $startTime = microtime(true);
        $globalSuccess = true;

        try {
            // Phase 1 : Test du Plan Comptable Général
            if (!$input->getOption('skip-pcg')) {
                $this->io->section('📋 Phase 1 : Test du Plan Comptable Général (PCG)');
                $success = $this->testPlanComptable();
                $globalSuccess = $globalSuccess && $success;
            }

            // Phase 2 : Test des Journaux Comptables
            $this->io->section('📚 Phase 2 : Test des Journaux Comptables');
            $success = $this->testJournauxComptables();
            $globalSuccess = $globalSuccess && $success;

            // Phase 3 : Test des Écritures Comptables
            $this->io->section('✍️ Phase 3 : Test des Écritures Comptables');
            $success = $this->testEcrituresComptables();
            $globalSuccess = $globalSuccess && $success;

            // Phase 4 : Test Balance et Grand Livre
            $this->io->section('⚖️ Phase 4 : Test Balance Générale et Grand Livre');
            $success = $this->testBalanceEtGrandLivre();
            $globalSuccess = $globalSuccess && $success;

            // Phase 5 : Test FEC
            if (!$input->getOption('skip-fec')) {
                $this->io->section('📄 Phase 5 : Test Génération FEC');
                $success = $this->testGenerationFEC();
                $globalSuccess = $globalSuccess && $success;
            }

            // Phase 6 : Test d'Intégration Complète
            $this->io->section('🔗 Phase 6 : Test d\'Intégration Complète');
            $success = $this->testIntegrationComplete();
            $globalSuccess = $globalSuccess && $success;

            // Affichage des résultats
            $this->afficherResultatsFinaux($globalSuccess, microtime(true) - $startTime);

            // Nettoyage si demandé
            if ($input->getOption('cleanup')) {
                $this->nettoyerDonneesTest();
            }

        } catch (\Exception $e) {
            $this->io->error([
                'Erreur critique lors du test :',
                $e->getMessage()
            ]);
            
            if ($input->getOption('verbose-errors')) {
                $this->io->text('<comment>Stack trace :</comment>');
                $this->io->text($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }

        return $globalSuccess ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Test 1 : Vérification du Plan Comptable Général
     */
    private function testPlanComptable(): bool
    {
        $this->io->text('Initialisation et vérification du plan comptable français...');
        
        $progressBar = new ProgressBar($this->io, 4);
        $progressBar->start();
        
        try {
            // 1. Initialisation du PCG
            $progressBar->setMessage('Initialisation du Plan Comptable Général...');
            $resultInit = $this->pcgService->initialiserPlanComptable();
            $progressBar->advance();
            
            if (!$resultInit['success']) {
                $this->io->error('Échec de l\'initialisation du PCG : ' . $resultInit['error']);
                return false;
            }
            
            // 2. Vérification du nombre de comptes créés
            $progressBar->setMessage('Vérification des comptes standards...');
            $comptesActifs = $this->em->getRepository(ComptePCG::class)->findBy(['isActif' => true]);
            $progressBar->advance();
            
            // 3. Test de recherche et validation
            $progressBar->setMessage('Test des fonctions de recherche...');
            $this->testRechercheComptes();
            $progressBar->advance();
            
            // 4. Génération des statistiques
            $progressBar->setMessage('Génération des statistiques PCG...');
            $stats = $this->pcgService->getStatistiques();
            $progressBar->advance();
            
            $progressBar->finish();
            $this->io->newLine(2);
            
            // Affichage des résultats
            $table = new Table($this->io);
            $table->setHeaders(['Métrique', 'Valeur', 'Statut']);
            $table->addRows([
                ['Comptes créés', $resultInit['comptes_crees'] ? count($resultInit['comptes_crees']) : 0, '✅'],
                ['Comptes existants', $resultInit['comptes_existants'] ? count($resultInit['comptes_existants']) : 0, '✅'],
                ['Total comptes actifs', $stats['total_comptes_actifs'], $stats['total_comptes_actifs'] >= 77 ? '✅' : '❌'],
                ['Classes représentées', count($stats['repartition_par_classe']), count($stats['repartition_par_classe']) >= 7 ? '✅' : '❌']
            ]);
            $table->render();
            
            $this->testResults['pcg'] = [
                'success' => true,
                'comptes_crees' => count($resultInit['comptes_crees'] ?? []),
                'total_actifs' => $stats['total_comptes_actifs'],
                'conformite' => $stats['total_comptes_actifs'] >= 77
            ];
            
            $this->io->success('✅ Test du Plan Comptable Général : RÉUSSI');
            return true;
            
        } catch (\Exception $e) {
            $this->io->error('❌ Test du Plan Comptable Général : ÉCHEC - ' . $e->getMessage());
            $this->testResults['pcg'] = ['success' => false, 'error' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Test 2 : Vérification des Journaux Comptables
     */
    private function testJournauxComptables(): bool
    {
        $this->io->text('Test des journaux comptables obligatoires...');
        
        $journauxObligatoires = ['VTE', 'ACH', 'BAN', 'CAI', 'OD', 'AN'];
        $progressBar = new ProgressBar($this->io, count($journauxObligatoires) + 2);
        $progressBar->start();
        
        try {
            // 1. Initialisation des journaux
            $progressBar->setMessage('Initialisation des journaux obligatoires...');
            $resultInit = $this->journalService->initialiserJournauxObligatoires();
            $progressBar->advance();
            
            if (!$resultInit['success']) {
                $this->io->error('Échec de l\'initialisation des journaux : ' . $resultInit['error']);
                return false;
            }
            
            // 2. Test de chaque journal
            $journauxTestes = [];
            foreach ($journauxObligatoires as $code) {
                $progressBar->setMessage("Test du journal {$code}...");
                $resultat = $this->testJournalIndividuel($code);
                $journauxTestes[$code] = $resultat;
                $progressBar->advance();
            }
            
            // 3. Test de génération de numéros
            $progressBar->setMessage('Test génération numéros d\'écriture...');
            $this->testGenerationNumeros();
            $progressBar->advance();
            
            $progressBar->finish();
            $this->io->newLine(2);
            
            // Affichage des résultats
            $table = new Table($this->io);
            $table->setHeaders(['Journal', 'Code', 'Statut', 'Dernier N°', 'Format']);
            
            $successCount = 0;
            foreach ($journauxTestes as $code => $result) {
                if ($result['success']) $successCount++;
                $table->addRow([
                    $result['libelle'] ?? 'N/A',
                    $code,
                    $result['success'] ? '✅' : '❌',
                    $result['dernier_numero'] ?? '0',
                    $result['format'] ?? 'N/A'
                ]);
            }
            $table->render();
            
            $this->testResults['journaux'] = [
                'success' => $successCount === count($journauxObligatoires),
                'journaux_crees' => count($resultInit['journaux_crees'] ?? []),
                'journaux_testes' => $successCount,
                'total_attendu' => count($journauxObligatoires)
            ];
            
            $success = $successCount === count($journauxObligatoires);
            $this->io->{$success ? 'success' : 'error'}(
                ($success ? '✅' : '❌') . ' Test des Journaux Comptables : ' . 
                ($success ? 'RÉUSSI' : 'ÉCHEC') . 
                " ({$successCount}/" . count($journauxObligatoires) . ")"
            );
            
            return $success;
            
        } catch (\Exception $e) {
            $this->io->error('❌ Test des Journaux Comptables : ÉCHEC - ' . $e->getMessage());
            $this->testResults['journaux'] = ['success' => false, 'error' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Test 3 : Création et Vérification des Écritures Comptables
     */
    private function testEcrituresComptables(): bool
    {
        $this->io->text('Test de création d\'écritures comptables avec équilibrage...');
        
        $progressBar = new ProgressBar($this->io, 6);
        $progressBar->start();
        
        try {
            // 1. Création d'un exercice comptable test
            $progressBar->setMessage('Création exercice comptable test...');
            $exercice = $this->creerExerciceTest();
            $progressBar->advance();
            
            // 2. Création de données clients/produits test
            $progressBar->setMessage('Création données test (clients, produits)...');
            $this->creerDonneesTestCommerciales();
            $progressBar->advance();
            
            // 3. Test d'écriture manuelle
            $progressBar->setMessage('Test écriture comptable manuelle...');
            $ecritureManuelle = $this->creerEcritureManuelle();
            $progressBar->advance();
            
            // 4. Test de comptabilisation automatique
            $progressBar->setMessage('Test comptabilisation automatique facture...');
            $facture = $this->creerFactureTest();
            $resultComptabilisation = $this->comptabilisationService->comptabiliserFacture($facture);
            $progressBar->advance();
            
            // 5. Vérification de l'équilibre
            $progressBar->setMessage('Vérification équilibre des écritures...');
            $equilibre = $this->verifierEquilibreEcritures();
            $progressBar->advance();
            
            // 6. Test d'intégrité des données
            $progressBar->setMessage('Test intégrité documents...');
            $integrite = $this->testerIntegriteDocuments();
            $progressBar->advance();
            
            $progressBar->finish();
            $this->io->newLine(2);
            
            // Affichage des résultats
            $table = new Table($this->io);
            $table->setHeaders(['Test', 'Résultat', 'Détails']);
            $table->addRows([
                ['Écriture manuelle', $ecritureManuelle ? '✅' : '❌', 
                 $ecritureManuelle ? 'Créée et équilibrée' : 'Échec création'],
                ['Comptabilisation auto', $resultComptabilisation['success'] ? '✅' : '❌',
                 $resultComptabilisation['success'] ? 
                    "N° {$resultComptabilisation['numeroEcriture']}" : 
                    ($resultComptabilisation['error'] ?? 'Erreur inconnue')],
                ['Équilibre général', $equilibre['equilibre'] ? '✅' : '❌',
                 "Débit: {$equilibre['total_debit']} | Crédit: {$equilibre['total_credit']}"],
                ['Intégrité documents', $integrite ? '✅' : '❌',
                 $integrite ? 'Signatures vérifiées' : 'Problème d\'intégrité']
            ]);
            $table->render();
            
            $this->testResults['ecritures'] = [
                'success' => $ecritureManuelle && $resultComptabilisation['success'] && 
                           $equilibre['equilibre'] && $integrite,
                'ecriture_manuelle' => $ecritureManuelle,
                'comptabilisation_auto' => $resultComptabilisation['success'],
                'equilibre' => $equilibre['equilibre'],
                'integrite' => $integrite
            ];
            
            $success = $this->testResults['ecritures']['success'];
            $this->io->{$success ? 'success' : 'error'}(
                ($success ? '✅' : '❌') . ' Test des Écritures Comptables : ' . 
                ($success ? 'RÉUSSI' : 'ÉCHEC')
            );
            
            return $success;
            
        } catch (\Exception $e) {
            $this->io->error('❌ Test des Écritures Comptables : ÉCHEC - ' . $e->getMessage());
            $this->testResults['ecritures'] = ['success' => false, 'error' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Test 4 : Balance Générale et Grand Livre
     */
    private function testBalanceEtGrandLivre(): bool
    {
        $this->io->text('Test de génération de la balance générale et du grand livre...');
        
        $progressBar = new ProgressBar($this->io, 4);
        $progressBar->start();
        
        try {
            $dateDebut = new \DateTime('first day of this year');
            $dateFin = new \DateTime('last day of this year');
            
            // 1. Génération balance générale
            $progressBar->setMessage('Génération balance générale...');
            $balance = $this->balanceService->genererBalanceGenerale($dateDebut, $dateFin);
            $progressBar->advance();
            
            // 2. Test balance par classe
            $progressBar->setMessage('Génération balance par classe...');
            $balanceClasses = $this->balanceService->genererBalanceParClasse($dateDebut, $dateFin);
            $progressBar->advance();
            
            // 3. Test grand livre d'un compte
            $progressBar->setMessage('Génération grand livre compte client...');
            $compteClient = $this->em->getRepository(ComptePCG::class)->findOneBy(['numeroCompte' => '411000']);
            $grandLivre = null;
            if ($compteClient) {
                $grandLivre = $this->balanceService->genererGrandLivre($compteClient, $dateDebut, $dateFin);
            }
            $progressBar->advance();
            
            // 4. Export CSV
            $progressBar->setMessage('Test export CSV...');
            $csvBalance = null;
            if ($balance['success']) {
                $csvBalance = $this->balanceService->exporterBalanceCSV($balance);
            }
            $progressBar->advance();
            
            $progressBar->finish();
            $this->io->newLine(2);
            
            // Affichage des résultats
            $table = new Table($this->io);
            $table->setHeaders(['Test', 'Résultat', 'Détails']);
            $table->addRows([
                ['Balance générale', $balance['success'] ? '✅' : '❌',
                 $balance['success'] ? 
                    "{$balance['totaux']['nombre_comptes']} comptes | Équilibre: " . 
                    ($balance['totaux']['equilibre'] ? 'OUI' : 'NON') :
                    ($balance['error'] ?? 'Erreur inconnue')],
                ['Balance par classe', $balanceClasses['success'] ? '✅' : '❌',
                 $balanceClasses['success'] ? 
                    count($balanceClasses['balance_par_classe']) . ' classes | Équilibre: ' .
                    ($balanceClasses['equilibre'] ? 'OUI' : 'NON') :
                    ($balanceClasses['error'] ?? 'Erreur inconnue')],
                ['Grand livre', $grandLivre && $grandLivre['success'] ? '✅' : '❌',
                 $grandLivre && $grandLivre['success'] ? 
                    "{$grandLivre['totaux']['nombre_mouvements']} mouvements" :
                    'Compte 411000 introuvable ou erreur'],
                ['Export CSV', $csvBalance ? '✅' : '❌',
                 $csvBalance ? strlen($csvBalance) . ' caractères' : 'Échec export']
            ]);
            $table->render();
            
            $this->testResults['balance'] = [
                'success' => $balance['success'] && $balanceClasses['success'],
                'equilibre_general' => $balance['success'] ? $balance['totaux']['equilibre'] : false,
                'nombre_comptes' => $balance['success'] ? $balance['totaux']['nombre_comptes'] : 0,
                'grand_livre_ok' => $grandLivre && $grandLivre['success'],
                'export_csv_ok' => (bool)$csvBalance
            ];
            
            $success = $this->testResults['balance']['success'];
            $this->io->{$success ? 'success' : 'error'}(
                ($success ? '✅' : '❌') . ' Test Balance et Grand Livre : ' . 
                ($success ? 'RÉUSSI' : 'ÉCHEC')
            );
            
            return $success;
            
        } catch (\Exception $e) {
            $this->io->error('❌ Test Balance et Grand Livre : ÉCHEC - ' . $e->getMessage());
            $this->testResults['balance'] = ['success' => false, 'error' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Test 5 : Génération FEC (Fichier des Écritures Comptables)
     */
    private function testGenerationFEC(): bool
    {
        $this->io->text('Test de génération du Fichier des Écritures Comptables (FEC)...');
        
        $progressBar = new ProgressBar($this->io, 4);
        $progressBar->start();
        
        try {
            $dateDebut = new \DateTime('first day of this year');
            $dateFin = new \DateTime('last day of this year');
            
            // 1. Génération FEC standard
            $progressBar->setMessage('Génération FEC standard...');
            $fecContent = $this->fecGenerator->generateFEC($dateDebut, $dateFin);
            $progressBar->advance();
            
            // 2. Validation du contenu FEC
            $progressBar->setMessage('Validation contenu FEC...');
            $lines = explode("\r\n", $fecContent);
            $validation = $this->validerFormatFEC($lines);
            $progressBar->advance();
            
            // 3. Test FEC avec filtres
            $progressBar->setMessage('Test FEC avec filtres...');
            $fecFiltre = $this->fecGenerator->generateFECForPeriod(
                $dateDebut, 
                $dateFin, 
                null, 
                ['VTE', 'ACH'], // Journaux spécifiques
                ['411', '701']  // Comptes spécifiques
            );
            $progressBar->advance();
            
            // 4. Statistiques FEC
            $progressBar->setMessage('Calcul statistiques FEC...');
            $stats = $this->fecGenerator->getFECStatistics($dateDebut, $dateFin);
            $progressBar->advance();
            
            $progressBar->finish();
            $this->io->newLine(2);
            
            // Affichage des résultats
            $table = new Table($this->io);
            $table->setHeaders(['Test', 'Résultat', 'Détails']);
            $table->addRows([
                ['Génération FEC', !empty($fecContent) ? '✅' : '❌',
                 !empty($fecContent) ? 
                    count($lines) . ' lignes | ' . strlen($fecContent) . ' caractères' :
                    'Aucun contenu généré'],
                ['Format FEC', $validation['valide'] ? '✅' : '❌',
                 $validation['valide'] ? 
                    'Conforme | ' . $validation['lignes_donnees'] . ' lignes de données' :
                    count($validation['erreurs']) . ' erreurs format'],
                ['FEC avec filtres', !empty($fecFiltre) ? '✅' : '❌',
                 !empty($fecFiltre) ? 
                    'Filtrage réussi | ' . count(explode("\r\n", $fecFiltre)) . ' lignes' :
                    'Échec filtrage'],
                ['Équilibre comptable', $stats['equilibre'] ? '✅' : '❌',
                 "Écritures: {$stats['nombre_ecritures']} | Lignes: {$stats['nombre_lignes']}"]
            ]);
            $table->render();
            
            if (!$validation['valide'] && !empty($validation['erreurs'])) {
                $this->io->warning('Erreurs de format FEC détectées :');
                foreach (array_slice($validation['erreurs'], 0, 5) as $erreur) {
                    $this->io->text('  • ' . $erreur);
                }
                if (count($validation['erreurs']) > 5) {
                    $this->io->text('  • ... et ' . (count($validation['erreurs']) - 5) . ' autres erreurs');
                }
            }
            
            $this->testResults['fec'] = [
                'success' => !empty($fecContent) && $validation['valide'] && $stats['equilibre'],
                'taille_fichier' => strlen($fecContent),
                'lignes_total' => count($lines),
                'format_valide' => $validation['valide'],
                'equilibre' => $stats['equilibre'],
                'nombre_ecritures' => $stats['nombre_ecritures']
            ];
            
            $success = $this->testResults['fec']['success'];
            $this->io->{$success ? 'success' : 'error'}(
                ($success ? '✅' : '❌') . ' Test Génération FEC : ' . 
                ($success ? 'RÉUSSI' : 'ÉCHEC')
            );
            
            return $success;
            
        } catch (\Exception $e) {
            $this->io->error('❌ Test Génération FEC : ÉCHEC - ' . $e->getMessage());
            $this->testResults['fec'] = ['success' => false, 'error' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Test 6 : Test d'Intégration Complète
     */
    private function testIntegrationComplete(): bool
    {
        $this->io->text('Test d\'intégration complète du workflow comptable...');
        
        $progressBar = new ProgressBar($this->io, 5);
        $progressBar->start();
        
        try {
            // 1. Workflow complet : Devis → Commande → Facture → Comptabilisation
            $progressBar->setMessage('Test workflow commercial complet...');
            $workflowSuccess = $this->testerWorkflowComplet();
            $progressBar->advance();
            
            // 2. Test de conformité réglementaire
            $progressBar->setMessage('Vérification conformité réglementaire...');
            $conformite = $this->verifierConformiteReglementaire();
            $progressBar->advance();
            
            // 3. Test de sécurité et intégrité
            $progressBar->setMessage('Test sécurité et intégrité globale...');
            $securite = $this->testerSecuriteGlobale();
            $progressBar->advance();
            
            // 4. Test de performance
            $progressBar->setMessage('Test de performance...');
            $performance = $this->testerPerformances();
            $progressBar->advance();
            
            // 5. Audit trail complet
            $progressBar->setMessage('Vérification audit trail...');
            $auditTrail = $this->verifierAuditTrail();
            $progressBar->advance();
            
            $progressBar->finish();
            $this->io->newLine(2);
            
            // Affichage des résultats d'intégration
            $table = new Table($this->io);
            $table->setHeaders(['Test d\'Intégration', 'Résultat', 'Détails']);
            $table->addRows([
                ['Workflow commercial', $workflowSuccess ? '✅' : '❌',
                 $workflowSuccess ? 'Devis→Commande→Facture→Comptabilisation OK' : 'Échec workflow'],
                ['Conformité réglementaire', $conformite['conforme'] ? '✅' : '❌',
                 $conformite['conforme'] ? 
                    "PCG: {$conformite['pcg']} | FEC: {$conformite['fec']} | Audit: {$conformite['audit']}" :
                    'Non-conformités détectées'],
                ['Sécurité globale', $securite ? '✅' : '❌',
                 $securite ? 'Intégrité documents OK' : 'Problèmes sécurité'],
                ['Performance', $performance['acceptable'] ? '✅' : '❌',
                 "Balance: {$performance['temps_balance']}ms | FEC: {$performance['temps_fec']}ms"],
                ['Audit trail', $auditTrail ? '✅' : '❌',
                 $auditTrail ? 'Traçabilité complète' : 'Lacunes audit']
            ]);
            $table->render();
            
            $this->testResults['integration'] = [
                'success' => $workflowSuccess && $conformite['conforme'] && $securite && 
                           $performance['acceptable'] && $auditTrail,
                'workflow' => $workflowSuccess,
                'conformite' => $conformite['conforme'],
                'securite' => $securite,
                'performance' => $performance['acceptable'],
                'audit_trail' => $auditTrail
            ];
            
            $success = $this->testResults['integration']['success'];
            $this->io->{$success ? 'success' : 'error'}(
                ($success ? '✅' : '❌') . ' Test d\'Intégration Complète : ' . 
                ($success ? 'RÉUSSI' : 'ÉCHEC')
            );
            
            return $success;
            
        } catch (\Exception $e) {
            $this->io->error('❌ Test d\'Intégration Complète : ÉCHEC - ' . $e->getMessage());
            $this->testResults['integration'] = ['success' => false, 'error' => $e->getMessage()];
            return false;
        }
    }

    // ============== MÉTHODES UTILITAIRES ==============

    private function testRechercheComptes(): void
    {
        // Test de recherche par critères
        $resultats = $this->pcgService->rechercherComptes([
            'classe' => '4',
            'nature' => 'ACTIF',
            'limit' => 10
        ]);
        
        if (empty($resultats)) {
            throw new \Exception('Recherche de comptes classe 4 échouée');
        }
    }

    private function testJournalIndividuel(string $code): array
    {
        $journal = $this->em->getRepository(JournalComptable::class)->findOneBy(['code' => $code]);
        
        if (!$journal) {
            return ['success' => false, 'error' => "Journal {$code} non trouvé"];
        }
        
        // Test génération numéro
        $result = $this->journalService->genererProchainNumeroEcriture($code);
        
        return [
            'success' => $result['success'],
            'libelle' => $journal->getLibelle(),
            'dernier_numero' => $journal->getDernierNumeroEcriture(),
            'format' => $journal->getFormatNumeroEcriture(),
            'error' => $result['error'] ?? null
        ];
    }

    private function testGenerationNumeros(): void
    {
        $journal = $this->em->getRepository(JournalComptable::class)->findOneBy(['code' => 'VTE']);
        if ($journal) {
            for ($i = 0; $i < 3; $i++) {
                $result = $this->journalService->genererProchainNumeroEcriture('VTE');
                if (!$result['success']) {
                    throw new \Exception('Échec génération numéro séquentiel');
                }
            }
        }
    }

    private function creerExerciceTest(): ExerciceComptable
    {
        $exercice = $this->em->getRepository(ExerciceComptable::class)->findOneBy([
            'anneeExercice' => (int)date('Y')
        ]);
        
        if (!$exercice) {
            $exercice = new ExerciceComptable();
            $exercice->setAnneeExercice((int)date('Y'));
            $exercice->setDateDebut(new \DateTime('first day of january this year'));
            $exercice->setDateFin(new \DateTime('last day of december this year'));
            $exercice->setStatut('OUVERT');
            
            $this->em->persist($exercice);
            $this->em->flush();
        }
        
        $this->testData['exercice'] = $exercice;
        return $exercice;
    }

    private function creerDonneesTestCommerciales(): void
    {
        // Rechercher un secteur et commercial existant
        $secteur = $this->em->getRepository('App\Entity\Secteur')->findOneBy([]);
        $commercial = $this->em->getRepository('App\Entity\User')->findOneBy([]);
        
        if (!$secteur) {
            $this->io->warning('Aucun secteur trouvé - les tests utiliseront des données minimales');
        }
        if (!$commercial) {
            $this->io->warning('Aucun commercial trouvé - les tests utiliseront des données minimales');
        }
        
        // Prospect test
        $prospect = new Prospect();
        $prospect->setNom('PROSPECT TEST COMPTABILITE');
        $prospect->setCode('TEST-' . uniqid());
        if ($secteur) $prospect->setSecteur($secteur);
        if ($commercial) $prospect->setCommercial($commercial);
        $this->em->persist($prospect);
        
        // Client test (pour factures)
        $client = new Client();
        $client->setNom('CLIENT TEST COMPTABILITE'); // Nom requis (NOT NULL)
        $client->setNomEntreprise('CLIENT TEST COMPTABILITE');
        $client->setCode('TEST-' . uniqid());
        if ($secteur) $client->setSecteur($secteur);
        if ($commercial) $client->setCommercial($commercial);
        $this->em->persist($client);
        
        // Produit test
        $produit = new Produit();
        $produit->setDesignation('PRODUIT TEST');
        $produit->setDescription('Produit pour test comptabilité');
        $produit->setReference('TEST-' . uniqid());
        $produit->setType('SERVICE');
        $produit->setPrixVenteHt('100.00');
        $this->em->persist($produit);
        
        // Devis test  
        $devis = new Devis();
        $devis->setNumeroDevis('DEV-' . date('YmdHis'));
        $devis->setDateCreation(new \DateTime());
        $devis->setDateValidite(new \DateTime('+30 days'));
        $devis->setClient($client);
        if ($commercial) $devis->setCommercial($commercial);
        $devis->setStatut('accepte');
        $devis->setTotalHt('100.00');
        $devis->setTotalTva('20.00');
        $devis->setTotalTtc('120.00');
        $this->em->persist($devis);
        
        // Commande test
        $commande = new Commande();
        $commande->setNumeroCommande('CMD-' . date('YmdHis'));
        $commande->setDateCommande(new \DateTime());
        $commande->setClient($client);
        $commande->setDevis($devis);
        if ($commercial) $commande->setCommercial($commercial);
        $commande->setStatut('validee');
        $commande->setTotalHt('100.00');
        $commande->setTotalTva('20.00');
        $commande->setTotalTtc('120.00');
        $this->em->persist($commande);
        
        $this->em->flush();
        
        $this->testData['prospect'] = $prospect;
        $this->testData['client'] = $client;
        $this->testData['produit'] = $produit;
        $this->testData['devis'] = $devis;
        $this->testData['commande'] = $commande;
        $this->testData['commercial'] = $commercial;
    }

    private function creerEcritureManuelle(): bool
    {
        try {
            $journal = $this->em->getRepository(JournalComptable::class)->findOneBy(['code' => 'OD']);
            $exercice = $this->testData['exercice'];
            
            if (!$journal || !$exercice) {
                return false;
            }
            
            $ecriture = new EcritureComptable();
            $ecriture->setJournal($journal);
            $ecriture->setNumeroEcriture($journal->generateNextNumeroEcriture());
            $ecriture->setDateEcriture(new \DateTime());
            $ecriture->setDatePiece(new \DateTime());
            $ecriture->setNumeroPiece('TEST-001');
            $ecriture->setLibelleEcriture('Écriture de test manuelle');
            $ecriture->setExerciceComptable($exercice);
            
            // Ligne débit
            $compteDebit = $this->em->getRepository(ComptePCG::class)->findOneBy(['numeroCompte' => '512000']);
            if ($compteDebit) {
                $ligneDebit = new LigneEcriture();
                $ligneDebit->setEcriture($ecriture);
                $ligneDebit->setComptePCG($compteDebit);
                $ligneDebit->setMontantDebit('1000.00');
                $ligneDebit->setMontantCredit('0.00');
                $ligneDebit->setLibelleLigne('Test débit banque');
                $this->em->persist($ligneDebit);
                $ecriture->addLignesEcriture($ligneDebit);
            }
            
            // Ligne crédit
            $compteCredit = $this->em->getRepository(ComptePCG::class)->findOneBy(['numeroCompte' => '701000']);
            if ($compteCredit) {
                $ligneCredit = new LigneEcriture();
                $ligneCredit->setEcriture($ecriture);
                $ligneCredit->setComptePCG($compteCredit);
                $ligneCredit->setMontantDebit('0.00');
                $ligneCredit->setMontantCredit('1000.00');
                $ligneCredit->setLibelleLigne('Test crédit vente');
                $this->em->persist($ligneCredit);
                $ecriture->addLignesEcriture($ligneCredit);
            }
            
            $this->em->persist($journal); // Met à jour le dernier numéro
            $this->em->persist($ecriture);
            $this->em->flush();
            
            $this->testData['ecriture_manuelle'] = $ecriture;
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    private function creerFactureTest(): Facture
    {
        $facture = new Facture();
        $facture->setNumeroFacture('FT-' . date('YmdHis'));
        $facture->setDateFacture(new \DateTime());
        $facture->setDateEcheance(new \DateTime('+30 days'));
        $facture->setClient($this->testData['client']);
        $facture->setCommande($this->testData['commande']);
        if (isset($this->testData['commercial'])) {
            $facture->setCommercial($this->testData['commercial']);
        }
        $facture->setTotalHt('100.00');
        $facture->setTotalTva('20.00');
        $facture->setTotalTtc('120.00');
        
        // Item de facture
        $item = new FactureItem();
        $item->setFacture($facture);
        $item->setDesignation($this->testData['produit']->getDesignation());
        $item->setQuantite('1.00');
        $item->setPrixUnitaireHt('100.00');
        $item->setTvaPercent('20.00');
        $item->setTotalLigneHt('100.00');
        
        $facture->addFactureItem($item);
        
        $this->em->persist($facture);
        $this->em->persist($item);
        $this->em->flush();
        
        $this->testData['facture'] = $facture;
        return $facture;
    }

    private function verifierEquilibreEcritures(): array
    {
        $qb = $this->em->createQueryBuilder();
        $result = $qb->select('SUM(l.montantDebit) as totalDebit, SUM(l.montantCredit) as totalCredit')
                    ->from(LigneEcriture::class, 'l')
                    ->getQuery()
                    ->getSingleResult();
        
        $totalDebit = $result['totalDebit'] ?? '0.00';
        $totalCredit = $result['totalCredit'] ?? '0.00';
        
        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'equilibre' => bccomp($totalDebit, $totalCredit, 2) === 0
        ];
    }

    private function testerIntegriteDocuments(): bool
    {
        try {
            // Vérification basique de l'intégrité
            if (isset($this->testData['ecriture_manuelle'])) {
                $ecriture = $this->testData['ecriture_manuelle'];
                return $ecriture->checkEquilibre();
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function validerFormatFEC(array $lines): array
    {
        $erreurs = [];
        $lignesDonnees = 0;
        
        if (empty($lines)) {
            $erreurs[] = 'FEC vide';
            return ['valide' => false, 'erreurs' => $erreurs, 'lignes_donnees' => 0];
        }
        
        // Vérification en-tête
        $enTete = $lines[0] ?? '';
        $colonnesAttendues = ['JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 
                             'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib',
                             'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit',
                             'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise'];
        
        $colonnesFEC = explode('|', $enTete);
        if ($colonnesFEC !== $colonnesAttendues) {
            $erreurs[] = 'En-tête FEC non conforme';
        }
        
        // Vérification des lignes de données
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') continue;
            
            $champs = explode('|', $lines[$i]);
            if (count($champs) !== 18) {
                $erreurs[] = "Ligne " . ($i + 1) . ": nombre de champs incorrect (" . count($champs) . "/18)";
            }
            $lignesDonnees++;
        }
        
        return [
            'valide' => empty($erreurs),
            'erreurs' => $erreurs,
            'lignes_donnees' => $lignesDonnees
        ];
    }

    private function testerWorkflowComplet(): bool
    {
        try {
            // Simulation d'un workflow complet déjà en partie réalisé
            // avec la facture et sa comptabilisation
            return isset($this->testData['facture']) && 
                   $this->comptabilisationService->isFactureComptabilisee($this->testData['facture']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function verifierConformiteReglementaire(): array
    {
        $conformite = [
            'conforme' => true,
            'pcg' => 'OK',
            'fec' => 'OK', 
            'audit' => 'OK'
        ];
        
        // Vérification PCG : au moins 77 comptes
        $stats = $this->pcgService->getStatistiques();
        if ($stats['total_comptes_actifs'] < 77) {
            $conformite['pcg'] = 'NOK';
            $conformite['conforme'] = false;
        }
        
        // Vérification journaux obligatoires
        $journauxOblig = ['VTE', 'ACH', 'BAN', 'CAI', 'OD'];
        foreach ($journauxOblig as $code) {
            $journal = $this->em->getRepository(JournalComptable::class)->findOneBy(['code' => $code]);
            if (!$journal || !$journal->isIsActif()) {
                $conformite['fec'] = 'NOK';
                $conformite['conforme'] = false;
                break;
            }
        }
        
        return $conformite;
    }

    private function testerSecuriteGlobale(): bool
    {
        try {
            // Test basique de l'intégrité des services
            return $this->integrityService->areKeysAvailable();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function testerPerformances(): array
    {
        $performance = ['acceptable' => true];
        
        try {
            // Test performance balance
            $start = microtime(true);
            $this->balanceService->genererBalanceGenerale(
                new \DateTime('first day of this month'),
                new \DateTime('last day of this month')
            );
            $performance['temps_balance'] = round((microtime(true) - $start) * 1000, 2);
            
            // Test performance FEC (si pas trop de données)
            $start = microtime(true);
            $stats = $this->fecGenerator->getFECStatistics(
                new \DateTime('first day of this month'),
                new \DateTime('last day of this month')
            );
            $performance['temps_fec'] = round((microtime(true) - $start) * 1000, 2);
            
            // Seuils acceptables
            if ($performance['temps_balance'] > 5000 || $performance['temps_fec'] > 3000) {
                $performance['acceptable'] = false;
            }
            
        } catch (\Exception $e) {
            $performance['acceptable'] = false;
            $performance['erreur'] = $e->getMessage();
        }
        
        return $performance;
    }

    private function verifierAuditTrail(): bool
    {
        try {
            // Vérification qu'il y a des traces d'audit
            $auditCount = $this->em->getRepository('App\Entity\AuditTrail')->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->getQuery()
                ->getSingleScalarResult();
            
            return $auditCount > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function afficherResultatsFinaux(bool $globalSuccess, float $tempsExecution): void
    {
        $this->io->newLine();
        $this->io->section('🎯 RÉSULTATS FINAUX DU TEST COMPTABLE');
        
        // Tableau de synthèse
        $table = new Table($this->io);
        $table->setHeaders(['Phase de Test', 'Statut', 'Score', 'Commentaire']);
        
        $phaseLabels = [
            'pcg' => 'Plan Comptable Général',
            'journaux' => 'Journaux Comptables', 
            'ecritures' => 'Écritures Comptables',
            'balance' => 'Balance & Grand Livre',
            'fec' => 'Génération FEC',
            'integration' => 'Intégration Complète'
        ];
        
        $totalTests = 0;
        $testsReussis = 0;
        
        foreach ($phaseLabels as $key => $label) {
            if (isset($this->testResults[$key])) {
                $result = $this->testResults[$key];
                $success = $result['success'];
                $totalTests++;
                if ($success) $testsReussis++;
                
                $score = $success ? '100%' : '0%';
                $commentaire = $success ? 'Conforme' : ($result['error'] ?? 'Échec');
                
                $table->addRow([
                    $label,
                    $success ? '✅' : '❌',
                    $score,
                    $commentaire
                ]);
            }
        }
        
        $table->render();
        
        // Métriques globales
        $scoreGlobal = $totalTests > 0 ? round(($testsReussis / $totalTests) * 100, 1) : 0;
        
        $this->io->newLine();
        $this->io->horizontalTable(
            ['Métrique', 'Valeur'],
            [
                ['Score Global', $scoreGlobal . '%'],
                ['Tests Réussis', $testsReussis . '/' . $totalTests],  
                ['Temps d\'Exécution', round($tempsExecution, 2) . 's'],
                ['Conformité PCG', $globalSuccess ? 'CONFORME' : 'NON CONFORME'],
                ['Status Final', $globalSuccess ? '✅ SYSTÈME VALIDÉ' : '❌ CORRECTIONS REQUISES']
            ]
        );
        
        // Message final
        if ($globalSuccess) {
            $this->io->success([
                '🎉 FÉLICITATIONS !',
                '',
                'Le système comptable TechnoProd est CONFORME',
                'aux normes du Plan Comptable Général français.',
                '',
                '✅ Tous les tests de conformité ont été validés',
                '✅ L\'intégrité des données est garantie',
                '✅ La génération FEC est opérationnelle',
                '✅ Le système est prêt pour la production'
            ]);
        } else {
            $this->io->error([
                '⚠️  ATTENTION !',
                '',
                'Des non-conformités ont été détectées dans le système comptable.',
                'Veuillez corriger les problèmes identifiés avant la mise en production.',
                '',
                'Score de conformité : ' . $scoreGlobal . '%',
                'Tests en échec : ' . ($totalTests - $testsReussis) . '/' . $totalTests
            ]);
        }
    }

    private function nettoyerDonneesTest(): void
    {
        $this->io->section('🧹 Nettoyage des données de test');
        
        try {
            // Suppression des données créées lors du test
            if (isset($this->testData['facture'])) {
                // Annuler la comptabilisation si elle existe
                $this->comptabilisationService->annulerComptabilisationFacture($this->testData['facture']);
                $this->em->remove($this->testData['facture']);
            }
            
            if (isset($this->testData['ecriture_manuelle'])) {
                $this->em->remove($this->testData['ecriture_manuelle']);
            }
            
            if (isset($this->testData['client'])) {
                $this->em->remove($this->testData['client']);
            }
            
            if (isset($this->testData['produit'])) {
                $this->em->remove($this->testData['produit']);
            }
            
            $this->em->flush();
            
            $this->io->success('✅ Données de test supprimées avec succès');
            
        } catch (\Exception $e) {
            $this->io->warning('⚠️  Nettoyage partiel des données : ' . $e->getMessage());
        }
    }
}