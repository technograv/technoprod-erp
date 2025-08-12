<?php
// Test spécifique de la route formes juridiques après correction

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== TEST FORMES JURIDIQUES APRÈS CORRECTION ===\n";

$_ENV['APP_ENV'] = 'dev';
$_ENV['APP_DEBUG'] = '1';
$_ENV['APP_BASE_URL'] = 'https://test.decorpub.fr:8080';

$kernel = new Kernel('dev', true);
$kernel->boot();

$request = Request::create('/admin/formes-juridiques', 'GET', [], [], [], [
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);

try {
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content Length: " . strlen($response->getContent()) . " bytes\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ SUCCESS - Route fonctionne parfaitement !\n";
        $content = $response->getContent();
        
        if (strpos($content, 'formes-juridiques') !== false || strpos($content, 'Forme Juridique') !== false) {
            echo "✅ Contenu formes juridiques détecté\n";
        }
        
        // Vérifier que la query fonctionne (pas d'erreur denomination)
        if (strpos($content, 'denomination') === false) {
            echo "✅ Plus d'erreur 'denomination' dans la réponse\n";
        }
        
        echo "\n📄 Aperçu du contenu (200 premiers caractères):\n";
        echo substr($content, 0, 200) . "...\n";
        
    } elseif ($response->getStatusCode() === 302) {
        echo "🔄 REDIRECT - Redirection d'authentification (comportement normal)\n";
        echo "Location: " . $response->headers->get('Location') . "\n";
    } else {
        echo "❌ ERROR - Status: " . $response->getStatusCode() . "\n";
        echo "Content: " . substr($response->getContent(), 0, 300) . "\n";
    }
    
} catch (Throwable $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    $kernel->shutdown();
}

echo "\n=== FIN TEST - CORRECTION REPOSITORY ===\n";