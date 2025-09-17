<?php

namespace App\Service\Admin\Interfaces;

/**
 * Interface pour le service de dashboard d'administration
 * Définit les méthodes nécessaires pour la gestion du tableau de bord admin
 */
interface AdminDashboardServiceInterface
{
    /**
     * Retourne toutes les données nécessaires pour le dashboard admin
     *
     * @return array Tableau contenant les statistiques, commerciaux, secteurs, etc.
     */
    public function getAdminDashboardData(): array;

    /**
     * Invalide le cache des données admin
     *
     * @return void
     */
    public function invalidateAdminCache(): void;
}