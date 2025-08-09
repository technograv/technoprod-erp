<?php

namespace App\Command;

use App\Entity\Societe;
use App\Service\InheritanceService;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-inheritance',
    description: 'Teste le système d\'héritage des paramètres entre société mère et filles'
)]
class TestInheritanceCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InheritanceService $inheritanceService,
        private TenantService $tenantService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔗 Test du Système d\'Héritage Multi-Tenant');

        try {
            // Récupérer la société mère et ses filles
            $societeMere = $this->entityManager->getRepository(Societe::class)->findOneBy(['type' => 'mere']);
            if (!$societeMere) {
                $io->error('Aucune société mère trouvée.');
                return Command::FAILURE;
            }

            $societesFilles = $this->entityManager->getRepository(Societe::class)->findBy(
                ['type' => 'fille'], 
                ['nom' => 'ASC']
            );

            if (empty($societesFilles)) {
                $io->warning('Aucune société fille trouvée pour les tests.');
            }

            $io->section('🏢 Test Société Mère : ' . $societeMere->getNom());
            $this->testSocieteInheritance($io, $societeMere);

            $io->section('👥 Test Sociétés Filles');
            foreach ($societesFilles as $fille) {
                $io->writeln("<info>🏪 Société : {$fille->getNom()}</info>");
                $this->testSocieteInheritance($io, $fille);
                $io->writeln('');
            }

            $io->section('🎨 Test Switch de Contexte');
            $this->testContextSwitch($io, [$societeMere, ...$societesFilles]);

            $io->success('✅ Tests d\'héritage terminés avec succès !');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Erreur lors du test : " . $e->getMessage());
            $io->writeln("Trace : " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function testSocieteInheritance(SymfonyStyle $io, Societe $societe): void
    {
        // Test des paramètres visuels
        $visualParams = $this->inheritanceService->getVisualParameters($societe);
        
        $io->writeln("  🎨 Thème : <comment>{$visualParams['theme']}</comment>");
        $io->writeln("  🔵 Couleur primaire : <comment>{$visualParams['colors']['primary']}</comment>");
        $io->writeln("  🟢 Couleur secondaire : <comment>{$visualParams['colors']['secondary']}</comment>");
        $io->writeln("  📄 Logo : <comment>" . ($visualParams['logo'] ?: 'Aucun') . "</comment>");
        
        // Test des préfixes de documents
        if (!empty($visualParams['document_prefixes'])) {
            $io->writeln("  📋 Préfixes documents :");
            foreach ($visualParams['document_prefixes'] as $type => $prefix) {
                $io->writeln("    • {$type}: <comment>{$prefix}</comment>");
            }
        }

        // Test des informations détaillées sur l'héritage
        if ($societe->isFille()) {
            $io->writeln("  🔗 Analyse héritage :");
            $parametersInfo = $this->inheritanceService->getAllParametersInfo($societe);
            
            foreach (['template_theme', 'devis_prefix', 'facture_prefix'] as $param) {
                if (isset($parametersInfo[$param])) {
                    $info = $parametersInfo[$param];
                    $source = match($info['source']) {
                        'local' => '✅ Local',
                        'inherited' => '⬆️ Hérité',
                        'default' => '🔧 Défaut'
                    };
                    $io->writeln("    • {$param}: <comment>{$info['effective_value']}</comment> ({$source})");
                }
            }
        }
    }

    private function testContextSwitch(SymfonyStyle $io, array $societes): void
    {
        foreach ($societes as $societe) {
            $io->writeln("<info>🔄 Switch vers : {$societe->getNom()}</info>");
            
            // Changer le contexte
            $this->tenantService->setCurrentSociete($societe);
            $current = $this->tenantService->getCurrentSociete();
            
            $success = $current && $current->getId() === $societe->getId();
            $status = $success ? '✅' : '❌';
            
            if ($success) {
                // Tester les paramètres dans ce contexte
                $colors = $this->inheritanceService->getColors();
                $theme = $this->inheritanceService->getTheme();
                
                $io->writeln("  {$status} Contexte actif - Thème: <comment>{$theme}</comment>, Couleur: <comment>{$colors['primary']}</comment>");
            } else {
                $io->writeln("  {$status} Échec du changement de contexte");
            }
        }
    }
}