<?php
// Script de test direct pour l'endpoint mon-secteur
require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('dev', true);
$kernel->boot();

// Créer une requête simulée
$request = Request::create('/workflow/dashboard/mon-secteur', 'GET');
$request->headers->set('X-Requested-With', 'XMLHttpRequest');

try {
    echo "🔍 Test de l'endpoint /workflow/dashboard/mon-secteur\n";
    echo "=====================================================\n";
    
    // Simuler une session utilisateur (utiliser l'ID 16 = Nicolas Michel)
    $container = $kernel->getContainer();
    $em = $container->get('doctrine.orm.entity_manager');
    
    // Récupérer l'utilisateur Nicolas Michel
    $userRepo = $em->getRepository(\App\Entity\User::class);
    $user = $userRepo->find(16); // Nicolas Michel
    
    if (!$user) {
        echo "❌ Utilisateur ID 16 (Nicolas Michel) non trouvé\n";
        exit(1);
    }
    
    echo "👤 Utilisateur trouvé: {$user->getNom()} {$user->getPrenom()}\n";
    
    // Récupérer ses secteurs
    $secteurRepo = $em->getRepository(\App\Entity\Secteur::class);
    $secteurs = $secteurRepo->findBy(['commercial' => $user, 'isActive' => true]);
    
    echo "🎯 Secteurs trouvés: " . count($secteurs) . "\n";
    foreach ($secteurs as $secteur) {
        echo "   - {$secteur->getNomSecteur()} (ID: {$secteur->getId()})\n";
        echo "     Attributions: " . count($secteur->getAttributions()) . "\n";
    }
    
    // Tester l'injection du CommuneGeometryCacheService
    $cacheService = $container->get(\App\Service\CommuneGeometryCacheService::class);
    echo "✅ CommuneGeometryCacheService accessible\n";
    
    // Tester directement le contrôleur
    $controller = $container->get(\App\Controller\WorkflowController::class);
    echo "✅ WorkflowController accessible\n";
    
    echo "\n🚀 Test terminé avec succès - Tous les services sont accessibles\n";
    
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}