<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Secteur;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service de gestion du cache pour optimiser les performances
 * Implémente la stratégie de cache selon les bonnes pratiques Symfony
 */
class CacheService
{
    public function __construct(
        private CacheInterface $cache,
        private AlerteService $alerteService,
        private SecteurService $secteurService,
        private DashboardService $dashboardService
    ) {}

    /**
     * Cache des alertes utilisateur (TTL: 5 minutes)
     */
    public function getCachedAlertes(User $user): array
    {
        $key = 'alertes_user_' . $user->getId();
        
        return $this->cache->get($key, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300); // 5 minutes
            
            return $this->alerteService->getVisibleAlertsForUser($user);
        });
    }

    /**
     * Cache des données secteur commercial (TTL: 15 minutes)
     */
    public function getCachedSecteurData(User $commercial): array
    {
        $key = 'secteur_data_' . $commercial->getId();
        
        return $this->cache->get($key, function (ItemInterface $item) use ($commercial) {
            $item->expiresAfter(900); // 15 minutes
            
            return $this->secteurService->getSecteurDataForCommercial($commercial);
        });
    }

    /**
     * Cache des statistiques dashboard (TTL: 10 minutes)
     */
    public function getCachedDashboardStats(User $user): array
    {
        $key = 'dashboard_stats_' . $user->getId();
        
        return $this->cache->get($key, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(600); // 10 minutes
            
            return $this->dashboardService->getDashboardStats($user);
        });
    }

    /**
     * Cache des performances commerciales (TTL: 30 minutes)
     */
    public function getCachedPerformances(User $commercial): array
    {
        $key = 'performances_' . $commercial->getId();
        
        return $this->cache->get($key, function (ItemInterface $item) use ($commercial) {
            $item->expiresAfter(1800); // 30 minutes
            
            return $this->secteurService->calculerPerformancesCommercial($commercial);
        });
    }

    /**
     * Invalide le cache d'un utilisateur (à appeler lors de modifications)
     */
    public function invalidateUserCache(User $user): void
    {
        $patterns = [
            'alertes_user_' . $user->getId(),
            'secteur_data_' . $user->getId(),
            'dashboard_stats_' . $user->getId(),
            'performances_' . $user->getId()
        ];
        
        foreach ($patterns as $pattern) {
            $this->cache->delete($pattern);
        }
    }

    /**
     * Invalide tous les caches des alertes (à appeler lors modification d'alertes)
     */
    public function invalidateAllAlertesCache(): void
    {
        // Note: Pour une invalidation plus sophistiquée, utiliser TagAwareCacheInterface
        $this->cache->clear();
    }

    /**
     * Invalide les caches secteur (à appeler lors modification secteurs)
     */
    public function invalidateSecteurCache(Secteur $secteur): void
    {
        if ($secteur->getCommercial()) {
            $patterns = [
                'secteur_data_' . $secteur->getCommercial()->getId(),
                'dashboard_stats_' . $secteur->getCommercial()->getId(),
                'performances_' . $secteur->getCommercial()->getId()
            ];
            
            foreach ($patterns as $pattern) {
                $this->cache->delete($pattern);
            }
        }
    }
}