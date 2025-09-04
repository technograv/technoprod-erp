#!/usr/bin/env php
<?php

echo "=== TechnoProd - Tests d'Intégration API ===\n\n";

// Vérifier que PHPUnit est disponible
if (!file_exists(__DIR__ . '/../vendor/bin/phpunit')) {
    echo "❌ PHPUnit n'est pas installé. Exécutez 'composer install --dev'\n";
    exit(1);
}

$testSuites = [
    'AdminApiTest' => 'Tests API Administration',
    'WorkflowApiTest' => 'Tests API Workflow Commercial', 
    'ClientApiTest' => 'Tests API Gestion Clients',
    'CommuneApiTest' => 'Tests API Communes Françaises'
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$results = [];

foreach ($testSuites as $testClass => $description) {
    echo "🧪 {$description}\n";
    echo str_repeat('-', 50) . "\n";
    
    $testFile = "tests/Integration/{$testClass}.php";
    $command = "cd " . dirname(__DIR__) . " && vendor/bin/phpunit {$testFile} --colors=never 2>&1";
    
    $startTime = microtime(true);
    $output = shell_exec($command);
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    // Analyser les résultats
    if (preg_match('/Tests: (\d+), Assertions: (\d+)(?:, Errors: (\d+))?(?:, Failures: (\d+))?/', $output, $matches)) {
        $tests = (int)$matches[1];
        $assertions = (int)$matches[2];
        $errors = isset($matches[3]) ? (int)$matches[3] : 0;
        $failures = isset($matches[4]) ? (int)$matches[4] : 0;
        
        $totalTests += $tests;
        
        if ($errors === 0 && $failures === 0) {
            echo "✅ {$tests} tests passés, {$assertions} assertions - {$duration}ms\n";
            $passedTests += $tests;
            $results[$testClass] = 'PASSED';
        } else {
            echo "❌ {$tests} tests, {$errors} erreurs, {$failures} échecs - {$duration}ms\n";
            $failedTests += $tests;
            $results[$testClass] = 'FAILED';
            
            // Afficher les détails des erreurs
            if (strpos($output, 'ERRORS!') !== false || strpos($output, 'FAILURES!') !== false) {
                $lines = explode("\n", $output);
                $inError = false;
                foreach ($lines as $line) {
                    if (strpos($line, 'There was') !== false || strpos($line, 'There were') !== false) {
                        $inError = true;
                    }
                    if ($inError && (empty(trim($line)) || strpos($line, 'Generated') !== false)) {
                        break;
                    }
                    if ($inError) {
                        echo "   " . $line . "\n";
                    }
                }
            }
        }
    } else {
        echo "⚠️  Impossible d'analyser les résultats - {$duration}ms\n";
        $results[$testClass] = 'UNKNOWN';
        
        // Afficher les premières lignes de sortie pour diagnostic
        $lines = array_slice(explode("\n", $output), 0, 10);
        foreach ($lines as $line) {
            if (trim($line)) {
                echo "   " . $line . "\n";
            }
        }
    }
    
    echo "\n";
}

echo "=== RÉSUMÉ FINAL ===\n";
echo "Total tests exécutés: {$totalTests}\n";
echo "Tests réussis: {$passedTests}\n";
echo "Tests échoués: {$failedTests}\n\n";

echo "Détail par suite de tests:\n";
foreach ($results as $suite => $status) {
    $icon = $status === 'PASSED' ? '✅' : ($status === 'FAILED' ? '❌' : '⚠️');
    echo "{$icon} {$suite}: {$status}\n";
}

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "\nTaux de réussite: {$successRate}%\n";

if ($failedTests === 0) {
    echo "\n🎉 Tous les tests d'intégration API sont passés avec succès!\n";
    exit(0);
} else {
    echo "\n⚠️  {$failedTests} tests ont échoué. Vérifiez les erreurs ci-dessus.\n";
    exit(1);
}