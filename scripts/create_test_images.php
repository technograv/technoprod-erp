<?php

/**
 * Script pour créer des données de test ProductImage
 * À exécuter depuis le dossier racine : php scripts/create_test_images.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Entity\ProductImage;
use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

// Configuration de la base
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'technoprod_db';  
$user = $_ENV['DB_USER'] ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    // Connexion PDO directe pour plus de simplicité
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connexion à la base de données réussie\n";
    
    // Données des images de test
    $testImages = [
        [
            'produit_id' => 10, // Consultation informatique
            'filename' => 'test-product-1.svg',
            'original_name' => 'consultation-image.svg',
            'mime_type' => 'image/svg+xml',
            'file_size' => 500,
            'width' => 400,
            'height' => 300,
            'is_default' => true,
            'alt' => 'Image consultation informatique'
        ],
        [
            'produit_id' => 11, // Formation utilisateur
            'filename' => 'test-product-2.svg', 
            'original_name' => 'formation-image.svg',
            'mime_type' => 'image/svg+xml',
            'file_size' => 520,
            'width' => 400,
            'height' => 300,
            'is_default' => true,
            'alt' => 'Image formation utilisateur'
        ]
    ];
    
    // Préparer la requête d'insertion
    $sql = "INSERT INTO product_image (produit_id, filename, original_name, mime_type, file_size, width, height, is_default, alt, created_at) 
            VALUES (:produit_id, :filename, :original_name, :mime_type, :file_size, :width, :height, :is_default, :alt, NOW())";
    
    $stmt = $pdo->prepare($sql);
    
    $inserted = 0;
    foreach ($testImages as $imageData) {
        try {
            $stmt->execute($imageData);
            $inserted++;
            echo "✅ Image créée pour produit ID {$imageData['produit_id']}: {$imageData['filename']}\n";
        } catch (PDOException $e) {
            echo "❌ Erreur lors de l'insertion de l'image {$imageData['filename']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Terminé ! $inserted images de test créées.\n";
    echo "\n📋 Pour tester :\n";
    echo "1. Éditez un devis avec les produits 'Consultation informatique' ou 'Formation utilisateur'\n";
    echo "2. Cochez la case 'Photo' sur ces lignes\n"; 
    echo "3. Les images devraient apparaître dans la modal de sélection\n";
    echo "4. Sélectionnez une image et générez le PDF pour voir le résultat\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage() . "\n";
    exit(1);
}