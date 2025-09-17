<?php

namespace App\Service\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour migrer progressivement le code de l'ancien AdminController
 * vers les nouveaux contrôleurs spécialisés
 * 
 * Cette approche permet de garder l'application fonctionnelle pendant la refactorisation
 */
class AdminControllerMigrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Migre une méthode spécifique de l'AdminController
     * 
     * @param string $methodName Le nom de la méthode à migrer
     * @param callable $implementation La nouvelle implémentation
     * @return mixed Le résultat de l'implémentation
     */
    public function migrateMethod(string $methodName, callable $implementation)
    {
        $this->logger->info("Migrating AdminController method: {$methodName}");
        
        try {
            $result = $implementation();
            $this->logger->info("Successfully migrated method: {$methodName}");
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Failed to migrate method: {$methodName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Vérifie si une méthode a été migrée avec succès
     */
    public function isMethodMigrated(string $methodName): bool
    {
        // TODO: Implémenter un système de tracking des migrations
        return false;
    }

    /**
     * Retourne la liste des méthodes restant à migrer
     */
    public function getPendingMigrations(): array
    {
        return [
            'dashboard',
            'debugSecteurs',
            'debugAttributions', 
            'debugAuth',
            'getAllSecteursGeoData',
            'getGroupe',
            'updateGroupe',
            'createGroupe',
            'deleteGroupe',
            'toggleGroupe',
            'parametres',
            'updateDelaisWorkflow',
            'getCommerciaux',
            'getSecteursList',
            'getSecteursAdmin',
            'updateObjectifsCommercial',
            'getPerformancesCommerciales',
            'exportPerformancesCommerciales',
            'alertes',
            'createAlerte',
            'getAlerte',
            'updateAlerte',
            'deleteAlerte'
        ];
    }
}