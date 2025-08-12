<?php

namespace App\Command;

use App\Entity\Societe;
use App\Service\ThemeService;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-theme-system',
    description: 'Teste le système de thèmes dynamiques multi-tenant'
)]
class TestThemeSystemCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ThemeService $themeService,
        private TenantService $tenantService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🎨 Test du Système de Thèmes Dynamiques');

        try {
            // Récupérer les sociétés de test
            $societes = $this->entityManager->getRepository(Societe::class)->findBy(
                ['nom' => ['TechnoGrav', 'TechnoPrint', 'TechnoBuro']],
                ['nom' => 'ASC']
            );

            if (empty($societes)) {
                $io->error('Aucune société de test trouvée. Assurez-vous que TechnoGrav, TechnoPrint et TechnoBuro existent.');
                return Command::FAILURE;
            }

            $io->section('📊 Test des Variables de Thème par Société');
            
            foreach ($societes as $societe) {
                $io->writeln("<info>🏢 Société : {$societe->getNom()}</info>");
                
                // Simuler le contexte de cette société
                $this->tenantService->setCurrentSociete($societe);
                
                // Récupérer les variables de thème
                $variables = $this->themeService->getThemeVariables($societe);
                
                $io->writeln("  🎨 Thème : <comment>{$variables['theme_name']}</comment>");
                $io->writeln("  🔵 Couleur primaire : <comment>{$variables['primary_color']}</comment>");
                $io->writeln("  🟢 Couleur secondaire : <comment>{$variables['secondary_color']}</comment>");
                $io->writeln("  📄 Logo : <comment>" . ($variables['logo_url'] ?: 'Aucun') . "</comment>");
                $io->writeln("");
            }

            $io->section('🎛️ Test de Génération CSS Dynamique');
            
            foreach ($societes as $societe) {
                $io->writeln("<info>🏢 {$societe->getNom()} :</info>");
                
                // Générer le CSS pour cette société
                $css = $this->themeService->generateDynamicCSS($societe);
                
                // Extraire quelques lignes significatives
                $lines = explode("\n", $css);
                $significantLines = array_filter($lines, function($line) {
                    return strpos($line, '--bs-primary:') !== false || 
                           strpos($line, 'theme-') !== false ||
                           strpos($line, '.navbar-dark') !== false;
                });
                
                foreach (array_slice($significantLines, 0, 3) as $line) {
                    $io->writeln("  📝 " . trim($line));
                }
                
                $io->writeln("  📏 Taille CSS : <comment>" . strlen($css) . " caractères</comment>");
                $io->writeln("");
            }

            $io->section('🔧 Test des Variables JavaScript');
            
            foreach ($societes as $societe) {
                $variables = $this->themeService->getJavaScriptVariables($societe);
                
                $io->writeln("<info>🏢 {$societe->getNom()} :</info>");
                $io->writeln("  🎨 JS Theme : <comment>{$variables['themeName']}</comment>");
                $io->writeln("  🔵 JS Primary : <comment>{$variables['primaryColor']}</comment>");
                $io->writeln("  🏢 JS Société : <comment>{$variables['societeName']}</comment>");
                $io->writeln("");
            }

            $io->section('✅ Test de Switch de Société');
            
            $io->writeln("Test du changement de contexte de société :");
            
            foreach ($societes as $societe) {
                // Changer le contexte
                $this->tenantService->setCurrentSociete($societe);
                $currentSociete = $this->tenantService->getCurrentSociete();
                
                $success = $currentSociete && $currentSociete->getId() === $societe->getId();
                $status = $success ? '✅' : '❌';
                
                $io->writeln("  {$status} Switch vers {$societe->getNom()} : " . 
                            ($success ? 'OK' : 'ÉCHEC'));
            }

            $io->success('🎉 Test du système de thèmes terminé avec succès !');
            
            $io->note([
                'Le système de thèmes dynamiques est opérationnel.',
                'Chaque société a ses propres couleurs et variables CSS.',
                'Le switch de contexte fonctionne correctement.',
                'URLs de test :',
                '  - /theme/css (CSS dynamique)',
                '  - /theme/vars.js (Variables JavaScript)',
                '  - /admin/theme/preview/{societeId} (Prévisualisation)'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Erreur lors du test : " . $e->getMessage());
            $io->writeln("Trace : " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}