<?php
/**
 * Script pour générer le MPD (Modèle Physique de Données) de TechnoProd
 * Utilise Doctrine pour extraire la structure complète
 */

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->bootEnv('.env.local');

// Configuration de la base de données
$connectionParams = [
    'dbname' => $_ENV['DATABASE_NAME'] ?? 'technoprod_db',
    'user' => $_ENV['DATABASE_USER'] ?? 'decorpub',
    'password' => $_ENV['DATABASE_PASSWORD'] ?? '',
    'host' => $_ENV['DATABASE_HOST'] ?? 'localhost',
    'driver' => 'pdo_pgsql',
];

try {
    $connection = DriverManager::getConnection($connectionParams);
    
    echo "# MPD TechnoProd - Modèle Physique de Données\n";
    echo "Date de génération: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Récupérer toutes les tables
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name";
    $tables = $connection->fetchAllAssociative($sql);
    
    foreach ($tables as $table) {
        $tableName = $table['table_name'];
        
        // Ignorer les tables système
        if (in_array($tableName, ['doctrine_migration_versions'])) {
            continue;
        }
        
        echo "## Table: {$tableName}\n";
        echo "```sql\n";
        
        // Structure de la table
        $sql = "SELECT 
                    column_name, 
                    data_type, 
                    character_maximum_length,
                    is_nullable, 
                    column_default,
                    CASE 
                        WHEN column_name = 'id' THEN 'PK'
                        WHEN column_name LIKE '%_id' THEN 'FK'
                        ELSE ''
                    END as key_type
                FROM information_schema.columns 
                WHERE table_name = ? 
                ORDER BY ordinal_position";
                
        $columns = $connection->fetchAllAssociative($sql, [$tableName]);
        
        foreach ($columns as $column) {
            $name = str_pad($column['column_name'], 30);
            $type = $column['data_type'];
            if ($column['character_maximum_length']) {
                $type .= "({$column['character_maximum_length']})";
            }
            $type = str_pad($type, 25);
            $nullable = $column['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
            $nullable = str_pad($nullable, 10);
            $key = $column['key_type'] ? "[{$column['key_type']}]" : '';
            
            echo "{$name} {$type} {$nullable} {$key}\n";
        }
        
        echo "```\n\n";
        
        // Relations (clés étrangères)
        $sql = "SELECT 
                    kcu.column_name, 
                    ccu.table_name AS foreign_table_name,
                    ccu.column_name AS foreign_column_name 
                FROM 
                    information_schema.table_constraints AS tc 
                    JOIN information_schema.key_column_usage AS kcu
                      ON tc.constraint_name = kcu.constraint_name
                    JOIN information_schema.constraint_column_usage AS ccu
                      ON ccu.constraint_name = tc.constraint_name
                WHERE constraint_type = 'FOREIGN KEY' 
                AND tc.table_name = ?";
                
        $foreignKeys = $connection->fetchAllAssociative($sql, [$tableName]);
        
        if (!empty($foreignKeys)) {
            echo "**Relations:**\n";
            foreach ($foreignKeys as $fk) {
                echo "- {$fk['column_name']} → {$fk['foreign_table_name']}.{$fk['foreign_column_name']}\n";
            }
            echo "\n";
        }
        
        echo "---\n\n";
    }
    
    // Résumé des entités principales et leurs redondances
    echo "# Analyse des redondances\n\n";
    
    echo "## Entités avec informations redondantes:\n\n";
    
    echo "### CLIENT\n";
    echo "**Champs problématiques:**\n";
    echo "- `nom` → devrait être dans Contact principal\n";
    echo "- `prenom` → devrait être dans Contact principal\n";
    echo "- `civilite` → devrait être dans Contact principal\n";
    echo "- `email` → devrait être dans Contact principal\n";
    echo "- `telephone` → devrait être dans Contact principal\n";
    echo "\n";
    
    echo "**Champs à conserver:**\n";
    echo "- `code` (identifiant unique)\n";
    echo "- `nom_entreprise` (dénomination sociale)\n";
    echo "- `forme_juridique_id` (relation)\n";
    echo "- `statut`, `notes`, `conditions_tarifs` (métadonnées business)\n";
    echo "- Relations vers contacts et adresses par défaut\n";
    echo "\n";
    
    echo "## Recommandations d'architecture:\n\n";
    echo "1. **Supprimer les champs redondants** de la table `client`\n";
    echo "2. **Utiliser les relations** vers `contact` et `adresse`\n";
    echo "3. **Ajouter des méthodes** dans l'entité Client pour accéder aux informations via les relations\n";
    echo "4. **Migrer les données existantes** vers les tables liées avant suppression\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}