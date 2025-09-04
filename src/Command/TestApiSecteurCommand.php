<?php

namespace App\Command;

use App\Controller\AdminController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-api-secteur',
    description: 'Test de l\'API des secteurs'
)]
class TestApiSecteurCommand extends Command
{
    public function __construct(
        private AdminController $adminController
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test de l'API getAllSecteursGeoData");

        // Appeler directement la méthode du controller
        try {
            $response = $this->adminController->getAllSecteursGeoData();
            $data = json_decode($response->getContent(), true);
            
            if ($data['success'] ?? false) {
                $io->success("API appelée avec succès");
                $io->info("Nombre de secteurs: " . count($data['secteurs'] ?? []));
                
                // Chercher le secteur "Plateau de Lannemezan"
                $secteurPlateau = null;
                foreach ($data['secteurs'] as $secteur) {
                    if (str_contains($secteur['nom'], 'Plateau de Lannemezan')) {
                        $secteurPlateau = $secteur;
                        break;
                    }
                }
                
                if ($secteurPlateau) {
                    $io->section("Secteur: " . $secteurPlateau['nom']);
                    
                    if (isset($secteurPlateau['center'])) {
                        $io->table(
                            ['Coordonnée', 'Valeur'],
                            [
                                ['Latitude', $secteurPlateau['center']['lat']],
                                ['Longitude', $secteurPlateau['center']['lng']],
                            ]
                        );
                        
                        // Identifier la commune la plus proche
                        $this->identifierCommuneLaPlusProche($io, $secteurPlateau['center']);
                        
                    } else {
                        $io->error("Pas de coordonnées center pour ce secteur");
                    }
                    
                    if (isset($secteurPlateau['attributions'])) {
                        $io->info("Nombre d'attributions: " . count($secteurPlateau['attributions']));
                        foreach ($secteurPlateau['attributions'] as $attribution) {
                            if (isset($attribution['communes'])) {
                                $io->note("Attribution " . $attribution['type'] . ": " . count($attribution['communes']) . " communes");
                            }
                        }
                    }
                    
                } else {
                    $io->error("Secteur 'Plateau de Lannemezan' non trouvé dans la réponse");
                }
                
            } else {
                $io->error("Erreur API: " . ($data['error'] ?? 'Erreur inconnue'));
            }
            
        } catch (\Exception $e) {
            $io->error("Exception: " . $e->getMessage());
            $io->writeln("Stack trace:");
            $io->writeln($e->getTraceAsString());
        }

        return Command::SUCCESS;
    }
    
    private function identifierCommuneLaPlusProche(SymfonyStyle $io, array $center): void
    {
        // Comparer avec les coordonnées de Saint-Laurent-de-Neste
        $stLaurentCoords = [
            'lat' => 43.0833,  // Coordonnées approximatives de Saint-Laurent-de-Neste
            'lng' => 0.5167
        ];
        
        $distance = $this->calculerDistance($center, $stLaurentCoords);
        
        $io->info("Distance du center à Saint-Laurent-de-Neste: " . number_format($distance, 6));
        
        if ($distance < 0.01) { // Moins de ~1km
            $io->error("⚠️  La position calculée est TRÈS PROCHE de Saint-Laurent-de-Neste !");
        } else {
            $io->success("✅ La position calculée est éloignée de Saint-Laurent-de-Neste");
        }
    }
    
    private function calculerDistance(array $point1, array $point2): float
    {
        $deltaLat = $point1['lat'] - $point2['lat'];
        $deltaLng = $point1['lng'] - $point2['lng'];
        return sqrt($deltaLat * $deltaLat + $deltaLng * $deltaLng);
    }
}