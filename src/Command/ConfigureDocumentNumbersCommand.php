<?php

namespace App\Command;

use App\Entity\Societe;
use App\Service\DocumentNumberService;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:configure-document-numbers',
    description: 'Configure les préfixes et teste la génération des numéros de documents'
)]
class ConfigureDocumentNumbersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentNumberService $documentNumberService,
        private TenantService $tenantService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('reset-counters', 'r', InputOption::VALUE_NONE, 'Remet les compteurs à zéro')
            ->addOption('test-only', 't', InputOption::VALUE_NONE, 'Mode test uniquement (pas de modification)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔢 Configuration des numéros de documents');

        $resetCounters = $input->getOption('reset-counters');
        $testOnly = $input->getOption('test-only');

        try {
            // Récupérer toutes les sociétés
            $societes = $this->entityManager->getRepository(Societe::class)->findAll();
            
            if (!$testOnly) {
                $io->section('⚙️ Configuration des préfixes');
                $this->configurePrefixes($io, $societes, $resetCounters);
            }

            $io->section('🧪 Test de génération de numéros');
            $this->testNumberGeneration($io, $societes);

            $io->section('📊 Statistiques de numérotation');
            $this->displayStatistics($io, $societes);

            if (!$testOnly) {
                $io->success('✅ Configuration terminée avec succès !');
            } else {
                $io->info('ℹ️ Mode test terminé (aucune modification effectuée)');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur : " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function configurePrefixes(SymfonyStyle $io, array $societes, bool $resetCounters): void
    {
        foreach ($societes as $societe) {
            $io->writeln("<info>🏢 Configuration {$societe->getDisplayName()}</info>");

            if ($societe->isFille()) {
                // Pour les sociétés filles, configurer des préfixes spécifiques
                $prefixes = $this->getFilialePrefixes($societe);
            } else {
                // Pour la société mère, préfixes par défaut
                $prefixes = [
                    'devis' => 'DEVIS-DP-',
                    'facture' => 'FACT-DP-',
                    'commande' => 'CMD-DP-',
                    'avoir' => 'AVOIR-DP-'
                ];
            }

            $this->documentNumberService->configureNumberingForSociete($societe, $prefixes);

            foreach ($prefixes as $type => $prefix) {
                $io->writeln("  • {$type}: {$prefix}");
            }

            if ($resetCounters) {
                $this->documentNumberService->resetCountersForSociete($societe);
                $io->writeln("  → Compteurs remis à zéro");
            }

            $io->writeln('');
        }
    }

    private function getFilialePrefixes(Societe $societe): array
    {
        $shortName = match($societe->getNom()) {
            'TechnoGrav' => 'TG',
            'TechnoPrint' => 'TP', 
            'TechnoBuro' => 'TB',
            default => strtoupper(substr($societe->getNom(), 0, 2))
        };

        return [
            'devis' => "DEVIS-{$shortName}-",
            'facture' => "FACT-{$shortName}-",
            'commande' => "CMD-{$shortName}-",
            'avoir' => "AVOIR-{$shortName}-"
        ];
    }

    private function testNumberGeneration(SymfonyStyle $io, array $societes): void
    {
        foreach ($societes as $societe) {
            $io->writeln("<info>🧪 Test avec {$societe->getDisplayName()}</info>");

            // Simuler le contexte de cette société
            $this->tenantService->setCurrentSociete($societe);

            $documentTypes = ['devis', 'facture', 'commande', 'avoir'];

            foreach ($documentTypes as $type) {
                try {
                    $preview = $this->documentNumberService->previewDocumentNumber($type, $societe);
                    $io->writeln("  • {$type}: <comment>{$preview}</comment>");
                } catch (\Exception $e) {
                    $io->writeln("  • {$type}: <error>Erreur - {$e->getMessage()}</error>");
                }
            }

            $io->writeln('');
        }
    }

    private function displayStatistics(SymfonyStyle $io, array $societes): void
    {
        $rows = [];
        
        foreach ($societes as $societe) {
            $stats = $this->documentNumberService->getNumberingStatistics($societe);
            
            foreach ($stats as $type => $stat) {
                $rows[] = [
                    $societe->getDisplayName(),
                    ucfirst($type),
                    $stat['prefix'],
                    $stat['current_counter'],
                    $stat['example']
                ];
            }
        }

        $io->table(
            ['Société', 'Type', 'Préfixe', 'Compteur', 'Exemple prochain'],
            $rows
        );

        // Afficher les règles de numérotation
        $io->note([
            'Règles de numérotation :',
            '• Les sociétés filles partagent les compteurs avec leur société mère',
            '• Seuls les préfixes changent selon l\'enseigne',
            '• Les factures incluent l\'année : FACT-TG-2025-0001',
            '• Les devis sont simples : DEVIS-TG-0001',
            '• La longueur de padding par défaut est de 4 chiffres'
        ]);
    }
}