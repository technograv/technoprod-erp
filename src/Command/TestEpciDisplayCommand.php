<?php

namespace App\Command;

use App\Repository\SecteurRepository;
use App\Repository\AttributionSecteurRepository;
use App\Entity\Secteur;
use App\Entity\AttributionSecteur;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:test-epci-display',
    description: 'Test des données EPCI pour affichage cartographique'
)]
class TestEpciDisplayCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test des données EPCI pour l\'affichage cartographique');

        try {
            // Récupérer les secteurs directement
            $secteurs = $this->entityManager->getRepository(Secteur::class)
                ->createQueryBuilder('s')
                ->leftJoin('s.attributions', 'attr')
                ->addSelect('attr')
                ->leftJoin('attr.divisionAdministrative', 'da')
                ->addSelect('da')
                ->where('s.isActive = true')
                ->getQuery()
                ->getResult();

            $io->section('Analyse des secteurs et leurs attributions');
            $io->text(sprintf('Total secteurs actifs: %d', count($secteurs)));
            
            $epciCount = 0;
            $epciWithCommunes = 0;
            
            foreach ($secteurs as $secteur) {
                $io->text(sprintf('Secteur: %s', $secteur->getNomSecteur()));
                
                foreach ($secteur->getAttributions() as $attribution) {
                    $io->text(sprintf('  - Attribution: %s', $attribution->getTypeCritere()));
                    
                    if ($attribution->getTypeCritere() === 'epci') {
                        $epciCount++;
                        
                        $divisionAdmin = $attribution->getDivisionAdministrative();
                        if ($divisionAdmin) {
                            $io->text(sprintf('  EPCI trouvé: %s (Code: %s)', 
                                $divisionAdmin->getNomEpci(),
                                $divisionAdmin->getCodeEpci()
                            ));
                            
                            // Tester la méthode fetchCommuneGeometryDirect
                            $io->text('  - Test de récupération des communes via API directe...');
                            
                            // Test avec un code INSEE de commune de cet EPCI
                            $testResult = $this->testDirectApiCall($divisionAdmin->getCodeInseeCommune(), $divisionAdmin->getNomCommune());
                            if ($testResult) {
                                $epciWithCommunes++;
                                $io->text('    ✅ API directe fonctionnelle');
                            } else {
                                $io->text('    ❌ API directe non fonctionnelle');
                            }
                        }
                        
                        $io->newLine();
                    }
                }
            }
            
            $io->success(sprintf('Résumé: %d EPCI trouvés, %d avec API fonctionnelle', $epciCount, $epciWithCommunes));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Erreur lors de l\'analyse: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function testDirectApiCall(string $codeInsee, string $nomCommune): bool
    {
        try {
            $url = "https://geo.api.gouv.fr/communes/{$codeInsee}?geometry=contour&format=geojson";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            if ($response === false) {
                return false;
            }
            
            $data = json_decode($response, true);
            return isset($data['geometry']) && isset($data['geometry']['coordinates']);
            
        } catch (\Exception $e) {
            return false;
        }
    }
}