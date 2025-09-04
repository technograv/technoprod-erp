<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Service\CommuneGeometryCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-position-puce',
    description: 'Test du calcul de position d\'une puce de secteur'
)]
class TestPositionPuceCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuneGeometryCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('nom_secteur', InputArgument::REQUIRED, 'Nom du secteur à analyser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nomSecteur = $input->getArgument('nom_secteur');

        $io->title("🎯 Test position puce pour : $nomSecteur");

        // 1. Récupérer le secteur
        $secteur = $this->entityManager->getRepository(Secteur::class)
            ->createQueryBuilder('s')
            ->where('s.nomSecteur LIKE :nom')
            ->setParameter('nom', '%' . $nomSecteur . '%')
            ->getQuery()
            ->getOneOrNullResult();

        if (!$secteur) {
            $io->error("Secteur '$nomSecteur' non trouvé");
            return Command::FAILURE;
        }

        // 2. Reproduire exactement l'algorithme du AdminController
        $io->section("🔄 Simulation complète algorithme AdminController");
        
        // Récupération assignation hiérarchique
        $communeVsSecteur = [];
        $ordreTraitement = ['commune', 'code_postal', 'epci', 'departement', 'region'];
        $attributionsParType = [];
        
        foreach ($secteur->getAttributions() as $attribution) {
            $type = $attribution->getTypeCritere();
            if (!isset($attributionsParType[$type])) {
                $attributionsParType[$type] = [];
            }
            $attributionsParType[$type][] = $attribution;
        }
        
        foreach ($ordreTraitement as $typeActuel) {
            if (!isset($attributionsParType[$typeActuel])) continue;
            
            foreach ($attributionsParType[$typeActuel] as $attribution) {
                $division = $attribution->getDivisionAdministrative();
                if (!$division) continue;
                
                $communesDuType = $this->getCommunesPourType($typeActuel, $division);
                
                foreach ($communesDuType as $commune) {
                    $codeInsee = $commune['codeInseeCommune'];
                    if (!isset($communeVsSecteur[$codeInsee])) {
                        $communeVsSecteur[$codeInsee] = $secteur->getId();
                    }
                }
            }
        }
        
        $io->writeln("Communes assignées : " . count($communeVsSecteur));
        
        // 3. Préparer les données comme dans AdminController
        $communesSecteur = [];
        foreach ($communeVsSecteur as $codeInsee => $secteurId) {
            if ($secteurId === $secteur->getId()) {
                $communesSecteur[] = [
                    'codeInseeCommune' => $codeInsee,
                    'nomCommune' => 'Commune ' . $codeInsee
                ];
            }
        }
        
        // 4. Récupérer géométries via cache (comme AdminController)
        $io->writeln("Récupération géométries...");
        $communesAvecGeometries = $this->cacheService->getMultipleCommunesGeometry($communesSecteur);
        $io->writeln("Géométries trouvées : " . count($communesAvecGeometries));
        
        if (empty($communesAvecGeometries)) {
            $io->error("Aucune géométrie trouvée - impossible de calculer la position");
            return Command::FAILURE;
        }
        
        // 5. Test du positionnement hiérarchique
        $io->section("🎯 Test calcul position hiérarchique");
        
        $position = $this->calculerPositionHierarchique($secteur, $communesAvecGeometries);
        
        if ($position) {
            $io->success("Position calculée via " . $position['type'] . ": " . $position['entite']);
            $io->table(
                ['Coordonnée', 'Valeur'],
                [
                    ['Latitude', $position['center']['lat']],
                    ['Longitude', $position['center']['lng']],
                ]
            );
            
            // Identifier la commune la plus proche de cette position
            $communeLaPlusProche = $this->trouverCommuneLaPlusProche($position['center'], $communesAvecGeometries);
            if ($communeLaPlusProche) {
                $io->note("Commune la plus proche de la position calculée : " . $communeLaPlusProche['nom'] . " (" . $communeLaPlusProche['code_insee'] . ")");
            }
            
        } else {
            $io->error("Impossible de calculer la position");
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
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codePostal', $division->getCodePostal())
                ->getResult();
                
            case 'epci':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :codeEpci 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeEpci', $division->getCodeEpci())
                ->getResult();
                
            case 'departement':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :codeDepartement 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeDepartement', $division->getCodeDepartement())
                ->getResult();
                
            case 'region':
                return $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeRegion = :codeRegion 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeRegion', $division->getCodeRegion())
                ->getResult();
                
            default:
                return [];
        }
    }

    private function calculerPositionHierarchique($secteur, array $communesAvecGeometries): ?array
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
                $centre = $this->calculerCentreEntitesPrincipales($epcis, $communesAvecGeometries, 'epci');
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
                $centre = $this->calculerCentreEntitesPrincipales($departements, $communesAvecGeometries, 'departement');
                if ($centre) {
                    return [
                        'center' => $centre,
                        'type' => 'Département',
                        'entite' => count($departements) . ' département(s)'
                    ];
                }
            }
            
            // 3. FALLBACK: Centre géographique des communes
            if (!empty($communesAvecGeometries)) {
                return $this->trouverCommuneCentrale($communesAvecGeometries);
            }
            
        } catch (\Exception $e) {
            echo "Erreur calcul position hiérarchique: " . $e->getMessage() . "\n";
        }
        
        return null;
    }

    private function calculerCentreEntitesPrincipales(array $entites, array $communesAvecGeometries, string $type): ?array
    {
        if (empty($entites) || empty($communesAvecGeometries)) {
            return null;
        }
        
        // Si une seule entité, prendre son centre géographique
        if (count($entites) === 1) {
            return $this->calculerCentreGeographiqueGlobal($communesAvecGeometries);
        }
        
        // Si plusieurs entités, prendre celle la plus centrale
        $centreGlobal = $this->calculerCentreGeographiqueGlobal($communesAvecGeometries);
        $entiteOptimale = null;
        $distanceMin = PHP_FLOAT_MAX;
        
        foreach ($entites as $entite) {
            $coordsEntite = $this->getCoordonneesPourEntite($entite, $communesAvecGeometries, $type);
            
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

    private function getCoordonneesPourEntite($entite, array $communesAvecGeometries, string $type): ?array
    {
        // REPRODUIRE LA MÉTHODE EXACTE DU ADMINCONTROLLER
        $codeReference = null;
        if ($type === 'epci' && method_exists($entite, 'getCodeEpci')) {
            $codeReference = $entite->getCodeEpci();
        } elseif ($type === 'departement' && method_exists($entite, 'getCodeDepartement')) {
            $codeReference = $entite->getCodeDepartement();
        }
        
        if (!$codeReference) {
            echo "⚠️ Aucun code de référence trouvé pour le type '$type'\n";
            return null;
        }
        
        echo "🔍 Recherche coordonnées pour $type $codeReference\n";
        
        // Récupérer toutes les communes appartenant à cette entité depuis la base
        $communes = [];
        try {
            if ($type === 'epci') {
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune, d.latitude, d.longitude
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :code 
                    AND d.codeInseeCommune IS NOT NULL
                    AND d.latitude IS NOT NULL 
                    AND d.longitude IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('code', $codeReference)
                ->getResult();
            } elseif ($type === 'departement') {
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune, d.latitude, d.longitude
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :code 
                    AND d.codeInseeCommune IS NOT NULL
                    AND d.latitude IS NOT NULL 
                    AND d.longitude IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('code', $codeReference)
                ->getResult();
            }
        } catch (\Exception $e) {
            echo "❌ Erreur lors de la récupération des communes pour $type $codeReference: " . $e->getMessage() . "\n";
            return null;
        }
        
        echo "📋 $type $codeReference: " . count($communes) . " communes trouvées avec coordonnées\n";
        
        if (empty($communes)) {
            return null;
        }
        
        // Filtrer les communes avec géométries qui appartiennent à cette entité
        $communesPertinentes = [];
        foreach ($communesAvecGeometries as $commune) {
            if (!isset($commune['code_insee'])) {
                continue;
            }
            
            foreach ($communes as $communeEntite) {
                if ($communeEntite['codeInseeCommune'] === $commune['code_insee']) {
                    $communesPertinentes[] = $commune;
                    break;
                }
            }
        }
        
        echo "🎯 $type $codeReference: " . count($communesPertinentes) . " communes pertinentes avec géométries\n";
        
        if (!empty($communesPertinentes)) {
            return $this->calculerCentreGeographiqueGlobal($communesPertinentes);
        }
        
        // Fallback: utiliser les coordonnées simples de l'entité
        if (!empty($communes)) {
            $latSum = $lngSum = 0;
            $count = 0;
            
            foreach ($communes as $commune) {
                if ($commune['latitude'] && $commune['longitude']) {
                    $latSum += $commune['latitude'];
                    $lngSum += $commune['longitude'];
                    $count++;
                }
            }
            
            if ($count > 0) {
                echo "🎯 Fallback: Utilisation centre géographique simple pour $type $codeReference\n";
                return [
                    'lat' => $latSum / $count,
                    'lng' => $lngSum / $count
                ];
            }
        }
        
        return null;
    }

    private function calculerCentreGeographiqueGlobal(array $communesAvecGeometries): array
    {
        $totalLat = $totalLng = 0;
        $nbCommunes = 0;
        
        foreach ($communesAvecGeometries as $commune) {
            if (!isset($commune['coordinates']) || empty($commune['coordinates'])) {
                continue;
            }
            
            $bbox = $this->calculerBoundingBox($commune['coordinates']);
            if ($bbox) {
                $totalLat += ($bbox['minLat'] + $bbox['maxLat']) / 2;
                $totalLng += ($bbox['minLng'] + $bbox['maxLng']) / 2;
                $nbCommunes++;
            }
        }
        
        if ($nbCommunes === 0) {
            return ['lat' => 46.603354, 'lng' => 1.888334]; // Centre France
        }
        
        return [
            'lat' => $totalLat / $nbCommunes,
            'lng' => $totalLng / $nbCommunes
        ];
    }

    private function calculerBoundingBox(array $coordinates): ?array
    {
        if (empty($coordinates)) {
            return null;
        }
        
        $minLat = $minLng = PHP_FLOAT_MAX;
        $maxLat = $maxLng = PHP_FLOAT_MIN;
        
        foreach ($coordinates as $coord) {
            if (!isset($coord['lat']) || !isset($coord['lng'])) {
                continue;
            }
            
            $minLat = min($minLat, $coord['lat']);
            $maxLat = max($maxLat, $coord['lat']);
            $minLng = min($minLng, $coord['lng']);
            $maxLng = max($maxLng, $coord['lng']);
        }
        
        if ($minLat === PHP_FLOAT_MAX) {
            return null;
        }
        
        return [
            'minLat' => $minLat,
            'maxLat' => $maxLat,
            'minLng' => $minLng,
            'maxLng' => $maxLng
        ];
    }

    private function calculerDistance(array $point1, array $point2): float
    {
        $deltaLat = $point1['lat'] - $point2['lat'];
        $deltaLng = $point1['lng'] - $point2['lng'];
        return sqrt($deltaLat * $deltaLat + $deltaLng * $deltaLng);
    }

    private function trouverCommuneCentrale(array $communesAvecGeometries): ?array
    {
        if (empty($communesAvecGeometries)) {
            return null;
        }

        $centreGlobal = $this->calculerCentreGeographiqueGlobal($communesAvecGeometries);
        $communeOptimale = null;
        $distanceMinimale = PHP_FLOAT_MAX;
        
        foreach ($communesAvecGeometries as $commune) {
            if (!isset($commune['coordinates']) || empty($commune['coordinates'])) {
                continue;
            }
            
            $bbox = $this->calculerBoundingBox($commune['coordinates']);
            if (!$bbox) {
                continue;
            }
            
            $centreCommune = [
                'lat' => ($bbox['minLat'] + $bbox['maxLat']) / 2,
                'lng' => ($bbox['minLng'] + $bbox['maxLng']) / 2
            ];
            
            $distance = $this->calculerDistance($centreCommune, $centreGlobal);
            
            if ($distance < $distanceMinimale) {
                $distanceMinimale = $distance;
                $communeOptimale = $commune;
            }
        }
        
        if ($communeOptimale) {
            $bbox = $this->calculerBoundingBox($communeOptimale['coordinates']);
            if ($bbox) {
                $centre = [
                    'lat' => ($bbox['minLat'] + $bbox['maxLat']) / 2,
                    'lng' => ($bbox['minLng'] + $bbox['maxLng']) / 2
                ];
                
                return [
                    'center' => $centre,
                    'communes' => count($communesAvecGeometries),
                    'type' => 'commune_centrale'
                ];
            }
        }
        
        return null;
    }

    private function trouverCommuneLaPlusProche(array $position, array $communesAvecGeometries): ?array
    {
        $communeLaPlusProche = null;
        $distanceMinimale = PHP_FLOAT_MAX;
        
        foreach ($communesAvecGeometries as $commune) {
            if (!isset($commune['coordinates']) || empty($commune['coordinates'])) {
                continue;
            }
            
            $bbox = $this->calculerBoundingBox($commune['coordinates']);
            if (!$bbox) {
                continue;
            }
            
            $centreCommune = [
                'lat' => ($bbox['minLat'] + $bbox['maxLat']) / 2,
                'lng' => ($bbox['minLng'] + $bbox['maxLng']) / 2
            ];
            
            $distance = $this->calculerDistance($centreCommune, $position);
            
            if ($distance < $distanceMinimale) {
                $distanceMinimale = $distance;
                $communeLaPlusProche = [
                    'nom' => $commune['nom'],
                    'code_insee' => $commune['code_insee'],
                    'distance' => $distance
                ];
            }
        }
        
        return $communeLaPlusProche;
    }
}