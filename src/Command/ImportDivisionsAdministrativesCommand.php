<?php

namespace App\Command;

use App\Service\ImportDivisionsAdministrativesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-divisions-administratives',
    description: 'Importe les données des divisions administratives françaises (échantillon)',
)]
class ImportDivisionsAdministrativesCommand extends Command
{
    public function __construct(
        private ImportDivisionsAdministrativesService $importService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('types-secteur', null, InputOption::VALUE_NONE, 'Importer aussi les types de secteur standard')
            ->addOption('nettoyer', null, InputOption::VALUE_NONE, 'Nettoyer les doublons après import')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer l\'import même si des données existent')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Import des divisions administratives françaises');
        $io->note('Import d\'un échantillon représentatif de 17 communes principales françaises');

        // 1. Import des types de secteur
        if ($input->getOption('types-secteur')) {
            $io->section('Import des types de secteur standard');
            $statsTypes = $this->importService->importerTypesSecteurStandard();
            
            $io->table(
                ['Métrique', 'Nombre'],
                [
                    ['Total traités', $statsTypes['total']],
                    ['Créés avec succès', $statsTypes['succes']],
                    ['Doublons ignorés', $statsTypes['doublons']]
                ]
            );

            if ($statsTypes['succes'] > 0) {
                $io->success(sprintf('%d types de secteur créés', $statsTypes['succes']));
            }
        }

        // 2. Import des divisions administratives
        $io->section('Import des divisions administratives');
        $io->progressStart();

        $statsDivisions = $this->importService->importerDonneesEchantillon();

        $io->progressFinish();
        
        $io->table(
            ['Métrique', 'Nombre'],
            [
                ['Total traités', $statsDivisions['total']],
                ['Créés avec succès', $statsDivisions['succes']],
                ['Erreurs', $statsDivisions['erreurs']],
                ['Doublons ignorés', $statsDivisions['doublons']]
            ]
        );

        // 3. Nettoyage des doublons si demandé
        if ($input->getOption('nettoyer')) {
            $io->section('Nettoyage des doublons');
            $statsNettoyage = $this->importService->nettoyerDoublons();
            
            if ($statsNettoyage['supprimes'] > 0) {
                $io->warning(sprintf(
                    '%d doublons supprimés, %d divisions conservées',
                    $statsNettoyage['supprimes'],
                    $statsNettoyage['conserves']
                ));
            } else {
                $io->info('Aucun doublon trouvé');
            }
        }

        // 4. Statistiques finales
        $io->section('Statistiques de couverture');
        $statsFinales = $this->importService->getStatistiquesApresImport();
        
        $io->table(
            ['Division administrative', 'Nombre'],
            [
                ['Total divisions', $statsFinales['total_divisions']],
                ['Codes postaux uniques', $statsFinales['codes_postaux']],
                ['Communes uniques', $statsFinales['communes']],
                ['Cantons uniques', $statsFinales['cantons']],
                ['EPCI uniques', $statsFinales['epci']],
                ['Départements uniques', $statsFinales['departements']],
                ['Régions uniques', $statsFinales['regions']]
            ]
        );

        // Messages de résumé
        if ($statsDivisions['erreurs'] > 0) {
            $io->warning(sprintf('%d erreurs rencontrées lors de l\'import', $statsDivisions['erreurs']));
        }

        if ($statsDivisions['succes'] > 0) {
            $io->success(sprintf(
                'Import terminé avec succès ! %d divisions administratives importées',
                $statsDivisions['succes']
            ));
            
            $io->note([
                'Vous pouvez maintenant :',
                '• Utiliser le nouveau système de secteurs dans l\'administration',
                '• Créer des secteurs basés sur ces divisions administratives',
                '• Migrer les secteurs existants vers le nouveau système'
            ]);
        } else {
            $io->info('Aucune nouvelle division importée (données déjà présentes)');
        }

        return Command::SUCCESS;
    }
}