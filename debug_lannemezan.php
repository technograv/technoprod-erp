<?php
require 'vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Récupérer le secteur et simuler l'algorithme
$em = $container->get('doctrine.orm.entity_manager');
// $cacheService = $container->get('App\Service\CommuneGeometryCacheService');

echo "=== DEBUG SECTEUR PLATEAU DE LANNEMEZAN ===\n\n";

// 1. Récupérer le secteur
$secteur = $em->getRepository('App\Entity\Secteur')->find(64);
if (!$secteur) {
    echo "❌ Secteur ID 64 non trouvé\n";
    exit;
}

echo "✅ Secteur: " . $secteur->getNomSecteur() . "\n\n";

// 2. Analyser les attributions
echo "📍 ATTRIBUTIONS DU SECTEUR:\n";
foreach ($secteur->getAttributions() as $attribution) {
    $div = $attribution->getDivisionAdministrative();
    echo "- Type: " . $attribution->getTypeCritere() . 
         ", Valeur: " . $attribution->getValeurCritere() . 
         ", Nom: " . ($div ? ($div->getNomCommune() ?: $div->getNomEpci()) : 'N/A') . 
         ", Coords: " . ($div ? $div->getLatitude() . ", " . $div->getLongitude() : 'N/A') . "\n";
}

// 3. Récupérer les communes avec géométries (comme fait dans l'AdminController)
$communesSecteur = [];
$ordreTraitement = ['commune', 'code_postal', 'epci', 'departement', 'region'];

foreach ($ordreTraitement as $typeActuel) {
    foreach ($secteur->getAttributions() as $attribution) {
        if ($attribution->getTypeCritere() === $typeActuel) {
            $division = $attribution->getDivisionAdministrative();
            if ($division) {
                // Logique similaire à celle de AdminController
                if ($typeActuel === 'epci' && $division->getCodeEpci()) {
                    // Récupérer toutes les communes de cet EPCI
                    $communesEpci = $em->createQuery('
                        SELECT d.codeInseeCommune, d.nomCommune, d.latitude, d.longitude, d.codeEpci, d.nomEpci
                        FROM App\Entity\DivisionAdministrative d 
                        WHERE d.codeEpci = :code 
                        AND d.codeInseeCommune IS NOT NULL
                        ORDER BY d.nomCommune
                    ')
                    ->setParameter('code', $division->getCodeEpci())
                    ->getResult();
                    
                    echo "\n🏛️ COMMUNES EPCI " . $division->getCodeEpci() . " (" . $division->getNomEpci() . "):\n";
                    foreach ($communesEpci as $c) {
                        echo "  - " . $c['nomCommune'] . " (" . $c['codeInseeCommune'] . ") - " . $c['latitude'] . ", " . $c['longitude'] . "\n";
                        $communesSecteur[] = [
                            'code_insee' => $c['codeInseeCommune'],
                            'nom' => $c['nomCommune'],
                            'type_attribution' => 'epci'
                        ];
                    }
                }
            }
        }
    }
}

// 4. Calculer le centre simplement à partir des communes de l'EPCI
echo "\n🎯 CALCUL DIRECT DU CENTRE:\n";
$totalLat = 0;
$totalLng = 0;
$count = 0;

foreach ($communesSecteur as $commune) {
    echo "  - " . $commune['nom'] . " (" . $commune['code_insee'] . ")\n";
    
    // Récupérer les coords depuis la BDD
    $coords = $em->createQuery('
        SELECT d.latitude, d.longitude 
        FROM App\Entity\DivisionAdministrative d 
        WHERE d.codeInseeCommune = :code
    ')
    ->setParameter('code', $commune['code_insee'])
    ->getOneOrNullResult();
    
    if ($coords && $coords['latitude'] && $coords['longitude']) {
        $totalLat += $coords['latitude'];
        $totalLng += $coords['longitude'];
        $count++;
        echo "    Coords: " . $coords['latitude'] . ", " . $coords['longitude'] . "\n";
    }
}

if ($count > 0) {
    $centreLat = $totalLat / $count;
    $centreLng = $totalLng / $count;
    echo "\n🎯 CENTRE CALCULÉ: $centreLat, $centreLng\n";
    
    // Distance de Saint Laurent de Neste
    $distanceSaintLaurent = sqrt(
        pow($centreLat - 43.0919, 2) + pow($centreLng - 0.4799, 2)
    ) * 111;
    
    echo "📏 Distance de Saint-Laurent-de-Neste: " . round($distanceSaintLaurent, 2) . " km\n";
    
    // Chercher quelle commune est la plus proche du centre calculé
    echo "\n🔍 COMMUNE LA PLUS PROCHE DU CENTRE:\n";
    $distanceMin = PHP_FLOAT_MAX;
    $communeLaPlusProche = null;
    
    foreach ($communesSecteur as $commune) {
        $coords = $em->createQuery('
            SELECT d.latitude, d.longitude 
            FROM App\Entity\DivisionAdministrative d 
            WHERE d.codeInseeCommune = :code
        ')
        ->setParameter('code', $commune['code_insee'])
        ->getOneOrNullResult();
        
        if ($coords && $coords['latitude'] && $coords['longitude']) {
            $distance = sqrt(
                pow($coords['latitude'] - $centreLat, 2) + 
                pow($coords['longitude'] - $centreLng, 2)
            ) * 111;
            
            if ($distance < $distanceMin) {
                $distanceMin = $distance;
                $communeLaPlusProche = $commune['nom'];
            }
        }
    }
    
    echo "➡️ " . $communeLaPlusProche . " (distance: " . round($distanceMin, 2) . " km)\n";
}

echo "\n=== FIN DEBUG ===\n";