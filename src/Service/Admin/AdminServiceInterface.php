<?php

namespace App\Service\Admin;

/**
 * Interface commune pour tous les services d'administration
 * Définit les méthodes de base pour la gestion des entités admin
 */
interface AdminServiceInterface
{
    /**
     * Récupère tous les éléments avec pagination optionnelle
     */
    public function findAll(?int $page = null, ?int $limit = null): array;

    /**
     * Récupère un élément par son ID
     */
    public function findById(int $id): ?object;

    /**
     * Crée un nouvel élément
     */
    public function create(array $data): object;

    /**
     * Met à jour un élément existant
     */
    public function update(object $entity, array $data): object;

    /**
     * Supprime un élément
     */
    public function delete(object $entity): bool;

    /**
     * Récupère les statistiques pour le dashboard
     */
    public function getStatistics(): array;
}