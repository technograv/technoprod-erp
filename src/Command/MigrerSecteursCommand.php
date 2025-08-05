<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Entity\AttributionSecteur;
use App\Service\ImportDivisionsAdministrativesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrer-secteurs',
    description: 'Migre les secteurs de l\'ancien système vers le nouveau système de divisions administratives',
)]
class MigrerSecteursCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImportDivisionsAdministrativesService $importService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans modifications')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer la migration même si des attributions existent')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $isDryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Migration des secteurs vers le nouveau système');

        if ($isDryRun) {
            $io->note('Mode simulation - aucune modification ne sera effectuée');
        }

        // 1. Analyse de l'état actuel
        $io->section('Analyse de l\'état actuel');
        
        $secteurs = $this->entityManager->getRepository(Secteur::class)->findAll();
        $stats = [
            'total' => count($secteurs),
            'nouveau_systeme' => 0,
            'ancien_systeme' => 0,
            'mixte' => 0,
            'orphelins' => 0
        ];

        foreach ($secteurs as $secteur) {
            $nouveauSysteme = $secteur->utiliseNouveauSysteme();
            $ancienSysteme = $secteur->utiliseAncienSysteme();

            if ($nouveauSysteme && $ancienSysteme) {
                $stats['mixte']++;
            } elseif ($nouveauSysteme) {
                $stats['nouveau_systeme']++;
            } elseif ($ancienSysteme) {
                $stats['ancien_systeme']++;
            } else {
                $stats['orphelins']++;
            }
        }

        $io->table(
            ['État du secteur', 'Nombre'],
            [
                ['Total secteurs', $stats['total']],
                ['Nouveau système uniquement', $stats['nouveau_systeme']],
                ['Ancien système uniquement', $stats['ancien_systeme']],
                ['Système mixte', $stats['mixte']],
                ['Secteurs orphelins', $stats['orphelins']]
            ]
        );

        if ($stats['ancien_systeme'] === 0 && $stats['mixte'] === 0) {
            $io->success('Tous les secteurs utilisent déjà le nouveau système !');
            return Command::SUCCESS;
        }

        // 2. Vérifications pré-migration
        $io->section('Vérifications pré-migration');

        $nbDivisions = $this->entityManager->getRepository(\App\Entity\DivisionAdministrative::class)->count(['actif' => true]);
        $nbTypes = $this->entityManager->getRepository(\App\Entity\TypeSecteur::class)->count(['actif' => true]);

        if ($nbDivisions === 0) {
            $io->error('Aucune division administrative trouvée. Exécutez d\'abord : php bin/console app:import-divisions-administratives');
            return Command::FAILURE;
        }

        if ($nbTypes === 0) {
            $io->error('Aucun type de secteur trouvé. Exécutez d\'abord : php bin/console app:import-divisions-administratives --types-secteur');
            return Command::FAILURE;
        }

        $io->info(sprintf('✓ %d divisions administratives disponibles', $nbDivisions));
        $io->info(sprintf('✓ %d types de secteur disponibles', $nbTypes));

        // 3. Migration proprement dite
        if (!$isDryRun) {
            if (!$force && !$io->confirm('Procéder à la migration ?', false)) {
                $io->note('Migration annulée');
                return Command::SUCCESS;
            }
        }

        $io->section('Migration en cours');
        $io->progressStart($stats['ancien_systeme'] + $stats['mixte']);

        $resultats = [
            'migrations_reussies' => 0,
            'migrations_echecs' => 0,
            'erreurs' => []
        ];

        // Récupérer les secteurs à migrer
        $secteursAMigrer = $this->entityManager->getRepository(Secteur::class)->findAvecAncienSysteme();

        foreach ($secteursAMigrer as $secteur) {
            $io->progressAdvance();
            
            try {
                if (!$isDryRun) {
                    $migrationInfo = $secteur->migrerVersNouveauSysteme();
                    
                    if (!empty($migrationInfo)) {
                        // Essayer de créer des attributions depuis les informations de migration
                        $this->creerAttributionsDepuisMigration($secteur, $migrationInfo);
                        $resultats['migrations_reussies']++;
                    } else {
                        $resultats['erreurs'][] = "Secteur {$secteur->getNomSecteur()} : Aucune donnée à migrer";
                    }
                } else {
                    // Mode dry-run : simuler
                    $migrationInfo = $secteur->migrerVersNouveauSysteme();
                    if (!empty($migrationInfo)) {
                        $resultats['migrations_reussies']++;
                    }
                }
            } catch (\Exception $e) {
                $resultats['migrations_echecs']++;
                $resultats['erreurs'][] = "Secteur {$secteur->getNomSecteur()} : " . $e->getMessage();
            }
        }

        $io->progressFinish();

        // 4. Résultats
        $io->section('Résultats de la migration');
        
        $io->table(
            ['Métrique', 'Nombre'],
            [
                ['Secteurs migrés avec succès', $resultats['migrations_reussies']],
                ['Secteurs en échec', $resultats['migrations_echecs']],
                ['Total erreurs', count($resultats['erreurs'])]
            ]
        );

        if (!empty($resultats['erreurs'])) {
            $io->warning('Erreurs rencontrées :');
            foreach ($resultats['erreurs'] as $erreur) {
                $io->writeln('• ' . $erreur);
            }
        }

        if (!$isDryRun && $resultats['migrations_reussies'] > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Migration terminée ! %d secteurs migrés vers le nouveau système', $resultats['migrations_reussies']));
        } elseif ($isDryRun) {
            $io->info(sprintf('Simulation terminée. %d secteurs peuvent être migrés', $resultats['migrations_reussies']));
        } else {
            $io->warning('Aucune migration effectuée');
        }

        return Command::SUCCESS;
    }

    private function creerAttributionsDepuisMigration(Secteur $secteur, array $migrationInfo): void
    {
        foreach ($migrationInfo as $info) {
            // Chercher la division administrative correspondante
            $division = $this->entityManager
                ->getRepository(\App\Entity\DivisionAdministrative::class)
                ->createQueryBuilder('d')
                ->where('d.codePostal = :cp')
                ->andWhere('LOWER(d.nomCommune) = LOWER(:ville)')
                ->setParameter('cp', $info['code_postal'])
                ->setParameter('ville', $info['ville'])
                ->getQuery()
                ->getOneOrNullResult();

            if ($division) {
                // Vérifier qu'une attribution n'existe pas déjà
                $existante = $this->entityManager
                    ->getRepository(AttributionSecteur::class)
                    ->estDejaCouvertePar($division, $info['type']);

                if (!$existante) {
                    $attribution = AttributionSecteur::creerDepuisDivision($secteur, $division, $info['type']);
                    $attribution->setNotes('Migré automatiquement depuis l\'ancien système');
                    
                    $this->entityManager->persist($attribution);
                }
            }
        }
    }
}