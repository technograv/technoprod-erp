#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$dashboardService = $container->get(\App\Service\DashboardService::class);

echo "=== Test Workflow Stats avec DashboardService ===\n\n";

// Test avec un utilisateur existant (ID 1)
try {
    $start = microtime(true);
    $workflowStats = $dashboardService->getWorkflowDashboardStats(1);
    $time = (microtime(true) - $start) * 1000;
    
    echo "✅ Workflow Stats (Utilisateur 1): " . round($time, 2) . "ms\n";
    echo "   - Devis brouillons: " . $workflowStats['devis_brouillons'] . "\n";
    echo "   - Devis à relancer: " . $workflowStats['devis_relances'] . "\n";
    echo "   - Prospects actifs: " . $workflowStats['prospects_actifs'] . "\n";
    echo "   - Total clients: " . $workflowStats['clients_total'] . "\n\n";
    
    // Test cache hit
    $start = microtime(true);
    $workflowStats2 = $dashboardService->getWorkflowDashboardStats(1);
    $time2 = (microtime(true) - $start) * 1000;
    
    echo "✅ Workflow Stats Cache Hit: " . round($time2, 2) . "ms\n";
    $improvement = $time / $time2;
    echo "   => Amélioration: " . round($improvement, 1) . "x plus rapide\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erreur Workflow Stats: " . $e->getMessage() . "\n\n";
}

// Test performance secteur
try {
    $start = microtime(true);
    $secteurStats = $dashboardService->getSecteurPerformanceData(1);
    $time = (microtime(true) - $start) * 1000;
    
    echo "✅ Secteur Performance (Utilisateur 1): " . round($time, 2) . "ms\n";
    echo "   - Secteurs: " . count($secteurStats['secteurs']) . "\n";
    echo "   - CA total: " . $secteurStats['resume']['total_ca'] . "€\n";
    echo "   - Total devis: " . $secteurStats['resume']['total_devis'] . "\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erreur Secteur Performance: " . $e->getMessage() . "\n\n";
}

echo "=== Test Terminé ===\n";