<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\DivisionAdministrative;

/**
 * Service étendu pour récupérer les frontières géographiques de tous types de zones administratives françaises
 */
class GeographicBoundariesService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private FilesystemAdapter $cache;
    private EntityManagerInterface $entityManager;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, EntityManagerInterface $entityManager)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->cache = new FilesystemAdapter('geographic_boundaries', 0, '/tmp/technoprod_cache');
    }

    /**
     * Récupère les frontières géographiques pour un code postal
     * Combine toutes les communes ayant ce code postal
     */
    public function getCodePostalBoundaries(string $codePostal): ?array
    {
        $cacheKey = "codepostal_boundaries_{$codePostal}";
        $cachedBoundaries = $this->cache->getItem($cacheKey);

        if ($cachedBoundaries->isHit()) {
            $this->logger->info("📄 Frontières code postal {$codePostal} récupérées depuis le cache");
            return $cachedBoundaries->get();
        }

        try {
            // Récupérer toutes les communes avec ce code postal
            $communes = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->findBy(['codePostal' => $codePostal, 'actif' => true]);

            if (empty($communes)) {
                $this->logger->warning("⚠️ Aucune commune trouvée pour le code postal {$codePostal}");
                return null;
            }

            $allBoundaries = [];
            $communesInfo = [];

            foreach ($communes as $commune) {
                $geometry = $this->getCommuneGeometry($commune->getCodeInseeCommune(), $commune->getNomCommune());
                if ($geometry && isset($geometry['boundaries'])) {
                    $allBoundaries = array_merge($allBoundaries, $geometry['boundaries']);
                    $communesInfo[] = [
                        'codeInsee' => $commune->getCodeInseeCommune(),
                        'nom' => $commune->getNomCommune(),
                        'points' => count($geometry['boundaries'])
                    ];
                }
            }

            if (empty($allBoundaries)) {
                return null;
            }

            // Restructurer les données pour garder les frontières par commune
            $communesWithBoundaries = [];
            foreach ($communesInfo as $commune) {
                $geometry = $this->getCommuneGeometry($commune['codeInsee'], $commune['nom']);
                if ($geometry && isset($geometry['boundaries'])) {
                    $communesWithBoundaries[] = [
                        'codeInsee' => $commune['codeInsee'],
                        'nom' => $commune['nom'],
                        'boundaries' => $geometry['boundaries'],
                        'points' => count($geometry['boundaries'])
                    ];
                }
            }

            $result = [
                'type' => 'code_postal',
                'code' => $codePostal,
                'nom' => "Code postal {$codePostal}",
                'communes' => $communesWithBoundaries, // Chaque commune avec ses frontières
                'source' => 'api_officielle_communes',
                'points_count' => count($allBoundaries),
                'imported_at' => new \DateTimeImmutable()
            ];

            // Cache pour 7 jours
            $cachedBoundaries->set($result);
            $cachedBoundaries->expiresAfter(7 * 24 * 3600);
            $this->cache->save($cachedBoundaries);

            $this->logger->info("✅ Frontières code postal {$codePostal} créées: " . count($communesInfo) . " communes agrégées");
            return $result;

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération frontières code postal {$codePostal}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les frontières géographiques pour un canton via ses communes
     */
    public function getCantonBoundaries(string $codeCanton): ?array
    {
        $cacheKey = "canton_boundaries_{$codeCanton}";
        $cachedBoundaries = $this->cache->getItem($cacheKey);

        if ($cachedBoundaries->isHit()) {
            return $cachedBoundaries->get();
        }

        try {
            // Récupérer toutes les communes de ce canton
            $communes = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->findBy(['codeCanton' => $codeCanton, 'actif' => true]);

            if (empty($communes)) {
                $this->logger->warning("⚠️ Aucune commune trouvée pour le canton {$codeCanton}");
                return null;
            }

            // Restructurer les données pour garder les frontières par commune
            $communesWithBoundaries = [];
            foreach ($communes as $commune) {
                $geometry = $this->getCommuneGeometry($commune->getCodeInseeCommune(), $commune->getNomCommune());
                if ($geometry && isset($geometry['boundaries'])) {
                    $communesWithBoundaries[] = [
                        'codeInsee' => $commune->getCodeInseeCommune(),
                        'nom' => $commune->getNomCommune(),
                        'boundaries' => $geometry['boundaries'],
                        'points' => count($geometry['boundaries'])
                    ];
                }
            }

            if (empty($communesWithBoundaries)) {
                return null;
            }

            $result = [
                'type' => 'canton',
                'code' => $codeCanton,
                'nom' => "Canton {$codeCanton}",
                'communes' => $communesWithBoundaries, // Chaque commune avec ses frontières
                'source' => 'api_officielle_communes',
                'points_count' => array_sum(array_column($communesWithBoundaries, 'points')),
                'imported_at' => new \DateTimeImmutable()
            ];

            // Cache pour 30 jours
            $cachedBoundaries->set($result);
            $cachedBoundaries->expiresAfter(30 * 24 * 3600);
            $this->cache->save($cachedBoundaries);

            $this->logger->info("✅ Frontières canton {$codeCanton} créées: " . count($communesWithBoundaries) . " communes agrégées");
            return $result;

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération frontières canton {$codeCanton}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les frontières géographiques pour un département via ses communes
     */
    public function getDepartementBoundaries(string $codeDepartement): ?array
    {
        $cacheKey = "departement_boundaries_{$codeDepartement}";
        $cachedBoundaries = $this->cache->getItem($cacheKey);

        if ($cachedBoundaries->isHit()) {
            return $cachedBoundaries->get();
        }

        try {
            // Récupérer toutes les communes de ce département
            $communes = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->findBy(['codeDepartement' => $codeDepartement, 'actif' => true]);

            if (empty($communes)) {
                $this->logger->warning("⚠️ Aucune commune trouvée pour le département {$codeDepartement}");
                return null;
            }

            // Restructurer les données pour garder les frontières par commune
            $communesWithBoundaries = [];
            foreach ($communes as $commune) {
                $geometry = $this->getCommuneGeometry($commune->getCodeInseeCommune(), $commune->getNomCommune());
                if ($geometry && isset($geometry['boundaries'])) {
                    $communesWithBoundaries[] = [
                        'codeInsee' => $commune->getCodeInseeCommune(),
                        'nom' => $commune->getNomCommune(),
                        'boundaries' => $geometry['boundaries'],
                        'points' => count($geometry['boundaries'])
                    ];
                }
            }

            if (empty($communesWithBoundaries)) {
                return null;
            }

            $result = [
                'type' => 'departement',
                'code' => $codeDepartement,
                'nom' => "Département {$codeDepartement}",
                'communes' => $communesWithBoundaries, // Chaque commune avec ses frontières
                'source' => 'api_officielle_communes',
                'points_count' => array_sum(array_column($communesWithBoundaries, 'points')),
                'imported_at' => new \DateTimeImmutable()
            ];

            // Cache pour 1 an
            $cachedBoundaries->set($result);
            $cachedBoundaries->expiresAfter(365 * 24 * 3600);
            $this->cache->save($cachedBoundaries);

            $this->logger->info("✅ Frontières département {$codeDepartement} créées: " . count($communesWithBoundaries) . " communes agrégées");
            return $result;

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération frontières département {$codeDepartement}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les frontières géographiques pour une région via ses communes
     */
    public function getRegionBoundaries(string $codeRegion): ?array
    {
        $cacheKey = "region_boundaries_{$codeRegion}";
        $cachedBoundaries = $this->cache->getItem($cacheKey);

        if ($cachedBoundaries->isHit()) {
            return $cachedBoundaries->get();
        }

        try {
            // Récupérer toutes les communes de cette région
            $communes = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->findBy(['codeRegion' => $codeRegion, 'actif' => true]);

            if (empty($communes)) {
                $this->logger->warning("⚠️ Aucune commune trouvée pour la région {$codeRegion}");
                return null;
            }

            // Restructurer les données pour garder les frontières par commune
            $communesWithBoundaries = [];
            foreach ($communes as $commune) {
                $geometry = $this->getCommuneGeometry($commune->getCodeInseeCommune(), $commune->getNomCommune());
                if ($geometry && isset($geometry['boundaries'])) {
                    $communesWithBoundaries[] = [
                        'codeInsee' => $commune->getCodeInseeCommune(),
                        'nom' => $commune->getNomCommune(),
                        'boundaries' => $geometry['boundaries'],
                        'points' => count($geometry['boundaries'])
                    ];
                }
            }

            if (empty($communesWithBoundaries)) {
                return null;
            }

            $result = [
                'type' => 'region',
                'code' => $codeRegion,
                'nom' => "Région {$codeRegion}",
                'communes' => $communesWithBoundaries, // Chaque commune avec ses frontières
                'source' => 'api_officielle_communes',
                'points_count' => array_sum(array_column($communesWithBoundaries, 'points')),
                'imported_at' => new \DateTimeImmutable()
            ];

            // Cache pour 1 an
            $cachedBoundaries->set($result);
            $cachedBoundaries->expiresAfter(365 * 24 * 3600);
            $this->cache->save($cachedBoundaries);

            $this->logger->info("✅ Frontières région {$codeRegion} créées: " . count($communesWithBoundaries) . " communes agrégées");
            return $result;

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération frontières région {$codeRegion}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère les frontières géographiques pour un EPCI (existant, maintenu pour compatibilité)
     */
    public function getEpciBoundaries(string $codeEpci): ?array
    {
        $cacheKey = "epci_boundaries_{$codeEpci}";
        $cachedBoundaries = $this->cache->getItem($cacheKey);

        if ($cachedBoundaries->isHit()) {
            return $cachedBoundaries->get();
        }

        try {
            // Récupérer toutes les communes de cet EPCI
            $communes = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->findBy(['codeEpci' => $codeEpci, 'actif' => true]);

            if (empty($communes)) {
                return null;
            }

            $allBoundaries = [];
            $communesInfo = [];

            foreach ($communes as $commune) {
                $geometry = $this->getCommuneGeometry($commune->getCodeInseeCommune(), $commune->getNomCommune());
                if ($geometry && isset($geometry['boundaries'])) {
                    $allBoundaries = array_merge($allBoundaries, $geometry['boundaries']);
                    $communesInfo[] = [
                        'codeInsee' => $commune->getCodeInseeCommune(),
                        'nom' => $commune->getNomCommune(),
                        'points' => count($geometry['boundaries'])
                    ];
                }
            }

            if (empty($allBoundaries)) {
                return null;
            }

            $result = [
                'type' => 'epci',
                'code' => $codeEpci,
                'nom' => $communes[0]->getNomEpci(),
                'boundaries' => $this->createConvexHull($allBoundaries), // Utiliser la vraie enveloppe convexe
                'communes' => $communesInfo,
                'source' => 'api_officielle_aggregee',
                'points_count' => count($allBoundaries),
                'imported_at' => new \DateTimeImmutable()
            ];

            // Cache pour 30 jours
            $cachedBoundaries->set($result);
            $cachedBoundaries->expiresAfter(30 * 24 * 3600);
            $this->cache->save($cachedBoundaries);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération frontières EPCI {$codeEpci}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère la géométrie d'une commune (délègue au service existant)
     */
    private function getCommuneGeometry(string $codeInsee, string $nomCommune = ''): ?array
    {
        try {
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
                        return [
                            'codeInsee' => $codeInsee,
                            'nom' => $nomCommune ?: $data['nom'] ?? "Commune {$codeInsee}",
                            'boundaries' => $boundaries,
                            'source' => 'api_officielle',
                            'points_count' => count($boundaries)
                        ];
                    }
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur récupération géométrie commune {$codeInsee}: " . $e->getMessage());
            return null;
        }
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
                $coordinates = $this->extractPolygonCoordinates($geometry['coordinates']);
                break;
                
            case 'MultiPolygon':
                // Prendre le plus grand polygone
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
        
        if (isset($polygonData[0])) {
            foreach ($polygonData[0] as $coord) {
                if (is_array($coord) && count($coord) >= 2) {
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
     * Crée une enveloppe convexe réelle pour plusieurs ensembles de coordonnées
     * Utilise l'algorithme de Graham Scan pour calculer le contour extérieur uniquement
     */
    private function createConvexHull(array $allPoints): array
    {
        if (count($allPoints) < 3) {
            return $allPoints;
        }

        // Enlever les doublons
        $uniquePoints = [];
        foreach ($allPoints as $point) {
            $key = $point['lat'] . ',' . $point['lng'];
            $uniquePoints[$key] = $point;
        }
        $allPoints = array_values($uniquePoints);

        if (count($allPoints) < 3) {
            return $allPoints;
        }

        // Trouver le point le plus bas (et le plus à gauche en cas d'égalité)
        $bottom = $allPoints[0];
        $bottomIndex = 0;
        for ($i = 1; $i < count($allPoints); $i++) {
            if ($allPoints[$i]['lat'] < $bottom['lat'] || 
                ($allPoints[$i]['lat'] == $bottom['lat'] && $allPoints[$i]['lng'] < $bottom['lng'])) {
                $bottom = $allPoints[$i];
                $bottomIndex = $i;
            }
        }

        // Déplacer le point de base au début
        $temp = $allPoints[0];
        $allPoints[0] = $allPoints[$bottomIndex];
        $allPoints[$bottomIndex] = $temp;

        // Trier les autres points par angle polaire par rapport au point de base
        $base = $allPoints[0];
        $remainingPoints = array_slice($allPoints, 1);
        usort($remainingPoints, function($a, $b) use ($base) {
            $angleA = atan2($a['lat'] - $base['lat'], $a['lng'] - $base['lng']);
            $angleB = atan2($b['lat'] - $base['lat'], $b['lng'] - $base['lng']);
            if ($angleA == $angleB) {
                // Si même angle, prendre le plus proche
                $distA = ($a['lat'] - $base['lat']) ** 2 + ($a['lng'] - $base['lng']) ** 2;
                $distB = ($b['lat'] - $base['lat']) ** 2 + ($b['lng'] - $base['lng']) ** 2;
                return $distA <=> $distB;
            }
            return $angleA <=> $angleB;
        });
        $allPoints = array_merge([$base], $remainingPoints);

        // Algorithme de Graham Scan
        $hull = [$allPoints[0], $allPoints[1]];
        
        for ($i = 2; $i < count($allPoints); $i++) {
            // Enlever les points qui créent un virage à droite
            while (count($hull) > 1) {
                $p1 = $hull[count($hull) - 2];
                $p2 = $hull[count($hull) - 1];
                $p3 = $allPoints[$i];
                
                // Produit vectoriel pour déterminer l'orientation
                $cross = ($p2['lng'] - $p1['lng']) * ($p3['lat'] - $p1['lat']) - 
                         ($p2['lat'] - $p1['lat']) * ($p3['lng'] - $p1['lng']);
                
                if ($cross <= 0) {
                    array_pop($hull);
                } else {
                    break;
                }
            }
            $hull[] = $allPoints[$i];
        }

        // Fermer le polygone en ajoutant le premier point à la fin
        $hull[] = $hull[0];

        return $hull;
    }

    /**
     * Méthode universelle pour récupérer les frontières selon le type
     */
    public function getBoundariesByType(string $type, string $code): ?array
    {
        return match($type) {
            'commune' => $this->getCommuneGeometry($code),
            'code_postal' => $this->getCodePostalBoundaries($code),
            'canton' => $this->getCantonBoundaries($code),
            'departement' => $this->getDepartementBoundaries($code),
            'region' => $this->getRegionBoundaries($code),
            'epci' => $this->getEpciBoundaries($code),
            default => null
        };
    }

    /**
     * Vide tout le cache des frontières
     */
    public function clearAllCache(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Statistiques globales du cache
     */
    public function getCacheStats(): array
    {
        $cacheDir = '/tmp/technoprod_cache/geographic_boundaries';
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
            'cached_boundaries' => $count,
            'cache_size_mb' => round($size / 1024 / 1024, 2),
            'cache_directory' => $cacheDir
        ];
    }
}