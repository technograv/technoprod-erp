<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Service\CommuneGeometryCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:position-lannemezan',
    description: 'Test de la position calculée pour le secteur Plateau de Lannemezan après correction'
)]
class TestPositionLannemezanCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuneGeometryCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('TEST POSITION SECTEUR PLATEAU DE LANNEMEZAN');
        
        // 1. Récupérer le secteur
        $secteur = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Secteur::class, 's')
            ->where('s.nomSecteur LIKE :nom')
            ->setParameter('nom', '%Plateau de Lannemezan%')
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$secteur) {
            $io->error('Secteur Plateau de Lannemezan non trouvé');
            return Command::FAILURE;
        }
        
        $io->success('Secteur trouvé: ' . $secteur->getNomSecteur() . ' (ID: ' . $secteur->getId() . ')');
        
        // 2. Simuler l'algorithme d'assignation des communes
        $io->section('Simulation de l\'assignation des communes au secteur');
        
        $communeVsSecteur = [];
        $ordreHierarchique = ['commune', 'code_postal', 'epci', 'departement', 'region'];
        
        foreach ($ordreHierarchique as $typeActuel) {
            foreach ($secteur->getAttributions() as $attribution) {
                if ($attribution->getTypeCritere() !== $typeActuel) {
                    continue;
                }
                
                $division = $attribution->getDivisionAdministrative();
                if (!$division) continue;
                
                $communesDuType = $this->getCommunesPourType($typeActuel, $division);
                
                $nouvellesCommunes = 0;
                foreach ($communesDuType as $commune) {
                    $codeInsee = $commune['codeInseeCommune'];
                    if (!isset($communeVsSecteur[$codeInsee])) {
                        $communeVsSecteur[$codeInsee] = $secteur->getId();
                        $nouvellesCommunes++;
                    }
                }
                
                if ($nouvellesCommunes > 0) {
                    $io->info("$typeActuel '{$attribution->getValeurCritere()}': {$nouvellesCommunes} nouvelles communes");
                }
            }
        }
        
        $io->note('Total communes assignées: ' . count($communeVsSecteur));
        
        // 3. Récupérer quelques géométries de test
        $communesSecteur = [];
        $count = 0;
        foreach ($communeVsSecteur as $codeInsee => $secteurId) {
            if ($secteurId === $secteur->getId() && $count < 10) {
                $communesSecteur[] = [
                    'codeInseeCommune' => $codeInsee,
                    'nomCommune' => 'Commune ' . $codeInsee
                ];
                $count++;
            }
        }
        
        if (!empty($communesSecteur)) {
            $communesAvecGeometries = $this->cacheService->getMultipleCommunesGeometry($communesSecteur);
            
            if (!empty($communesAvecGeometries)) {
                $io->info('Communes avec géométries obtenues: ' . count($communesAvecGeometries));
                
                // Afficher quelques communes pour vérification
                $io->section('Échantillon des communes assignées');
                foreach (array_slice($communesAvecGeometries, 0, 5) as $commune) {
                    $io->text('- ' . ($commune['nomCommune'] ?? 'N/A') . ' (' . ($commune['codeInseeCommune'] ?? 'N/A') . ')');
                }
                
                // 4. Tester le calcul de position hiérarchique
                $io->section('Test du calcul de position hiérarchique');
                
                $positionOptimale = $this->calculerPositionHierarchiqueTest($secteur, $communesAvecGeometries);
                
                if ($positionOptimale) {
                    $io->success('Position calculée avec succès !');
                    $io->definitionList([
                        'Type' => $positionOptimale['type'],
                        'Entité' => $positionOptimale['entite'],
                        'Latitude' => $positionOptimale['center']['lat'],
                        'Longitude' => $positionOptimale['center']['lng']
                    ]);
                    
                    // Vérification si la position est cohérente avec la région attendue
                    $lat = $positionOptimale['center']['lat'];
                    $lng = $positionOptimale['center']['lng'];
                    
                    // Coordonnées approximatives de la région Lannemezan
                    if ($lat >= 42.5 && $lat <= 44.0 && $lng >= -0.5 && $lng <= 1.0) {
                        $io->success('✅ Position géographique COHÉRENTE avec la région du Plateau de Lannemezan');
                    } else {
                        $io->error('❌ Position géographique INCOHÉRENTE - pourrait indiquer un problème');
                        $io->warning("Coordonnées attendues: Lat 42.5-44.0, Lng -0.5-1.0");
                        $io->warning("Coordonnées calculées: Lat $lat, Lng $lng");
                    }
                } else {
                    $io->error('Échec du calcul de position hiérarchique');
                }
            } else {
                $io->warning('Aucune géométrie trouvée pour les communes du secteur');
            }
        } else {
            $io->warning('Aucune commune assignée au secteur');
        }
        
        return Command::SUCCESS;
    }
    
    private function getCommunesPourType(string $type, $division): array
    {
        switch ($type) {
            case 'commune':
                return [[
                    'codeInseeCommune' => $division->getCodeInseeCommune(),
                    'nomCommune' => $division->getNomCommune()
                ]];
                
            case 'code_postal':
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codePostal', $division->getCodePostal())
                ->getResult();
                return $communes;
                
            case 'epci':
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :codeEpci 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeEpci', $division->getCodeEpci())
                ->getResult();
                return $communes;
                
            case 'departement':
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :codeDepartement 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeDepartement', $division->getCodeDepartement())
                ->getResult();
                return $communes;
                
            default:
                return [];
        }
    }
    
    private function calculerPositionHierarchiqueTest(Secteur $secteur, array $communesAvecGeometries): ?array
    {
        try {
            $attributions = $secteur->getAttributions();
            
            // 1. PRIORITÉ ÉPCI
            $epcis = [];
            foreach ($attributions as $attribution) {
                if ($attribution->getTypeCritere() === 'epci') {
                    $division = $attribution->getDivisionAdministrative();
                    if ($division && $division->getNomEpci()) {
                        $epcis[] = $division;
                    }
                }
            }
            
            if (!empty($epcis)) {
                $centre = $this->calculerCentreEntitesPrincipalesTest($epcis, $communesAvecGeometries, 'epci');
                if ($centre) {
                    return [
                        'center' => $centre,
                        'type' => 'EPCI',
                        'entite' => count($epcis) . ' EPCI(s)'
                    ];
                }
            }
            
            // 2. PRIORITÉ DÉPARTEMENT
            $departements = [];
            foreach ($attributions as $attribution) {
                if ($attribution->getTypeCritere() === 'departement') {
                    $division = $attribution->getDivisionAdministrative();
                    if ($division && $division->getCodeDepartement()) {
                        $departements[] = $division;
                    }
                }
            }
            
            if (!empty($departements)) {
                $centre = $this->calculerCentreEntitesPrincipalesTest($departements, $communesAvecGeometries, 'departement');
                if ($centre) {
                    return [
                        'center' => $centre,
                        'type' => 'DÉPARTEMENT',
                        'entite' => count($departements) . ' département(s)'
                    ];
                }
            }
            
            // 3. FALLBACK: Centre géographique global
            $centre = $this->calculerCentreGeographiqueGlobalTest($communesAvecGeometries);
            if ($centre) {
                return [
                    'center' => $centre,
                    'type' => 'CENTRE_GLOBAL',
                    'entite' => count($communesAvecGeometries) . ' commune(s)'
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("❌ Erreur dans calculerPositionHierarchiqueTest: " . $e->getMessage());
            return null;
        }
    }
    
    private function calculerCentreEntitesPrincipalesTest(array $entites, array $communesAvecGeometries, string $type): ?array
    {
        if (count($entites) === 1) {
            return $this->calculerCentreGeographiqueGlobalTest($communesAvecGeometries);
        }
        
        $centreGlobal = $this->calculerCentreGeographiqueGlobalTest($communesAvecGeometries);
        $entiteOptimale = null;
        $distanceMin = PHP_FLOAT_MAX;
        
        foreach ($entites as $entite) {
            $coordsEntite = $this->getCoordonneesPourEntiteTest($entite, $communesAvecGeometries, $type);
            
            if ($coordsEntite) {
                $distance = $this->calculerDistance($coordsEntite, $centreGlobal);
                if ($distance < $distanceMin) {
                    $distanceMin = $distance;
                    $entiteOptimale = $coordsEntite;
                }
            }
        }
        
        return $entiteOptimale;
    }
    
    private function getCoordonneesPourEntiteTest($entite, array $communesAvecGeometries, string $type): ?array
    {
        $communesPertinentes = [];
        
        $codeReference = null;
        if ($type === 'epci' && method_exists($entite, 'getCodeEpci')) {
            $codeReference = $entite->getCodeEpci();
        } elseif ($type === 'departement' && method_exists($entite, 'getCodeDepartement')) {
            $codeReference = $entite->getCodeDepartement();
        }
        
        if (!$codeReference) {
            return null;
        }
        
        // Récupérer toutes les communes appartenant à cette entité
        $communes = [];
        if ($type === 'epci') {
            $communes = $this->entityManager->createQuery('
                SELECT d.codeInseeCommune
                FROM App\Entity\DivisionAdministrative d 
                WHERE d.codeEpci = :code 
                AND d.codeInseeCommune IS NOT NULL
            ')
            ->setParameter('code', $codeReference)
            ->getResult();
        } elseif ($type === 'departement') {
            $communes = $this->entityManager->createQuery('
                SELECT d.codeInseeCommune
                FROM App\Entity\DivisionAdministrative d 
                WHERE d.codeDepartement = :code 
                AND d.codeInseeCommune IS NOT NULL
            ')
            ->setParameter('code', $codeReference)
            ->getResult();
        }
        
        $codesInseeEntite = array_column($communes, 'codeInseeCommune');
        
        // Filtrer les communes avec géométries
        foreach ($communesAvecGeometries as $commune) {
            if (isset($commune['codeInseeCommune']) && 
                in_array($commune['codeInseeCommune'], $codesInseeEntite)) {
                $communesPertinentes[] = $commune;
            }
        }
        
        if (!empty($communesPertinentes)) {
            return $this->calculerCentreGeographiqueGlobalTest($communesPertinentes);
        }
        
        return null;
    }
    
    private function calculerCentreGeographiqueGlobalTest(array $communesAvecGeometries): ?array
    {
        if (empty($communesAvecGeometries)) {
            return null;
        }
        
        $latSum = $lngSum = 0;
        $coordCount = 0;
        
        foreach ($communesAvecGeometries as $commune) {
            if (isset($commune['coordinates']) && is_array($commune['coordinates'])) {
                foreach ($commune['coordinates'] as $coord) {
                    if (isset($coord['lat']) && isset($coord['lng'])) {
                        $latSum += $coord['lat'];
                        $lngSum += $coord['lng'];
                        $coordCount++;
                    }
                }
            }
        }
        
        if ($coordCount > 0) {
            return [
                'lat' => $latSum / $coordCount,
                'lng' => $lngSum / $coordCount
            ];
        }
        
        return null;
    }
    
    private function calculerDistance(array $point1, array $point2): float
    {
        $lat1 = deg2rad($point1['lat']);
        $lng1 = deg2rad($point1['lng']);
        $lat2 = deg2rad($point2['lat']);
        $lng2 = deg2rad($point2['lng']);
        
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        
        $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return 6371 * $c; // Rayon de la Terre en km
    }
}