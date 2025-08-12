<?php
// Script de test de régression pour l'interface admin

echo "=== TEST DE RÉGRESSION INTERFACE ADMIN ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$baseUrl = 'https://127.0.0.1:8080';
$adminRoutes = [
    // Dashboard principal
    'Dashboard Admin' => '/admin/',
    
    // ConfigurationController 
    'Formes Juridiques' => '/admin/formes-juridiques',
    'Modes de Paiement' => '/admin/modes-paiement', 
    'Modes de Règlement' => '/admin/modes-reglement',
    'Banques' => '/admin/banques',
    'Taux TVA' => '/admin/taux-tva',
    'Unités' => '/admin/unites',
    
    // UserManagementController
    'Utilisateurs' => '/admin/users',
    'Groupes Utilisateurs' => '/admin/groupes-utilisateurs',
    
    // SocieteController  
    'Sociétés' => '/admin/societes',
    'Paramètres' => '/admin/settings',
    
    // CatalogController
    'Produits' => '/admin/produits',
    'Tags' => '/admin/tags',
    'Modèles de Document' => '/admin/modeles-document',
    
    // LogisticsController
    'Transporteurs' => '/admin/transporteurs',
    'Frais de Port' => '/admin/frais-port',
    'Méthodes Expédition' => '/admin/methodes-expedition',
    'Civilités' => '/admin/civilites',
    
    // ThemeController
    'Environnement' => '/admin/environment',
    'Templates' => '/admin/templates',
    
    // SecteurController
    'Secteurs Commerciaux' => '/admin/secteurs-admin',
    
    // SystemController
    'Numérotation' => '/admin/numerotation',
];

$results = [
    'SUCCESS' => [],
    'REDIRECT' => [],
    'ERROR' => []
];

foreach ($adminRoutes as $name => $route) {
    $url = $baseUrl . $route;
    
    // Test HTTP avec curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: AdminRegressionTest/1.0'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $results['ERROR'][] = "$name ($route): CURL Error - $error";
    } elseif ($httpCode == 200) {
        $results['SUCCESS'][] = "$name ($route): HTTP $httpCode ✓";
    } elseif ($httpCode == 302) {
        $results['REDIRECT'][] = "$name ($route): HTTP $httpCode (Redirect - Normal pour auth)";
    } else {
        $results['ERROR'][] = "$name ($route): HTTP $httpCode";
    }
}

// Affichage des résultats
echo "📊 RÉSULTATS DES TESTS:\n";
echo str_repeat("-", 50) . "\n";

echo "\n✅ SUCCÈS (" . count($results['SUCCESS']) . " routes):\n";
foreach ($results['SUCCESS'] as $success) {
    echo "  $success\n";
}

echo "\n🔄 REDIRECTIONS (" . count($results['REDIRECT']) . " routes):\n";
foreach ($results['REDIRECT'] as $redirect) {
    echo "  $redirect\n";
}

echo "\n❌ ERREURS (" . count($results['ERROR']) . " routes):\n";
foreach ($results['ERROR'] as $error) {
    echo "  $error\n";
}

$totalRoutes = count($adminRoutes);
$successRate = round((count($results['SUCCESS']) + count($results['REDIRECT'])) / $totalRoutes * 100, 2);

echo "\n📈 STATISTIQUES FINALES:\n";
echo str_repeat("-", 50) . "\n";
echo "Total routes testées: $totalRoutes\n";
echo "Succès + Redirections: " . (count($results['SUCCESS']) + count($results['REDIRECT'])) . "\n";
echo "Erreurs: " . count($results['ERROR']) . "\n";
echo "Taux de réussite: $successRate%\n\n";

if (count($results['ERROR']) == 0) {
    echo "🎉 TOUS LES TESTS SONT PASSÉS - RÉGRESSION RÉUSSIE!\n";
} else {
    echo "⚠️  CERTAINS TESTS ONT ÉCHOUÉ - VÉRIFICATION NÉCESSAIRE\n";
}

echo "=== FIN DES TESTS ===\n";