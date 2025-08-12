<?php
// Script de debug pour tester une route admin spécifique

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== DEBUG ROUTE ADMIN ===\n";

// Créer le kernel en mode dev
$_ENV['APP_ENV'] = 'dev';
$_ENV['APP_DEBUG'] = '1';
$kernel = new Kernel('dev', true);
$kernel->boot();

// Créer une requête pour tester
$request = Request::create('/admin/modes-paiement', 'GET');

try {
    // Traiter la requête
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Length: " . strlen($response->getContent()) . " bytes\n";
    
    if ($response->getStatusCode() >= 400) {
        echo "Response Content:\n";
        echo $response->getContent();
        echo "\n";
    } else {
        echo "✅ Route fonctionne correctement\n";
        
        // Vérifier si c'est une réponse HTML valide
        $content = $response->getContent();
        if (strpos($content, '<html') !== false || strpos($content, 'modes_paiement') !== false) {
            echo "✅ Contenu HTML détecté\n";
        } else {
            echo "ℹ️  Contenu: " . substr($content, 0, 200) . "...\n";
        }
    }
    
} catch (Throwable $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
} finally {
    $kernel->shutdown();
}

echo "\n=== FIN DEBUG ===\n";