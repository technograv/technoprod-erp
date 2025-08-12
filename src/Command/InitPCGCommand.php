<?php

namespace App\Command;

use App\Entity\ExerciceComptable;
use App\Entity\User;
use App\Repository\ExerciceComptableRepository;
use App\Repository\UserRepository;
use App\Service\JournalService;
use App\Service\PCGService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[AsCommand(
    name: 'app:pcg:initialiser',
    description: 'Initialise le plan comptable général français avec les journaux obligatoires et un exercice comptable pour l\'année en cours'
)]
class InitPCGCommand extends Command
{
    public function __construct(
        private PCGService $pcgService,
        private JournalService $journalService,
        private EntityManagerInterface $entityManager,
        private ExerciceComptableRepository $exerciceRepository,
        private UserRepository $userRepository,
        private Security $security,
        private TokenStorageInterface $tokenStorage
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('annee', 'a', InputOption::VALUE_OPTIONAL, 'Année de l\'exercice comptable (par défaut : année courante)', date('Y'))
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force la réinitialisation même si des données existent déjà')
            ->addOption('skip-pcg', null, InputOption::VALUE_NONE, 'Ignorer l\'initialisation du plan comptable')
            ->addOption('skip-journaux', null, InputOption::VALUE_NONE, 'Ignorer l\'initialisation des journaux')
            ->addOption('skip-exercice', null, InputOption::VALUE_NONE, 'Ignorer la création de l\'exercice comptable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $annee = (int) $input->getOption('annee');
        $force = $input->getOption('force');
        $skipPcg = $input->getOption('skip-pcg');
        $skipJournaux = $input->getOption('skip-journaux');
        $skipExercice = $input->getOption('skip-exercice');

        $io->title('Initialisation du Plan Comptable Général Français');

        // Validation de l'année
        if ($annee < 2000 || $annee > 2100) {
            $io->error('L\'année doit être comprise entre 2000 et 2100');
            return Command::FAILURE;
        }

        $io->section('Configuration de l\'initialisation');
        $io->table(
            ['Paramètre', 'Valeur'],
            [
                ['Année de l\'exercice', $annee],
                ['Mode forcé', $force ? 'Oui' : 'Non'],
                ['Initialiser PCG', $skipPcg ? 'Non' : 'Oui'],
                ['Initialiser journaux', $skipJournaux ? 'Non' : 'Oui'],
                ['Créer exercice', $skipExercice ? 'Non' : 'Oui'],
            ]
        );

        if (!$io->confirm('Voulez-vous continuer l\'initialisation ?', true)) {
            $io->info('Initialisation annulée.');
            return Command::SUCCESS;
        }

        $rapportInitialisation = [
            'pcg' => null,
            'journaux' => null,
            'exercice' => null,
            'erreurs' => []
        ];

        // Récupération ou création d'un utilisateur système pour l'audit
        $utilisateurSysteme = $this->getOrCreateSystemUser();

        // 1. Initialisation du Plan Comptable Général
        if (!$skipPcg) {
            $io->section('1. Initialisation du Plan Comptable Général');
            
            try {
                $resultPcg = $this->initialiserPCGAvecUtilisateur($utilisateurSysteme);
                $rapportInitialisation['pcg'] = $resultPcg;

                if ($resultPcg['success']) {
                    $io->success($resultPcg['message']);
                    $io->table(
                        ['Statistique', 'Valeur'],
                        [
                            ['Comptes créés', count($resultPcg['comptes_crees'])],
                            ['Comptes existants', count($resultPcg['comptes_existants'])],
                            ['Total comptes', $resultPcg['total_comptes']],
                        ]
                    );

                    if (!empty($resultPcg['comptes_crees'])) {
                        $io->note('Nouveaux comptes créés : ' . implode(', ', array_slice($resultPcg['comptes_crees'], 0, 10)) . 
                                 (count($resultPcg['comptes_crees']) > 10 ? '...' : ''));
                    }
                } else {
                    $io->error('Erreur lors de l\'initialisation du PCG : ' . $resultPcg['error']);
                    $rapportInitialisation['erreurs'][] = 'PCG: ' . $resultPcg['error'];
                }
            } catch (\Exception $e) {
                $io->error('Exception lors de l\'initialisation du PCG : ' . $e->getMessage());
                $rapportInitialisation['erreurs'][] = 'PCG Exception: ' . $e->getMessage();
            }
        } else {
            $io->info('Initialisation du PCG ignorée (--skip-pcg)');
        }

        // 2. Initialisation des Journaux Comptables Obligatoires
        if (!$skipJournaux) {
            $io->section('2. Initialisation des Journaux Comptables Obligatoires');
            
            try {
                $resultJournaux = $this->initialiserJournauxAvecUtilisateur($utilisateurSysteme);
                $rapportInitialisation['journaux'] = $resultJournaux;

                if ($resultJournaux['success']) {
                    $io->success($resultJournaux['message']);
                    $io->table(
                        ['Statistique', 'Valeur'],
                        [
                            ['Journaux créés', count($resultJournaux['journaux_crees'])],
                            ['Journaux existants', count($resultJournaux['journaux_existants'])],
                            ['Total journaux', $resultJournaux['total_journaux']],
                        ]
                    );

                    if (!empty($resultJournaux['journaux_crees'])) {
                        $io->note('Nouveaux journaux créés : ' . implode(', ', $resultJournaux['journaux_crees']));
                    }
                } else {
                    $io->error('Erreur lors de l\'initialisation des journaux : ' . $resultJournaux['error']);
                    $rapportInitialisation['erreurs'][] = 'Journaux: ' . $resultJournaux['error'];
                }
            } catch (\Exception $e) {
                $io->error('Exception lors de l\'initialisation des journaux : ' . $e->getMessage());
                $rapportInitialisation['erreurs'][] = 'Journaux Exception: ' . $e->getMessage();
            }
        } else {
            $io->info('Initialisation des journaux ignorée (--skip-journaux)');
        }

        // 3. Création de l'Exercice Comptable
        if (!$skipExercice) {
            $io->section("3. Création de l'Exercice Comptable $annee");
            
            try {
                $resultExercice = $this->creerExerciceComptable($annee, $force);
                $rapportInitialisation['exercice'] = $resultExercice;

                if ($resultExercice['success']) {
                    $io->success($resultExercice['message']);
                    $io->table(
                        ['Propriété', 'Valeur'],
                        [
                            ['Année', $resultExercice['exercice']->getAnneeExercice()],
                            ['Période', $resultExercice['exercice']->getPeriodeTexte()],
                            ['Statut', $resultExercice['exercice']->getStatut()],
                            ['Créé le', $resultExercice['exercice']->getCreatedAt()->format('d/m/Y H:i:s')],
                        ]
                    );
                } else {
                    $io->error('Erreur lors de la création de l\'exercice : ' . $resultExercice['error']);
                    $rapportInitialisation['erreurs'][] = 'Exercice: ' . $resultExercice['error'];
                }
            } catch (\Exception $e) {
                $io->error('Exception lors de la création de l\'exercice : ' . $e->getMessage());
                $rapportInitialisation['erreurs'][] = 'Exercice Exception: ' . $e->getMessage();
            }
        } else {
            $io->info('Création de l\'exercice ignorée (--skip-exercice)');
        }

        // 4. Rapport Final d'Initialisation
        $this->afficherRapportFinal($io, $rapportInitialisation);

        // Retour du code de sortie
        return empty($rapportInitialisation['erreurs']) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Crée un exercice comptable pour l'année spécifiée
     */
    private function creerExerciceComptable(int $annee, bool $force): array
    {
        try {
            // Vérifier si un exercice existe déjà pour cette année
            $exerciceExistant = $this->exerciceRepository->findOneBy(['anneeExercice' => $annee]);

            if ($exerciceExistant && !$force) {
                return [
                    'success' => false,
                    'error' => "Un exercice comptable pour l'année $annee existe déjà. Utilisez --force pour le recréer."
                ];
            }

            $this->entityManager->beginTransaction();

            // Supprimer l'exercice existant si mode force
            if ($exerciceExistant && $force) {
                $this->entityManager->remove($exerciceExistant);
                $this->entityManager->flush();
            }

            // Créer le nouvel exercice
            $exercice = new ExerciceComptable();
            $exercice->setAnneeExercice($annee);
            
            // Dates par défaut : du 1er janvier au 31 décembre
            $dateDebut = new \DateTime("$annee-01-01");
            $dateFin = new \DateTime("$annee-12-31");
            
            $exercice->setDateDebut($dateDebut);
            $exercice->setDateFin($dateFin);
            $exercice->setStatut('ouvert');
            
            // Utilisateur créateur (si disponible)
            $user = $this->security->getUser();
            if ($user) {
                $exercice->setCreatedBy($user);
            }

            // Métadonnées d'initialisation
            $exercice->setMetadonnees([
                'initialise_par_commande' => true,
                'date_initialisation' => (new \DateTime())->format('Y-m-d H:i:s'),
                'version_pcg' => '2025',
                'type_exercice' => 'standard'
            ]);

            $this->entityManager->persist($exercice);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return [
                'success' => true,
                'exercice' => $exercice,
                'message' => "Exercice comptable $annee créé avec succès",
                'action' => $exerciceExistant ? 'recreated' : 'created'
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Affiche le rapport final d'initialisation
     */
    private function afficherRapportFinal(SymfonyStyle $io, array $rapport): void
    {
        $io->section('Rapport Final d\'Initialisation');

        // Résumé global
        $succesTotal = empty($rapport['erreurs']);
        
        if ($succesTotal) {
            $io->success('Initialisation du Plan Comptable Général terminée avec succès !');
        } else {
            $io->error('Initialisation terminée avec des erreurs');
        }

        // Détails par composant
        $tableauResume = [];

        if ($rapport['pcg']) {
            $status = $rapport['pcg']['success'] ? '✅ Succès' : '❌ Échec';
            $details = $rapport['pcg']['success'] 
                ? sprintf('%d comptes créés, %d existants', 
                    count($rapport['pcg']['comptes_crees']), 
                    count($rapport['pcg']['comptes_existants']))
                : $rapport['pcg']['error'];
            $tableauResume[] = ['Plan Comptable', $status, $details];
        }

        if ($rapport['journaux']) {
            $status = $rapport['journaux']['success'] ? '✅ Succès' : '❌ Échec';
            $details = $rapport['journaux']['success'] 
                ? sprintf('%d journaux créés, %d existants', 
                    count($rapport['journaux']['journaux_crees']), 
                    count($rapport['journaux']['journaux_existants']))
                : $rapport['journaux']['error'];
            $tableauResume[] = ['Journaux Comptables', $status, $details];
        }

        if ($rapport['exercice']) {
            $status = $rapport['exercice']['success'] ? '✅ Succès' : '❌ Échec';
            $details = $rapport['exercice']['success'] 
                ? sprintf('Exercice %d créé (%s)', 
                    $rapport['exercice']['exercice']->getAnneeExercice(),
                    $rapport['exercice']['action'])
                : $rapport['exercice']['error'];
            $tableauResume[] = ['Exercice Comptable', $status, $details];
        }

        if (!empty($tableauResume)) {
            $io->table(['Composant', 'Statut', 'Détails'], $tableauResume);
        }

        // Affichage des erreurs
        if (!empty($rapport['erreurs'])) {
            $io->warning('Erreurs rencontrées :');
            foreach ($rapport['erreurs'] as $erreur) {
                $io->text('• ' . $erreur);
            }
        }

        // Conseils d'utilisation
        if ($succesTotal) {
            $io->note([
                'Le plan comptable français est maintenant initialisé.',
                'Vous pouvez commencer à créer des écritures comptables.',
                '',
                'Commandes utiles :',
                '• app:pcg:initialiser --help : Afficher l\'aide complète',
                '• doctrine:query:sql "SELECT COUNT(*) FROM compte_pcg" : Vérifier les comptes',
                '• doctrine:query:sql "SELECT code, libelle FROM journal_comptable" : Lister les journaux'
            ]);
        }
    }

    /**
     * Récupère un utilisateur pour les opérations d'audit
     */
    private function getOrCreateSystemUser(): User
    {
        // Chercher d'abord l'utilisateur admin
        $systemUser = $this->userRepository->findOneBy(['email' => 'admin@technoprod.com']);
        
        if (!$systemUser) {
            // Chercher n'importe quel utilisateur actif
            $systemUser = $this->userRepository->findOneBy(['isActive' => true]);
        }
        
        if (!$systemUser) {
            // En dernier recours, créer un utilisateur système
            $systemUser = new User();
            $systemUser->setEmail('system@technoprod.local');
            $systemUser->setNom('Système');
            $systemUser->setPrenom('Console');
            $systemUser->setRoles(['ROLE_SYSTEM']);
            $systemUser->setIsActive(true);
            $systemUser->setCreatedAt(new \DateTimeImmutable());
            $systemUser->setUpdatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($systemUser);
            $this->entityManager->flush();
        }
        
        return $systemUser;
    }

    /**
     * Initialise le PCG avec un utilisateur spécifié pour l'audit
     */
    private function initialiserPCGAvecUtilisateur(User $user): array
    {
        // Sauvegarder le token actuel
        $originalToken = $this->tokenStorage->getToken();
        
        try {
            // Définir un nouveau token avec l'utilisateur spécifié
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
            
            // Exécuter l'initialisation du PCG
            return $this->pcgService->initialiserPlanComptable();
            
        } finally {
            // Restaurer le token original
            $this->tokenStorage->setToken($originalToken);
        }
    }

    /**
     * Initialise les journaux avec un utilisateur spécifié pour l'audit
     */
    private function initialiserJournauxAvecUtilisateur(User $user): array
    {
        // Sauvegarder le token actuel
        $originalToken = $this->tokenStorage->getToken();
        
        try {
            // Définir un nouveau token avec l'utilisateur spécifié
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
            
            // Exécuter l'initialisation des journaux
            return $this->journalService->initialiserJournauxObligatoires();
            
        } finally {
            // Restaurer le token original
            $this->tokenStorage->setToken($originalToken);
        }
    }
}