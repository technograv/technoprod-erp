<?php
// Test spécifique de la route modes-reglement après correction

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== TEST MODES REGLEMENT APRÈS CORRECTION ===\n";

$_ENV['APP_ENV'] = 'dev';
$_ENV['APP_DEBUG'] = '1';
$_ENV['APP_BASE_URL'] = 'https://test.decorpub.fr:8080';

$kernel = new Kernel('dev', true);
$kernel->boot();

$request = Request::create('/admin/modes-reglement', 'GET', [], [], [], [
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);

try {
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content Length: " . strlen($response->getContent()) . " bytes\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ SUCCESS - Route modes-reglement fonctionne !\n";
        $content = $response->getContent();
        
        if (strpos($content, 'modes-reglement') !== false) {
            echo "✅ Contenu modes-reglement détecté\n";
        }
        
        if (strpos($content, 'Variable "modes_paiement" does not exist') === false) {
            echo "✅ Plus d'erreur variable modes_paiement manquante\n";
        }
        
        echo "\n📄 Aperçu du contenu (300 premiers caractères):\n";
        echo substr($content, 0, 300) . "...\n";
        
    } elseif ($response->getStatusCode() === 302) {
        echo "🔄 REDIRECT - Redirection d'authentification (comportement normal)\n";
        echo "Location: " . $response->headers->get('Location') . "\n";
    } else {
        echo "❌ ERROR - Status: " . $response->getStatusCode() . "\n";
        echo "Content: " . substr($response->getContent(), 0, 500) . "\n";
    }
    
} catch (Throwable $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
} finally {
    $kernel->shutdown();
}

echo "\n=== FIN TEST - CORRECTION TEMPLATE MODES REGLEMENT ===\n";