<?php

namespace App\Command;

use App\Controller\AdminController;
use App\Service\EpciBoundariesService;
use App\Service\CommuneGeometryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:test-geo-api',
    description: 'Test de l\'API géographique complète des secteurs'
)]
class TestGeoApiCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private EpciBoundariesService $epciBoundariesService;
    private CommuneGeometryService $communeGeometryService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EpciBoundariesService $epciBoundariesService,
        CommuneGeometryService $communeGeometryService
    ) {
        $this->entityManager = $entityManager;
        $this->epciBoundariesService = $epciBoundariesService;
        $this->communeGeometryService = $communeGeometryService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test de l\'API géographique complète des secteurs');

        try {
            // Créer une instance temporaire du contrôleur
            $adminController = new AdminController();
            
            // Utiliser la réflexion pour appeler la méthode publique
            $reflection = new \ReflectionClass($adminController);
            $method = $reflection->getMethod('getAllSecteursGeoData');

            // Créer une instance du service de cache
            $cacheService = new \App\Service\CommuneGeometryCacheService($this->entityManager, new \Psr\Log\NullLogger());
            
            // Appeler la méthode avec les services requis
            $jsonResponse = $method->invoke(
                $adminController, 
                $this->entityManager, 
                $this->epciBoundariesService, 
                $this->communeGeometryService,
                $cacheService
            );
            
            // Récupérer le contenu JSON
            $geoData = json_decode($jsonResponse->getContent(), true);
            
            $io->section('Analyse des données géographiques complètes');
            $io->text(sprintf('Total d\'éléments géographiques: %d', count($geoData)));
            
            $epciCount = 0;
            $epciWithCommunes = 0;
            $communeCount = 0;
            
            foreach ($geoData as $item) {
                switch ($item['type']) {
                    case 'epci':
                        $epciCount++;
                        $io->text(sprintf('EPCI: %s', $item['nom']));
                        $io->text(sprintf('  - boundary_type: %s', $item['boundary_type'] ?? 'non défini'));
                        
                        if (isset($item['communes']) && is_array($item['communes'])) {
                            $communeCountInEpci = count($item['communes']);
                            $epciWithCommunes++;
                            $io->text(sprintf('  - Communes: %d', $communeCountInEpci));
                            
                            // Vérifier que les communes ont des coordonnées
                            $communesWithCoords = 0;
                            foreach ($item['communes'] as $commune) {
                                if (isset($commune['coordinates']) && count($commune['coordinates']) > 0) {
                                    $communesWithCoords++;
                                }
                            }
                            $io->text(sprintf('  - Communes avec coordonnées: %d', $communesWithCoords));
                        }
                        break;
                        
                    case 'commune':
                        $communeCount++;
                        break;
                }
            }
            
            $io->success(sprintf(
                'Résumé: %d EPCI (%d avec communes), %d communes individuelles', 
                $epciCount, 
                $epciWithCommunes, 
                $communeCount
            ));
            
            // Test d'un échantillon de la structure de données
            if (!empty($geoData)) {
                $firstEpci = null;
                foreach ($geoData as $item) {
                    if ($item['type'] === 'epci') {
                        $firstEpci = $item;
                        break;
                    }
                }
                
                if ($firstEpci) {
                    $io->section('Échantillon de structure EPCI');
                    $io->text('Structure du premier EPCI trouvé:');
                    $io->text(sprintf('  - nom: %s', $firstEpci['nom']));
                    $io->text(sprintf('  - type: %s', $firstEpci['type']));
                    $io->text(sprintf('  - boundary_type: %s', $firstEpci['boundary_type'] ?? 'non défini'));
                    $io->text(sprintf('  - couleur: %s', $firstEpci['couleur'] ?? 'non définie'));
                    
                    if (isset($firstEpci['communes']) && !empty($firstEpci['communes'])) {
                        $firstCommune = $firstEpci['communes'][0];
                        $io->text('  - Première commune:');
                        $io->text(sprintf('    * nom: %s', $firstCommune['nom'] ?? 'non défini'));
                        $io->text(sprintf('    * code_insee: %s', $firstCommune['code_insee'] ?? 'non défini'));
                        $io->text(sprintf('    * coordonnées: %d points', 
                            isset($firstCommune['coordinates']) ? count($firstCommune['coordinates']) : 0
                        ));
                    }
                }
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Erreur lors du test: ' . $e->getMessage());
            $io->error('Trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}