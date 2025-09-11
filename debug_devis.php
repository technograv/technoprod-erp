<?php
// Script de debug pour les logs du devis
echo "=== LOGS DEVIS DEBUG ===\n";

// Chercher les logs dans les fichiers de log possibles
$logFiles = [
    'var/log/dev.log',
    '/var/log/php8.3-fpm.log',
    '/var/log/apache2/error.log',
    '/tmp/php_errors.log'
];

$found = false;

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "Fichier de log trouvé : $logFile\n";
        $content = file_get_contents($logFile);
        
        // Chercher les logs de DEBUG DEVIS
        if (strpos($content, 'DEBUG DEVIS') !== false) {
            echo "Logs de debug trouvés !\n";
            $lines = explode("\n", $content);
            $debugLines = array_filter($lines, function($line) {
                return strpos($line, 'DEBUG DEVIS') !== false || 
                       strpos($line, 'Client ID reçu') !== false ||
                       strpos($line, 'Client trouvé') !== false ||
                       strpos($line, 'ERREUR') !== false;
            });
            
            foreach (array_slice($debugLines, -20) as $line) {
                echo $line . "\n";
            }
            $found = true;
        }
    }
}

if (!$found) {
    echo "Aucun log de debug trouvé. Essayez error_log() après avoir soumis le formulaire.\n";
    echo "Logs attendus dans :\n";
    foreach ($logFiles as $file) {
        echo "- $file\n";
    }
}