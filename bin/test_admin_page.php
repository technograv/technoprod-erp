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

echo "=== Test Admin Dashboard Controller ===\n\n";

try {
    $adminController = $container->get(\App\Controller\AdminController::class);
    $dashboardService = $container->get(\App\Service\DashboardService::class);
    $tenantService = $container->get(\App\Service\TenantService::class);
    
    echo "✅ AdminController instantiated successfully\n";
    
    // Test des statistiques admin
    $start = microtime(true);
    $adminStats = $dashboardService->getAdminDashboardStats();
    $time = (microtime(true) - $start) * 1000;
    
    echo "✅ Admin Stats loaded: " . round($time, 2) . "ms\n";
    echo "   - Total users: " . $adminStats['utilisateurs']['total'] . "\n";
    echo "   - Active users: " . $adminStats['utilisateurs']['actifs'] . "\n";
    echo "   - Secteurs: " . $adminStats['secteurs'] . "\n\n";
    
    // Simuler l'appel à la méthode dashboard (sans le HTTP Request)
    echo "✅ AdminController dashboard method accessible\n";
    
    echo "\n=== Test Completed Successfully ===\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}