<?php

namespace App\Command;

use App\Entity\User;
use App\Service\TenantService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-tenant',
    description: 'Test du système multi-tenant'
)]
class TestTenantCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantService $tenantService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧪 Test du système multi-tenant');

        try {
            // Récupérer l'utilisateur super admin
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => 'nicolas.michel@decorpub.fr']);

            if (!$user) {
                $io->error('Utilisateur nicolas.michel@decorpub.fr non trouvé');
                return Command::FAILURE;
            }

            $io->section('👤 Utilisateur testé');
            $io->writeln("Email: {$user->getEmail()}");
            $io->writeln("Super Admin: " . ($user->isSuperAdmin() ? '✅' : '❌'));

            // Test des rôles de sociétés
            $io->section('🏢 Rôles dans les sociétés');
            $societeRoles = $user->getSocieteRoles();
            
            if ($societeRoles->count() === 0) {
                $io->warning('Aucun rôle de société trouvé');
            } else {
                foreach ($societeRoles as $role) {
                    $io->writeln("• {$role->getSociete()->getDisplayName()} - {$role->getRoleLibelle()} " . 
                                ($role->isActive() ? '✅' : '❌'));
                }
            }

            // Test d'accès aux sociétés
            $io->section('🔑 Test d\'accès aux sociétés');
            $societes = $this->entityManager->getRepository(\App\Entity\Societe::class)->findAll();
            
            foreach ($societes as $societe) {
                $hasAccess = $user->hasAccessToSociete($societe);
                $role = $user->getRoleInSociete($societe);
                
                $io->writeln("• {$societe->getDisplayName()}: " . 
                           ($hasAccess ? '✅ Accès' : '❌ Pas d\'accès') .
                           ($role ? " - {$role->getRoleLibelle()}" : ''));
            }

            $io->success('Tests terminés avec succès !');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Erreur lors des tests : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}