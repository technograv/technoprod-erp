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

echo "=== Performance Test: DashboardService avec Redis ===\n\n";

// Test 1: Admin Dashboard Stats - Cache Miss
$start = microtime(true);
$stats1 = $dashboardService->getAdminDashboardStats();
$time1 = (microtime(true) - $start) * 1000;

echo "1. Admin Stats (Cache Miss): " . round($time1, 2) . "ms\n";

// Test 2: Admin Dashboard Stats - Cache Hit  
$start = microtime(true);
$stats2 = $dashboardService->getAdminDashboardStats();
$time2 = (microtime(true) - $start) * 1000;

echo "2. Admin Stats (Cache Hit):  " . round($time2, 2) . "ms\n";

// Amélioration
$improvement = $time1 / $time2;
echo "   => Amélioration: " . round($improvement, 1) . "x plus rapide\n\n";

// Vérifier contenu cache
echo "3. Contenu statistics admin:\n";
echo "   - Utilisateurs total: " . $stats1['utilisateurs']['total'] . "\n";
echo "   - Utilisateurs actifs: " . $stats1['utilisateurs']['actifs'] . "\n";
echo "   - Secteurs: " . $stats1['secteurs'] . "\n";
echo "   - Clients: " . $stats1['commercial']['clients'] . "\n";
echo "   - Prospects: " . $stats1['commercial']['prospects'] . "\n\n";

// Test 3: Invalidation cache et rechargement
$dashboardService->invalidateAdminCache();
$start = microtime(true);
$stats3 = $dashboardService->getAdminDashboardStats();
$time3 = (microtime(true) - $start) * 1000;

echo "4. Admin Stats (Après invalidation): " . round($time3, 2) . "ms\n";

// Test Redis direct
echo "\n=== Test Redis Direct ===\n";
$redis = new Predis\Client('tcp://127.0.0.1:6379');
$redis->set('test_technoprod', 'Performance OK');
$value = $redis->get('test_technoprod');
echo "Redis direct: " . $value . "\n";

// Lister les clés cache TechnoProd
$keys = $redis->keys('*technoprod*');
echo "Clés cache TechnoProd: " . count($keys) . " trouvées\n";
foreach (array_slice($keys, 0, 5) as $key) {
    echo "  - $key\n";
}

echo "\n=== Performance Test Terminé ===\n";