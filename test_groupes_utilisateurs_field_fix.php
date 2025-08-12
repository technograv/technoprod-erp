<?php
// Test spécifique de la route groupes-utilisateurs après correction du champ

require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

echo "=== TEST GROUPES UTILISATEURS APRÈS CORRECTION CHAMP ===\n";

$_ENV['APP_ENV'] = 'dev';
$_ENV['APP_DEBUG'] = '1';
$_ENV['APP_BASE_URL'] = 'https://test.decorpub.fr:8080';

$kernel = new Kernel('dev', true);
$kernel->boot();

$request = Request::create('/admin/groupes-utilisateurs', 'GET', [], [], [], [
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
]);

try {
    $response = $kernel->handle($request);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content Length: " . strlen($response->getContent()) . " bytes\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ SUCCESS - Route groupes-utilisateurs fonctionne parfaitement !\n";
        $content = $response->getContent();
        
        if (strpos($content, 'groupes') !== false || strpos($content, 'utilisateurs') !== false) {
            echo "✅ Contenu groupes-utilisateurs détecté\n";
        }
        
        if (strpos($content, 'Unrecognized field') === false) {
            echo "✅ Plus d'erreur champ non reconnu\n";
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

echo "\n=== FIN TEST - CORRECTION CHAMP DOCTRINE ===\n";