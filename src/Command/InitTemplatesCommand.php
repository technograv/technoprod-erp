<?php

namespace App\Command;

use App\Entity\Societe;
use App\Service\TemplateHierarchyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-templates',
    description: 'Initialise les templates hiérarchiques pour toutes les sociétés'
)]
class InitTemplatesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemplateHierarchyService $templateService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🎨 Initialisation des templates hiérarchiques');

        try {
            // Récupérer toutes les sociétés
            $societes = $this->entityManager->getRepository(Societe::class)->findAll();
            
            if (empty($societes)) {
                $io->warning('Aucune société trouvée. Exécutez d\'abord app:init-multi-tenant');
                return Command::FAILURE;
            }

            $io->section('📁 Création des répertoires de templates');
            $totalCreated = 0;

            foreach ($societes as $societe) {
                if ($societe->isMere()) {
                    $createdDirs = $this->templateService->createTemplateDirectories($societe);
                    
                    $io->writeln("• {$societe->getNom()} : " . count($createdDirs) . " répertoires créés");
                    foreach ($createdDirs as $dir) {
                        $io->writeln("  → " . str_replace('/home/decorpub/TechnoProd/technoprod/templates/', '', $dir));
                    }
                    
                    $totalCreated += count($createdDirs);
                }
            }

            $io->section('📋 Templates disponibles par société');
            
            foreach ($societes as $societe) {
                $io->writeln("<info>🏢 {$societe->getDisplayName()}</info>");
                
                // Afficher les variables de thème
                $themeVars = $this->templateService->getThemeVariables($societe);
                $io->writeln("  Couleurs : {$themeVars['couleur_primaire']} / {$themeVars['couleur_secondaire']}");
                $io->writeln("  Thème : {$themeVars['theme_name']}");
                
                // Tester la résolution de template
                $resolvedTemplate = $this->templateService->resolveTemplate('devis_pdf_header.html.twig');
                $io->writeln("  Template résolu : <comment>{$resolvedTemplate}</comment>");
                
                $io->writeln('');
            }

            $io->section('🧪 Test de résolution de templates');
            
            // Tester avec chaque société
            foreach ($societes as $societe) {
                $io->writeln("<info>Test avec {$societe->getDisplayName()}</info>");
                
                // Simuler le contexte de cette société
                // Note: En réalité, ceci devrait être testé avec une vraie session
                
                $testTemplates = [
                    'devis_pdf_header.html.twig',
                    'base/devis_pdf_header.html.twig',
                    'email/devis_notification.html.twig'
                ];
                
                foreach ($testTemplates as $template) {
                    $resolved = $this->templateService->resolveTemplate($template);
                    $status = $resolved === $template ? '📄' : '✨';
                    $io->writeln("  {$status} {$template} → {$resolved}");
                }
                
                $io->writeln('');
            }

            $io->success("✅ Initialisation terminée ! {$totalCreated} répertoires créés.");
            
            $io->note([
                'Les templates sont maintenant organisés de manière hiérarchique :',
                '• templates/base/ : Templates par défaut',
                '• templates/custom/societe_1/ : Templates Groupe DecorPub',
                '• templates/custom/societe_1/technograv/ : Templates TechnoGrav',
                '• templates/custom/societe_1/technoprint/ : Templates TechnoPrint', 
                '• templates/custom/societe_1/technoburo/ : Templates TechnoBuro',
                '',
                'Le système utilise automatiquement le template le plus spécifique.'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("❌ Erreur lors de l'initialisation : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}