<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Entity\Secteur;
use App\Entity\AttributionSecteur;
use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\DBAL\DriverManager;

// Configuration de la base de données (adapter selon vos paramètres)
$config = Setup::createAttributeMetadataConfiguration([__DIR__ . '/src'], true);
$connectionParams = [
    'driver'   => 'pdo_pgsql',
    'host'     => 'localhost',
    'dbname'   => 'technoprod',
    'user'     => 'postgres',
    'password' => 'postgres',
];

try {
    $connection = DriverManager::getConnection($connectionParams);
    $entityManager = new EntityManager($connection, $config);
    
    echo "=== DIAGNOSTIC SECTEUR PLATEAU DE LANNEMEZAN ===\n\n";
    
    // 1. Rechercher les secteurs contenant "Lannemezan" ou "Plateau"
    echo "1. RECHERCHE DES SECTEURS:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $secteurs = $entityManager->createQueryBuilder()
        ->select('s')
        ->from(Secteur::class, 's')
        ->where('s.nomSecteur LIKE :lannemezan OR s.nomSecteur LIKE :plateau')
        ->setParameter('lannemezan', '%Lannemezan%')
        ->setParameter('plateau', '%Plateau%')
        ->getQuery()
        ->getResult();
    
    if (empty($secteurs)) {
        echo "❌ Aucun secteur trouvé avec 'Lannemezan' ou 'Plateau' dans le nom.\n\n";
        
        // Recherche plus large
        echo "Recherche plus large dans tous les secteurs actifs:\n";
        $allSecteurs = $entityManager->getRepository(Secteur::class)
            ->findBy(['isActive' => true], ['nomSecteur' => 'ASC']);
        
        foreach ($allSecteurs as $secteur) {
            echo "- " . $secteur->getNomSecteur() . " (ID: " . $secteur->getId() . ")\n";
        }
        echo "\nTotal secteurs actifs: " . count($allSecteurs) . "\n\n";
    } else {
        foreach ($secteurs as $secteur) {
            echo "✅ Secteur trouvé: " . $secteur->getNomSecteur() . "\n";
            echo "   - ID: " . $secteur->getId() . "\n";
            echo "   - Commercial: " . ($secteur->getCommercial() ? 
                $secteur->getCommercial()->getPrenom() . ' ' . $secteur->getCommercial()->getNom() : 
                'Aucun') . "\n";
            echo "   - Couleur: " . ($secteur->getCouleurHex() ?: 'Aucune') . "\n";
            echo "   - Actif: " . ($secteur->isActive() ? 'Oui' : 'Non') . "\n";
            echo "   - Nombre d'attributions: " . $secteur->getAttributions()->count() . "\n";
            echo "\n";
            
            // 2. Analyser les attributions du secteur
            echo "2. ATTRIBUTIONS DU SECTEUR '" . $secteur->getNomSecteur() . "':\n";
            echo "=" . str_repeat("=", 60) . "\n";
            
            if ($secteur->getAttributions()->isEmpty()) {
                echo "❌ Aucune attribution trouvée pour ce secteur.\n\n";
            } else {
                foreach ($secteur->getAttributions() as $attribution) {
                    echo "📍 Attribution #" . $attribution->getId() . ":\n";
                    echo "   - Type: " . $attribution->getTypeCritere() . "\n";
                    echo "   - Valeur: " . $attribution->getValeurCritere() . "\n";
                    echo "   - Division Administrative:\n";
                    
                    $division = $attribution->getDivisionAdministrative();
                    if ($division) {
                        echo "     * Code INSEE: " . ($division->getCodeInseeCommune() ?: 'N/A') . "\n";
                        echo "     * Nom commune: " . ($division->getNomCommune() ?: 'N/A') . "\n";
                        echo "     * Code postal: " . ($division->getCodePostal() ?: 'N/A') . "\n";
                        echo "     * Département: " . ($division->getNomDepartement() ?: 'N/A') . " (" . ($division->getCodeDepartement() ?: 'N/A') . ")\n";
                        echo "     * Région: " . ($division->getNomRegion() ?: 'N/A') . "\n";
                        echo "     * EPCI: " . ($division->getNomEpci() ?: 'N/A') . " (" . ($division->getCodeEpci() ?: 'N/A') . ")\n";
                        echo "     * Latitude: " . ($division->getLatitude() ?: 'N/A') . "\n";
                        echo "     * Longitude: " . ($division->getLongitude() ?: 'N/A') . "\n";
                    } else {
                        echo "     * ❌ Aucune division administrative associée\n";
                    }
                    echo "\n";
                }
            }
            
            // 3. Simulation du calcul de position hiérarchique
            echo "3. SIMULATION CALCUL POSITION HIERARCHIQUE:\n";
            echo "=" . str_repeat("=", 60) . "\n";
            
            // Analyser les types d'attributions par ordre hiérarchique
            $attributionsParType = [];
            foreach ($secteur->getAttributions() as $attribution) {
                $type = $attribution->getTypeCritere();
                if (!isset($attributionsParType[$type])) {
                    $attributionsParType[$type] = [];
                }
                $attributionsParType[$type][] = $attribution;
            }
            
            $ordreHierarchique = ['commune', 'code_postal', 'epci', 'departement', 'region'];
            
            echo "Analyse hiérarchique des attributions:\n";
            foreach ($ordreHierarchique as $type) {
                if (isset($attributionsParType[$type])) {
                    echo "✅ Type '$type': " . count($attributionsParType[$type]) . " attribution(s)\n";
                    foreach ($attributionsParType[$type] as $attribution) {
                        $division = $attribution->getDivisionAdministrative();
                        if ($division && $division->getLatitude() && $division->getLongitude()) {
                            echo "   - " . $attribution->getValeurCritere() . 
                                 " → Coordonnées: " . $division->getLatitude() . ", " . $division->getLongitude() . "\n";
                        } else {
                            echo "   - " . $attribution->getValeurCritere() . " → ❌ Pas de coordonnées\n";
                        }
                    }
                } else {
                    echo "❌ Type '$type': aucune attribution\n";
                }
            }
            
            // Types non hiérarchiques
            foreach ($attributionsParType as $type => $attributions) {
                if (!in_array($type, $ordreHierarchique)) {
                    echo "🔍 Type '$type' (non-hiérarchique): " . count($attributions) . " attribution(s)\n";
                }
            }
            
            echo "\n";
        }
    }
    
    // 4. Rechercher dans les divisions administratives toute référence à "Lannemezan"
    echo "4. RECHERCHE DANS LES DIVISIONS ADMINISTRATIVES:\n";
    echo "=" . str_repeat("=", 60) . "\n";
    
    $divisions = $entityManager->createQueryBuilder()
        ->select('d')
        ->from(DivisionAdministrative::class, 'd')
        ->where('d.nomCommune LIKE :lannemezan OR d.nomEpci LIKE :lannemezan OR d.nomCanton LIKE :lannemezan')
        ->setParameter('lannemezan', '%Lannemezan%')
        ->getQuery()
        ->getResult();
    
    if (empty($divisions)) {
        echo "❌ Aucune division administrative trouvée avec 'Lannemezan'.\n\n";
        
        // Recherche de "Plateau"
        $divisionsPlateaux = $entityManager->createQueryBuilder()
            ->select('d')
            ->from(DivisionAdministrative::class, 'd')
            ->where('d.nomCommune LIKE :plateau OR d.nomEpci LIKE :plateau OR d.nomCanton LIKE :plateau')
            ->setParameter('plateau', '%Plateau%')
            ->getQuery()
            ->getResult();
        
        if (!empty($divisionsPlateaux)) {
            echo "🔍 Divisions avec 'Plateau' trouvées:\n";
            foreach ($divisionsPlateaux as $division) {
                echo "- " . $division->getNomCommune() . " (" . $division->getCodePostal() . ")\n";
            }
            echo "\n";
        }
    } else {
        foreach ($divisions as $division) {
            echo "✅ Division trouvée:\n";
            echo "   - Commune: " . $division->getNomCommune() . "\n";
            echo "   - Code postal: " . $division->getCodePostal() . "\n";
            echo "   - Code INSEE: " . $division->getCodeInseeCommune() . "\n";
            echo "   - Département: " . $division->getNomDepartement() . "\n";
            echo "   - EPCI: " . $division->getNomEpci() . "\n";
            echo "   - Latitude: " . ($division->getLatitude() ?: 'N/A') . "\n";
            echo "   - Longitude: " . ($division->getLongitude() ?: 'N/A') . "\n";
            
            // Vérifier les attributions de secteur pour cette division
            echo "   - Attributions secteurs: " . $division->getAttributionsSecteur()->count() . "\n";
            foreach ($division->getAttributionsSecteur() as $attribution) {
                echo "     → Secteur: " . $attribution->getSecteur()->getNomSecteur() . 
                     " (Type: " . $attribution->getTypeCritere() . ")\n";
            }
            echo "\n";
        }
    }
    
    // 5. Diagnostic final
    echo "5. DIAGNOSTIC ET RECOMMANDATIONS:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    if (empty($secteurs)) {
        echo "❌ PROBLÈME IDENTIFIÉ: Le secteur 'Plateau de Lannemezan' n'existe pas dans la base.\n";
        echo "💡 SOLUTIONS POSSIBLES:\n";
        echo "   1. Vérifier l'orthographe exacte du nom du secteur\n";
        echo "   2. Le secteur pourrait être inactif (is_active = false)\n";
        echo "   3. Le secteur pourrait avoir un nom différent\n";
    } else {
        foreach ($secteurs as $secteur) {
            if ($secteur->getAttributions()->isEmpty()) {
                echo "❌ PROBLÈME: Le secteur '" . $secteur->getNomSecteur() . "' n'a aucune attribution.\n";
                echo "💡 SOLUTION: Ajouter des attributions géographiques au secteur.\n";
            } else {
                $hasCoordinates = false;
                foreach ($secteur->getAttributions() as $attribution) {
                    $division = $attribution->getDivisionAdministrative();
                    if ($division && $division->getLatitude() && $division->getLongitude()) {
                        $hasCoordinates = true;
                        break;
                    }
                }
                
                if (!$hasCoordinates) {
                    echo "❌ PROBLÈME: Aucune attribution du secteur n'a de coordonnées GPS.\n";
                    echo "💡 SOLUTION: Vérifier les coordonnées des divisions administratives.\n";
                } else {
                    echo "✅ Le secteur semble correctement configuré.\n";
                    echo "🔍 VÉRIFICATION RECOMMANDÉE: Analyser l'algorithme de positionnement dans AdminController.\n";
                }
            }
        }
    }
    
    echo "\n=== FIN DU DIAGNOSTIC ===\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}