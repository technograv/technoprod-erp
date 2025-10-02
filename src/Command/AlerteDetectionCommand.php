<?php

namespace App\Command;

use App\Service\AlerteManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:alerte:detect',
    description: 'Lance la détection des alertes système',
)]
class AlerteDetectionCommand extends Command
{
    public function __construct(
        private AlerteManager $alerteManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Détection des alertes système');

        $io->section('Détecteurs disponibles:');
        $detectors = $this->alerteManager->getDetectors();
        foreach ($detectors as $className => $detector) {
            $io->text(sprintf('- %s (%s)', $detector->getName(), $className));
        }

        $io->section('Lancement des détections...');

        try {
            $results = $this->alerteManager->runDetection();

            $io->success('Détections terminées');

            $totalInstances = 0;
            foreach ($results as $typeId => $count) {
                if (is_numeric($count)) {
                    $io->text(sprintf('Type %d: %d instance(s) créée(s)', $typeId, $count));
                    $totalInstances += $count;
                } else {
                    $io->error(sprintf('Type %d: %s', $typeId, $count));
                }
            }

            $io->note(sprintf('Total: %d instance(s) d\'alerte créée(s)', $totalInstances));

            // Afficher les statistiques
            $io->section('Statistiques:');
            $stats = $this->alerteManager->getStatistics();
            $io->text([
                sprintf('Types actifs: %d', $stats['types_actifs']),
                sprintf('Instances non résolues: %d', $stats['alertes_non_resolues']),
                sprintf('Instances résolues aujourd\'hui: %d', $stats['alertes_resolues_aujourd_hui'])
            ]);

            if (!empty($stats['par_type'])) {
                $io->table(
                    ['Type', 'Instances actives'],
                    array_map(fn($stat) => [$stat['nom'], $stat['nb_instances']], $stats['par_type'])
                );
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors des détections: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}