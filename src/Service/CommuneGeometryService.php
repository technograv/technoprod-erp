<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Service pour récupérer et mettre en cache les géométries réelles des communes françaises
 */
class CommuneGeometryService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private FilesystemAdapter $cache;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = new FilesystemAdapter('commune_geometries', 0, '/tmp/technoprod_cache');
    }

    /**
     * Récupère la géométrie réelle d'une commune depuis l'API officielle
     * 
     * @param string $codeInsee Code INSEE de la commune
     * @param string $nomCommune Nom de la commune (pour les logs)
     * @return array|null Géométrie de la commune ou null si erreur
     */
    public function getCommuneGeometry(string $codeInsee, string $nomCommune = ''): ?array
    {
        // Vérifier le cache d'abord
        $cacheKey = "geometry_{$codeInsee}";
        $cachedGeometry = $this->cache->getItem($cacheKey);

        if ($cachedGeometry->isHit()) {
            $this->logger->info("📄 Géométrie commune {$codeInsee} récupérée depuis le cache");
            return $cachedGeometry->get();
        }

        try {
            $this->logger->info("🌐 Récupération géométrie commune {$codeInsee} ({$nomCommune}) depuis l'API");
            
            // Appel API geo.api.gouv.fr pour la géométrie
            $response = $this->httpClient->request('GET', "https://geo.api.gouv.fr/communes/{$codeInsee}", [
                'query' => [
                    'geometry' => 'contour',
                    'format' => 'geojson'
                ],
                'timeout' => 15
            ]);
            
            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                
                if (isset($data['geometry'])) {
                    $boundaries = $this->extractBoundariesFromGeometry($data['geometry']);
                    
                    if ($boundaries && count($boundaries) >= 3) {
                        $geometry = [
                            'codeInsee' => $codeInsee,
                            'nom' => $nomCommune ?: $data['nom'] ?? "Commune {$codeInsee}",
                            'boundaries' => $boundaries,
                            'source' => 'api_officielle',
                            'points_count' => count($boundaries),
                            'imported_at' => new \DateTimeImmutable()
                        ];
                        
                        // Mettre en cache pour 7 jours
                        $cachedGeometry->set($geometry);
                        $cachedGeometry->expiresAfter(7 * 24 * 3600);
                        $this->cache->save($cachedGeometry);
                        
                        $this->logger->info("✅ Géométrie récupérée pour {$codeInsee}: " . count($boundaries) . " points de frontière");
                        return $geometry;
                    }
                }
            }
            
            $this->logger->warning("⚠️ Aucune géométrie valide trouvée pour commune {$codeInsee}");
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération géométrie {$codeInsee}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les géométries de plusieurs communes
     * 
     * @param array $communes Tableau avec codeInsee et nomCommune
     * @return array Géométries indexées par code INSEE
     */
    public function getMultipleCommuneGeometries(array $communes): array
    {
        $geometries = [];
        
        foreach ($communes as $commune) {
            $codeInsee = $commune['codeInseeCommune'] ?? $commune['codeInsee'] ?? null;
            $nomCommune = $commune['nomCommune'] ?? $commune['nom'] ?? '';
            
            if ($codeInsee) {
                $geometry = $this->getCommuneGeometry($codeInsee, $nomCommune);
                if ($geometry) {
                    $geometries[$codeInsee] = $geometry;
                }
                
                // Pause pour éviter de surcharger l'API
                usleep(100000); // 100ms
            }
        }
        
        return $geometries;
    }

    /**
     * Extrait les coordonnées de frontière depuis la géométrie GeoJSON
     */
    private function extractBoundariesFromGeometry(array $geometry): ?array
    {
        if (!isset($geometry['type']) || !isset($geometry['coordinates'])) {
            return null;
        }

        $coordinates = [];

        switch ($geometry['type']) {
            case 'Polygon':
                // Un seul polygone
                $coordinates = $this->extractPolygonCoordinates($geometry['coordinates']);
                break;
                
            case 'MultiPolygon':
                // Plusieurs polygones (îles, enclaves, etc.)
                // Prendre le plus grand polygone (généralement le territoire principal)
                $largestPolygon = [];
                $maxPoints = 0;
                
                foreach ($geometry['coordinates'] as $polygon) {
                    $polygonCoords = $this->extractPolygonCoordinates($polygon);
                    if (count($polygonCoords) > $maxPoints) {
                        $maxPoints = count($polygonCoords);
                        $largestPolygon = $polygonCoords;
                    }
                }
                $coordinates = $largestPolygon;
                break;
                
            default:
                $this->logger->warning("❌ Type de géométrie non supporté: {$geometry['type']}");
                return null;
        }

        return $coordinates;
    }

    /**
     * Extrait les coordonnées d'un polygone GeoJSON
     */
    private function extractPolygonCoordinates(array $polygonData): array
    {
        $coordinates = [];
        
        // Le premier élément contient le contour extérieur
        // Les éléments suivants sont des trous (que nous ignorons pour simplifier)
        if (isset($polygonData[0])) {
            foreach ($polygonData[0] as $coord) {
                if (is_array($coord) && count($coord) >= 2) {
                    // GeoJSON utilise [longitude, latitude]
                    // Google Maps utilise [latitude, longitude]
                    $coordinates[] = [
                        'lat' => (float) $coord[1],
                        'lng' => (float) $coord[0]
                    ];
                }
            }
        }

        return $coordinates;
    }

    /**
     * Simplifie la géométrie d'une commune pour améliorer les performances
     * 
     * @param array $geometry Géométrie de la commune
     * @param float $tolerance Tolérance de simplification
     * @return array Géométrie simplifiée
     */
    public function simplifyGeometry(array $geometry, float $tolerance = 0.001): array
    {
        if (!isset($geometry['boundaries']) || count($geometry['boundaries']) <= 10) {
            return $geometry;
        }

        $simplified = $geometry;
        $simplified['boundaries'] = $this->simplifyCoordinates($geometry['boundaries'], $tolerance);
        $simplified['original_points'] = count($geometry['boundaries']);
        $simplified['simplified_points'] = count($simplified['boundaries']);
        
        return $simplified;
    }

    /**
     * Simplifie un tableau de coordonnées avec l'algorithme Douglas-Peucker
     */
    private function simplifyCoordinates(array $coordinates, float $tolerance): array
    {
        if (count($coordinates) <= 3) {
            return $coordinates;
        }

        $simplified = [$coordinates[0]]; // Premier point
        
        for ($i = 1; $i < count($coordinates) - 1; $i++) {
            $prev = end($simplified);
            $current = $coordinates[$i];
            $next = $coordinates[$i + 1];
            
            // Calcul de la distance perpendiculaire approximative
            $distance = $this->perpendicularDistance($current, $prev, $next);
            
            if ($distance > $tolerance) {
                $simplified[] = $current;
            }
        }
        
        $simplified[] = $coordinates[count($coordinates) - 1]; // Dernier point
        
        return $simplified;
    }

    /**
     * Calcule la distance perpendiculaire d'un point à une ligne
     */
    private function perpendicularDistance(array $point, array $lineStart, array $lineEnd): float
    {
        $A = $lineEnd['lat'] - $lineStart['lat'];
        $B = $lineStart['lng'] - $lineEnd['lng'];
        $C = $lineEnd['lng'] * $lineStart['lat'] - $lineStart['lng'] * $lineEnd['lat'];
        
        $distance = abs($A * $point['lng'] + $B * $point['lat'] + $C) / sqrt($A * $A + $B * $B);
        
        return $distance;
    }

    /**
     * Vide le cache des géométries (pour renouveler les données)
     */
    public function clearCache(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Statistiques du cache
     */
    public function getCacheStats(): array
    {
        $cacheDir = '/tmp/technoprod_cache/commune_geometries';
        $count = 0;
        $size = 0;
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $count = count($files);
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                }
            }
        }
        
        return [
            'cached_communes' => $count,
            'cache_size_mb' => round($size / 1024 / 1024, 2),
            'cache_directory' => $cacheDir
        ];
    }
}