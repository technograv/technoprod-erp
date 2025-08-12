<?php
// Test spécifique de la route societes après correction

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== TEST SOCIETES APRÈS CORRECTION ===\n";

$_ENV['APP_ENV'] = 'dev';
$_ENV['APP_DEBUG'] = '1';
$_ENV['APP_BASE_URL'] = 'https://test.decorpub.fr:8080';

$kernel = new Kernel('dev', true);
$kernel->boot();

$request = Request::create('/admin/societes', 'GET', [], [], [], [
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);

try {
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content Length: " . strlen($response->getContent()) . " bytes\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ SUCCESS - Route societes fonctionne !\n";
        $content = $response->getContent();
        
        if (strpos($content, 'societes') !== false) {
            echo "✅ Contenu societes détecté\n";
        }
        
        if (strpos($content, 'Variable "is_societe_mere" does not exist') === false) {
            echo "✅ Plus d'erreur variable is_societe_mere manquante\n";
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

echo "\n=== FIN TEST - CORRECTION TEMPLATE SOCIETES ===\n";