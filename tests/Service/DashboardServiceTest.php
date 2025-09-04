<?php

namespace App\Tests\Service;

use App\Service\DashboardService;
use App\Repository\UserRepository;
use App\Repository\SecteurRepository;
use App\Repository\ProduitRepository;
use App\Repository\FormeJuridiqueRepository;
use App\Repository\ClientRepository;
use App\Repository\DevisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DashboardServiceTest extends TestCase
{
    private DashboardService $dashboardService;
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|Connection $connection;
    private MockObject|CacheInterface $cache;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheInterface::class);
        
        $userRepository = $this->createMock(UserRepository::class);
        $secteurRepository = $this->createMock(SecteurRepository::class);
        $produitRepository = $this->createMock(ProduitRepository::class);
        $formeJuridiqueRepository = $this->createMock(FormeJuridiqueRepository::class);
        $clientRepository = $this->createMock(ClientRepository::class);
        $devisRepository = $this->createMock(DevisRepository::class);

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->dashboardService = new DashboardService(
            $this->entityManager,
            $userRepository,
            $secteurRepository,
            $produitRepository,
            $formeJuridiqueRepository,
            $clientRepository,
            $devisRepository,
            $this->cache
        );
    }

    public function testGetAdminDashboardStats(): void
    {
        // Mock des données statistiques
        $expectedStats = [
            'utilisateurs' => [
                'total' => 8,
                'actifs' => 7,
                'administrateurs' => 3
            ],
            'secteurs' => 4,
            'zones' => 4,
            'produits' => 21,
            'formes_juridiques' => [
                'total' => 8,
                'actives' => 8
            ],
            'commercial' => [
                'clients' => 15,
                'prospects' => 5,
                'devis_signes' => 12,
                'devis_en_cours' => 3
            ]
        ];

        // Mock du cache - simule cache hit
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('admin_dashboard_stats')
            ->willReturn($expectedStats);

        // Test
        $result = $this->dashboardService->getAdminDashboardStats();

        // Assertions
        $this->assertEquals($expectedStats, $result);
        $this->assertArrayHasKey('utilisateurs', $result);
        $this->assertArrayHasKey('commercial', $result);
        $this->assertEquals(8, $result['utilisateurs']['total']);
        $this->assertEquals(21, $result['produits']);
    }

    public function testGetWorkflowDashboardStats(): void
    {
        $userId = 1;
        $expectedStats = [
            'devis_brouillons' => 3,
            'devis_relances' => 2,
            'prospects_actifs' => 8,
            'clients_total' => 15
        ];

        // Mock du cache
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with("workflow_dashboard_stats_user_{$userId}")
            ->willReturn($expectedStats);

        // Test
        $result = $this->dashboardService->getWorkflowDashboardStats($userId);

        // Assertions
        $this->assertEquals($expectedStats, $result);
        $this->assertEquals(3, $result['devis_brouillons']);
        $this->assertEquals(15, $result['clients_total']);
    }

    public function testGetSecteurPerformanceData(): void
    {
        $userId = 1;
        $expectedData = [
            'secteurs' => [
                [
                    'id' => 1,
                    'secteur_nom' => 'Centre-Ville',
                    'couleur' => '#007bff',
                    'nombre_clients' => 10,
                    'nombre_prospects' => 5,
                    'nombre_devis' => 8,
                    'ca_signe' => 45000,
                    'nombre_zones' => 1,
                    'zones_noms' => 'Centre-Ville'
                ]
            ],
            'resume' => [
                'total_ca' => 45000,
                'total_devis' => 8,
                'total_clients' => 10
            ]
        ];

        // Mock du cache
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with("secteur_performance_user_{$userId}")
            ->willReturn($expectedData);

        // Test
        $result = $this->dashboardService->getSecteurPerformanceData($userId);

        // Assertions
        $this->assertEquals($expectedData, $result);
        $this->assertArrayHasKey('secteurs', $result);
        $this->assertArrayHasKey('resume', $result);
        $this->assertEquals(45000, $result['resume']['total_ca']);
    }

    public function testInvalidateUserCache(): void
    {
        $userId = 1;

        // Mock des suppressions de cache
        $this->cache
            ->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(
                ["workflow_dashboard_stats_user_{$userId}"],
                ["secteur_performance_user_{$userId}"]
            );

        // Test
        $this->dashboardService->invalidateUserCache($userId);
    }

    public function testInvalidateAdminCache(): void
    {
        // Mock de la suppression du cache admin
        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with('admin_dashboard_stats');

        // Test
        $this->dashboardService->invalidateAdminCache();
    }
}