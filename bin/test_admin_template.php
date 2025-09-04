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

echo "=== Test Template Admin Dashboard ===\n\n";

// Test du format des données pour le template
$adminStats = $dashboardService->getAdminDashboardStats();

echo "✅ Clés disponibles dans adminStats :\n";
foreach (array_keys($adminStats) as $key) {
    echo "   - $key\n";
}

echo "\n✅ Vérification compatibilité template :\n";

// Vérifier les clés attendues par le template
$requiredKeys = ['users', 'users_actifs', 'admins', 'secteurs', 'zones', 'produits', 'formes_juridiques'];
$missingKeys = [];

foreach ($requiredKeys as $key) {
    if (isset($adminStats[$key])) {
        echo "   ✅ $key: " . $adminStats[$key] . "\n";
    } else {
        $missingKeys[] = $key;
        echo "   ❌ $key: MANQUANT\n";
    }
}

if (empty($missingKeys)) {
    echo "\n🎉 Toutes les clés requises par le template sont présentes !\n";
} else {
    echo "\n⚠️  Clés manquantes: " . implode(', ', $missingKeys) . "\n";
}

echo "\n=== Test Terminé ===\n";