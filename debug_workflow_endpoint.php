<?php
// Test direct de l'endpoint via reflection

use App\Entity\User;
use App\Controller\WorkflowController;

// Charger l'autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Créer le kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

try {
    // Récupérer les services
    $em = $container->get('doctrine.orm.entity_manager');
    $cacheService = $container->get(\App\Service\CommuneGeometryCacheService::class);
    $workflowService = $container->get(\App\Service\WorkflowService::class);
    $dashboardService = $container->get(\App\Service\DashboardService::class);
    $alerteService = $container->get(\App\Service\AlerteService::class);
    $secteurService = $container->get(\App\Service\SecteurService::class);
    $validator = $container->get('validator');
    
    // Créer le contrôleur
    $controller = new WorkflowController(
        $workflowService,
        $em,
        $validator,
        $dashboardService,
        $alerteService,
        $secteurService,
        $cacheService
    );
    
    // Récupérer l'utilisateur Nicolas Michel
    $user = $em->getRepository(User::class)->find(16);
    if (!$user) {
        throw new \Exception('User 16 not found');
    }
    
    echo "👤 Testing with user: {$user->getNom()} {$user->getPrenom()}\n";
    
    // Utiliser la réflection pour appeler getMonSecteur en simulant l'utilisateur connecté
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getMonSecteur');
    
    // Simuler l'utilisateur connecté via un mock
    $tokenStorage = $container->get('security.token_storage');
    $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
        $user, 
        'main', 
        $user->getRoles()
    );
    $tokenStorage->setToken($token);
    
    echo "🚀 Calling getMonSecteur()...\n";
    $response = $method->invoke($controller);
    $data = json_decode($response->getContent(), true);
    
    echo "📊 Response status: " . $response->getStatusCode() . "\n";
    echo "📄 Response data:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}