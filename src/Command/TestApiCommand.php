<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\AdminController;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:test-api',
    description: 'Test API response directly'
)]
class TestApiCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminController $adminController,
        private \App\Service\CommuneGeometryCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔍 Testing API response...');
        
        try {
            // Appeler directement la méthode du contrôleur
            $response = $this->adminController->getAllSecteursGeoData($this->entityManager, $this->cacheService);
            $data = json_decode($response->getContent(), true);
            
            $output->writeln('📊 API Response:');
            $output->writeln('  Success: ' . ($data['success'] ? 'YES' : 'NO'));
            $output->writeln('  Total: ' . $data['total']);
            $output->writeln('  Secteurs: ' . count($data['secteurs']));
            
            foreach ($data['secteurs'] as $secteur) {
                $output->writeln(sprintf('    - %s: %d attributions', 
                    $secteur['nom'], 
                    count($secteur['attributions'])
                ));
            }
            
        } catch (\Exception $e) {
            $output->writeln('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}