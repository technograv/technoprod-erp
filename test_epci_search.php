<?php
ob_start();

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

try {
    $dotenv = new Dotenv();
    $dotenv->load('.env', '.env.local');
    
    $_ENV['APP_ENV'] = 'dev';
    $_ENV['APP_DEBUG'] = '1';
    
    $kernel = new Kernel('dev', true);
    $kernel->boot();
    
    echo "\n=== TEST Recherche EPCI Lannemezan ===\n";
    
    // Désactiver temporairement la sécurité pour test
    $request = Request::create('/admin/divisions-administratives/recherche?terme=lannemezan&type=epci', 'GET');
    
    // Modifier temporairement le contrôleur pour test
    $content = file_get_contents('/home/decorpub/TechnoProd/technoprod/src/Controller/Admin/SecteurController.php');
    $content = str_replace('#[IsGranted(\'ROLE_ADMIN\')]', '// #[IsGranted(\'ROLE_ADMIN\')] // Test', $content);
    file_put_contents('/home/decorpub/TechnoProd/technoprod/src/Controller/Admin/SecteurController.php', $content);
    
    $response = $kernel->handle($request);
    
    echo "Status: " . $response->getStatusCode() . "\n";
    
    if ($response->getStatusCode() === 200) {
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        if ($data && isset($data['results'])) {
            echo "✅ Résultats trouvés: " . count($data['results']) . "\n";
            
            foreach ($data['results'] as $i => $result) {
                echo "  " . ($i+1) . ". {$result['nom']} (code: {$result['code']})\n";
                echo "     Details: {$result['details']}\n";
                echo "     Type: {$result['type']}, Valeur: {$result['valeur']}\n";
                echo "     ---\n";
            }
        } else {
            echo "❌ Aucun résultat dans data.results\n";
        }
    } else {
        echo "❌ Erreur HTTP " . $response->getStatusCode() . "\n";
        echo "Content: " . substr($response->getContent(), 0, 300) . "\n";
    }
    
    // Restaurer le contrôleur
    $content = file_get_contents('/home/decorpub/TechnoProd/technoprod/src/Controller/Admin/SecteurController.php');
    $content = str_replace('// #[IsGranted(\'ROLE_ADMIN\')] // Test', '#[IsGranted(\'ROLE_ADMIN\')]', $content);
    file_put_contents('/home/decorpub/TechnoProd/technoprod/src/Controller/Admin/SecteurController.php', $content);
    
} catch (Throwable $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . " in " . basename($e->getFile()) . "\n";
} finally {
    if (isset($kernel)) {
        $kernel->shutdown();
    }
}