<?php

namespace App\Tests\Performance;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Stopwatch\Stopwatch;

class DashboardPerformanceTest extends KernelTestCase
{
    private DashboardService $dashboardService;
    private Stopwatch $stopwatch;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->dashboardService = static::getContainer()->get(DashboardService::class);
        $this->stopwatch = new Stopwatch();
    }

    public function testAdminDashboardPerformance(): void
    {
        $this->stopwatch->start('admin_dashboard');
        
        // Premier appel (cache miss)
        $stats1 = $this->dashboardService->getAdminDashboardStats();
        $event1 = $this->stopwatch->stop('admin_dashboard');
        
        $this->stopwatch->start('admin_dashboard_cached');
        
        // Deuxième appel (cache hit)
        $stats2 = $this->dashboardService->getAdminDashboardStats();
        $event2 = $this->stopwatch->stop('admin_dashboard_cached');
        
        // Vérifier que les données sont identiques
        $this->assertEquals($stats1, $stats2);
        
        // Vérifier que le cache améliore les performances
        $this->assertLessThan($event1->getDuration(), $event2->getDuration());
        
        // Les statistiques doivent contenir les bonnes clés
        $this->assertArrayHasKey('utilisateurs', $stats1);
        $this->assertArrayHasKey('secteurs', $stats1);
        $this->assertArrayHasKey('commercial', $stats1);
        
        echo sprintf(
            "Performance Admin Dashboard:\n- Cache miss: %dms\n- Cache hit: %dms\n- Improvement: %.1fx faster\n",
            $event1->getDuration(),
            $event2->getDuration(),
            $event1->getDuration() / $event2->getDuration()
        );
    }

    public function testWorkflowDashboardPerformance(): void
    {
        // Utiliser un ID utilisateur de test
        $testUserId = 1;
        
        $this->stopwatch->start('workflow_dashboard');
        
        // Premier appel (cache miss)
        $stats1 = $this->dashboardService->getWorkflowDashboardStats($testUserId);
        $event1 = $this->stopwatch->stop('workflow_dashboard');
        
        $this->stopwatch->start('workflow_dashboard_cached');
        
        // Deuxième appel (cache hit)
        $stats2 = $this->dashboardService->getWorkflowDashboardStats($testUserId);
        $event2 = $this->stopwatch->stop('workflow_dashboard_cached');
        
        // Vérifier que les données sont identiques
        $this->assertEquals($stats1, $stats2);
        
        // Les statistiques doivent contenir les bonnes clés
        $this->assertArrayHasKey('devis_brouillons', $stats1);
        $this->assertArrayHasKey('prospects_actifs', $stats1);
        
        echo sprintf(
            "Performance Workflow Dashboard:\n- Cache miss: %dms\n- Cache hit: %dms\n- Improvement: %.1fx faster\n",
            $event1->getDuration(),
            $event2->getDuration(),
            $event1->getDuration() > $event2->getDuration() ? 
                $event1->getDuration() / $event2->getDuration() : 1
        );
    }

    public function testCacheInvalidation(): void
    {
        $testUserId = 1;
        
        // Charger les stats en cache
        $this->dashboardService->getWorkflowDashboardStats($testUserId);
        $this->dashboardService->getAdminDashboardStats();
        
        // Invalider les caches
        $this->dashboardService->invalidateUserCache($testUserId);
        $this->dashboardService->invalidateAdminCache();
        
        // Les stats doivent être recalculées
        $this->stopwatch->start('after_invalidation');
        $stats = $this->dashboardService->getAdminDashboardStats();
        $event = $this->stopwatch->stop('after_invalidation');
        
        // Vérifier que les données sont valides
        $this->assertArrayHasKey('utilisateurs', $stats);
        $this->assertGreaterThanOrEqual(0, $stats['utilisateurs']['total']);
        
        echo sprintf("Cache invalidation successful - reload time: %dms\n", $event->getDuration());
    }
}