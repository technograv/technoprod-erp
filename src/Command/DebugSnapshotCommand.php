<?php

namespace App\Command;

use App\Entity\DevisVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-snapshot',
    description: 'Debug snapshot data for a specific version',
)]
class DebugSnapshotCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('versionId', InputArgument::REQUIRED, 'Version ID to debug')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $versionId = $input->getArgument('versionId');

        $version = $this->entityManager->getRepository(DevisVersion::class)->find($versionId);
        
        if (!$version) {
            $io->error('Version not found: ' . $versionId);
            return Command::FAILURE;
        }

        $io->title('Debug Snapshot for Version ' . $versionId);
        
        $snapshotData = $version->getSnapshotData();
        
        $io->section('Snapshot Keys');
        $io->listing(array_keys($snapshotData));
        
        if (isset($snapshotData['devis_data'])) {
            $io->section('Devis Data Keys');
            $io->listing(array_keys($snapshotData['devis_data']));
        }
        
        if (isset($snapshotData['elements']) && !empty($snapshotData['elements'])) {
            $io->section('Elements');
            $io->text('Count: ' . count($snapshotData['elements']));
            $io->text('First element keys: ' . implode(', ', array_keys($snapshotData['elements'][0])));
            
            foreach ($snapshotData['elements'] as $index => $element) {
                if ($element['type'] === 'product') {
                    $io->text(sprintf(
                        'Element %d: %s - Prix: %s - Remise: %s - TVA: %s - Produit ID: %s',
                        $index,
                        $element['designation'] ?? 'N/A',
                        $element['prix_unitaire_ht'] ?? $element['prixUnitaireHt'] ?? 'N/A',
                        $element['remise_percent'] ?? $element['remisePercent'] ?? 'N/A',
                        $element['tva_percent'] ?? $element['tvaPercent'] ?? 'N/A',
                        $element['produit_id'] ?? $element['produitId'] ?? 'N/A'
                    ));
                }
            }
        }
        
        if (isset($snapshotData['items']) && !empty($snapshotData['items'])) {
            $io->section('Items (old system)');
            $io->text('Count: ' . count($snapshotData['items']));
            
            foreach ($snapshotData['items'] as $index => $item) {
                $io->text(sprintf(
                    'Item %d: %s - Prix: %s - Remise: %s - TVA: %s - Produit ID: %s',
                    $index,
                    $item['designation'] ?? 'N/A',
                    $item['prix_unitaire_ht'] ?? 'N/A',
                    $item['remise_percent'] ?? 'N/A',
                    $item['tva_percent'] ?? 'N/A',
                    $item['produit_id'] ?? 'N/A'
                ));
            }
        }

        return Command::SUCCESS;
    }
}