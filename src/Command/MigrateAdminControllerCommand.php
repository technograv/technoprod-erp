<?php

namespace App\Command;

use App\Service\Admin\AdminControllerMigrationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-admin-controller',
    description: 'Migre les méthodes de l\'AdminController vers les nouveaux contrôleurs spécialisés'
)]
class MigrateAdminControllerCommand extends Command
{
    public function __construct(
        private AdminControllerMigrationService $migrationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('method', InputArgument::OPTIONAL, 'Méthode spécifique à migrer')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécution à blanc sans modifications')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Migrer toutes les méthodes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Migration AdminController -> Contrôleurs spécialisés');
        
        if ($input->getOption('dry-run')) {
            $io->note('Mode DRY-RUN activé - Aucune modification ne sera effectuée');
        }

        $pendingMigrations = $this->migrationService->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            $io->success('Toutes les méthodes ont déjà été migrées !');
            return Command::SUCCESS;
        }

        $io->section('Méthodes en attente de migration :');
        $io->listing($pendingMigrations);

        $method = $input->getArgument('method');
        
        if ($method) {
            if (!in_array($method, $pendingMigrations)) {
                $io->error("La méthode '{$method}' n'existe pas ou a déjà été migrée");
                return Command::FAILURE;
            }
            
            $io->info("Migration de la méthode : {$method}");
            
            if (!$input->getOption('dry-run')) {
                // TODO: Implémenter la migration de la méthode spécifique
                $io->warning("Migration automatique non encore implémentée. Migration manuelle requise.");
            }
        } elseif ($input->getOption('all')) {
            $io->info('Migration de toutes les méthodes...');
            
            if (!$input->getOption('dry-run')) {
                $io->warning("Migration automatique non encore implémentée. Migration manuelle requise.");
            }
        } else {
            $io->note('Utilisez --all pour migrer toutes les méthodes ou spécifiez une méthode.');
        }

        $io->success('Commande terminée avec succès');
        return Command::SUCCESS;
    }
}