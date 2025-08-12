<?php

namespace App\Controller\Admin;

use App\Service\DocumentNumerotationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SystemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentNumerotationService $numerotationService
    ) {}

    // ================================
    // NUMEROTATION
    // ================================

    #[Route('/numerotation', name: 'app_admin_numerotation', methods: ['GET'])]
    public function numerotation(): Response
    {
        $configurations = $this->numerotationService->getAllConfigurations();
        
        return $this->render('admin/system/numerotation.html.twig', [
            'configurations' => $configurations
        ]);
    }

    #[Route('/numerotation/{prefixe}/update', name: 'app_admin_numerotation_update', methods: ['POST'])]
    public function updateNumerotation(Request $request, string $prefixe): JsonResponse
    {
        try {
            $data = $request->request->all();
            
            if (!isset($data['prochain_numero'])) {
                return $this->json(['error' => 'Numéro manquant'], 400);
            }
            
            $prochainNumero = intval($data['prochain_numero']);
            
            if ($prochainNumero <= 0) {
                return $this->json(['error' => 'Le numéro doit être supérieur à 0'], 400);
            }
            
            $this->numerotationService->updateConfiguration($prefixe, [
                'prochain_numero' => $prochainNumero,
                'format' => $data['format'] ?? null,
                'increment' => intval($data['increment'] ?? 1)
            ]);
            
            return $this->json([
                'success' => true,
                'message' => 'Configuration mise à jour avec succès',
                'prefixe' => $prefixe,
                'prochain_numero' => $prochainNumero
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // DEBUG & DIAGNOSTICS
    // ================================

    #[Route('/debug/secteurs', name: 'app_admin_debug_secteurs', methods: ['GET'])]
    public function debugSecteurs(): Response
    {
        try {
            // Récupérer tous les secteurs avec leurs statistiques
            $secteurs = $this->entityManager->createQuery(
                'SELECT s.id, s.nom, s.actif, COUNT(a.id) as attributions_count
                 FROM App\Entity\Secteur s 
                 LEFT JOIN s.attributions a
                 GROUP BY s.id, s.nom, s.actif
                 ORDER BY s.nom ASC'
            )->getResult();
            
            return $this->render('admin/system/debug_secteurs.html.twig', [
                'secteurs' => $secteurs
            ]);
        } catch (\Exception $e) {
            return $this->render('admin/system/debug_secteurs.html.twig', [
                'secteurs' => [],
                'error' => 'Erreur lors du chargement: ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/debug/attributions', name: 'app_admin_debug_attributions', methods: ['GET'])]
    public function debugAttributions(): JsonResponse
    {
        try {
            $attributions = $this->entityManager->createQuery(
                'SELECT a.id, a.type, a.identifiant, a.nom, s.nom as secteur_nom, COUNT(e.id) as exclusions_count
                 FROM App\Entity\AttributionSecteur a
                 JOIN a.secteur s
                 LEFT JOIN a.exclusions e
                 GROUP BY a.id, a.type, a.identifiant, a.nom, s.nom
                 ORDER BY s.nom ASC, a.type ASC'
            )->getResult();
            
            return $this->json([
                'attributions' => $attributions,
                'total' => count($attributions)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors du debug: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/debug-auth', name: 'app_admin_debug_auth', methods: ['GET'])]
    public function debugAuth(): JsonResponse
    {
        $user = $this->getUser();
        
        return $this->json([
            'authenticated' => $user !== null,
            'user_id' => $user?->getId(),
            'user_email' => $user?->getEmail(),
            'roles' => $user?->getRoles() ?? [],
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'is_manager' => $this->isGranted('ROLE_MANAGER'),
            'is_commercial' => $this->isGranted('ROLE_COMMERCIAL')
        ]);
    }

    // ================================
    // SYSTÈME & MAINTENANCE
    // ================================

    #[Route('/system/info', name: 'app_admin_system_info', methods: ['GET'])]
    public function systemInfo(): JsonResponse
    {
        try {
            $info = [
                'php' => [
                    'version' => PHP_VERSION,
                    'extensions' => [
                        'pdo' => extension_loaded('pdo'),
                        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
                        'json' => extension_loaded('json'),
                        'curl' => extension_loaded('curl'),
                        'mbstring' => extension_loaded('mbstring'),
                        'gd' => extension_loaded('gd'),
                        'zip' => extension_loaded('zip')
                    ]
                ],
                'symfony' => [
                    'version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
                    'environment' => $this->getParameter('kernel.environment'),
                    'debug' => $this->getParameter('kernel.debug')
                ],
                'database' => [
                    'platform' => 'PostgreSQL',
                    'connected' => $this->testDatabaseConnection()
                ],
                'disk_space' => [
                    'total' => disk_total_space('/'),
                    'free' => disk_free_space('/'),
                    'used_percent' => round((1 - disk_free_space('/') / disk_total_space('/')) * 100, 2)
                ],
                'memory' => [
                    'limit' => ini_get('memory_limit'),
                    'usage' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true)
                ]
            ];
            
            return $this->json(['system_info' => $info]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des informations: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/system/cache/clear', name: 'app_admin_system_cache_clear', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        try {
            // Nettoyer le cache Symfony
            $cacheDir = $this->getParameter('kernel.cache_dir');
            $this->clearDirectory($cacheDir);
            
            return $this->json([
                'success' => true,
                'message' => 'Cache vidé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors du nettoyage du cache: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/system/logs/tail', name: 'app_admin_system_logs', methods: ['GET'])]
    public function getSystemLogs(Request $request): JsonResponse
    {
        try {
            $lines = intval($request->query->get('lines', 50));
            $logFile = $this->getParameter('kernel.logs_dir') . '/dev.log';
            
            if (!file_exists($logFile)) {
                return $this->json(['logs' => [], 'message' => 'Fichier de log non trouvé']);
            }
            
            $logs = $this->tailFile($logFile, $lines);
            
            return $this->json([
                'logs' => $logs,
                'file' => $logFile,
                'lines' => count($logs)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la lecture des logs: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // STATISTIQUES AVANCÉES
    // ================================

    #[Route('/system/stats/database', name: 'app_admin_system_stats_database', methods: ['GET'])]
    public function getDatabaseStats(): JsonResponse
    {
        try {
            $stats = [];
            
            // Tables principales et leur nombre d'enregistrements
            $tables = [
                'User' => 'App\Entity\User',
                'Client' => 'App\Entity\Client',
                'Societe' => 'App\Entity\Societe',
                'Secteur' => 'App\Entity\Secteur',
                'Produit' => 'App\Entity\Produit',
                'Tag' => 'App\Entity\Tag',
                'FormeJuridique' => 'App\Entity\FormeJuridique',
                'Devis' => 'App\Entity\Devis'
            ];
            
            foreach ($tables as $name => $entity) {
                try {
                    $stats[$name] = $this->entityManager->getRepository($entity)->count([]);
                } catch (\Exception $e) {
                    $stats[$name] = 'Error: ' . $e->getMessage();
                }
            }
            
            return $this->json(['database_stats' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/system/health', name: 'app_admin_system_health', methods: ['GET'])]
    public function systemHealthCheck(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];
        
        try {
            // Vérification base de données
            $health['checks']['database'] = [
                'status' => $this->testDatabaseConnection() ? 'ok' : 'error',
                'message' => $this->testDatabaseConnection() ? 'Connection OK' : 'Connection failed'
            ];
            
            // Vérification espace disque
            $diskUsedPercent = round((1 - disk_free_space('/') / disk_total_space('/')) * 100, 2);
            $health['checks']['disk_space'] = [
                'status' => $diskUsedPercent < 90 ? 'ok' : 'warning',
                'message' => "Disk usage: {$diskUsedPercent}%",
                'used_percent' => $diskUsedPercent
            ];
            
            // Vérification mémoire
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryPercent = round(($memoryUsage / $memoryLimit) * 100, 2);
            
            $health['checks']['memory'] = [
                'status' => $memoryPercent < 80 ? 'ok' : 'warning',
                'message' => "Memory usage: {$memoryPercent}%",
                'used_percent' => $memoryPercent
            ];
            
            // Déterminer le statut global
            $hasError = false;
            $hasWarning = false;
            
            foreach ($health['checks'] as $check) {
                if ($check['status'] === 'error') {
                    $hasError = true;
                }
                if ($check['status'] === 'warning') {
                    $hasWarning = true;
                }
            }
            
            if ($hasError) {
                $health['status'] = 'error';
            } elseif ($hasWarning) {
                $health['status'] = 'warning';
            }
            
            return $this->json($health);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Health check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // ================================
    // HELPER METHODS
    // ================================

    private function testDatabaseConnection(): bool
    {
        try {
            $this->entityManager->getConnection()->connect();
            return $this->entityManager->getConnection()->isConnected();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir()) ? 'rmdir' : 'unlink';
            $todo($fileInfo->getRealPath());
        }
    }

    private function tailFile(string $filePath, int $lines): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $file = fopen($filePath, 'r');
        $lineCount = 0;
        $pos = -2;
        $beginning = false;
        $text = [];
        
        while ($lineCount <= $lines) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($file, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($file);
                $pos--;
            }
            $lineCount++;
            if ($beginning) {
                rewind($file);
            }
            $text[$lineCount] = fgets($file);
            if ($beginning) break;
        }
        
        fclose($file);
        
        return array_reverse(array_filter($text));
    }

    private function parseMemoryLimit(string $limit): int
    {
        if ($limit == '-1') {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $limit *= 1024 * 1024;
                break;
            case 'k':
                $limit *= 1024;
                break;
        }
        
        return $limit;
    }
}