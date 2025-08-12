<?php

namespace App\Service;

use App\Entity\CommuneGeometryCache;
use App\Repository\CommuneGeometryCacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de cache pour les géométries des communes avec fallback API
 */
class CommuneGeometryCacheService
{
    private EntityManagerInterface $entityManager;
    private CommuneGeometryCacheRepository $repository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(CommuneGeometryCache::class);
        $this->logger = $logger;
    }

    /**
     * Récupère la géométrie d'une commune (cache + fallback API)
     */
    public function getCommuneGeometry(string $codeInsee, string $nomCommune): ?array
    {
        // 1. Vérifier le cache
        $cached = $this->repository->findByCodeInsee($codeInsee);
        
        if ($cached && !$cached->isExpired()) {
            $this->logger->info("📦 Cache hit pour commune {$nomCommune} ({$codeInsee})");
            return $cached->getGeometryData();
        }

        // 2. Fallback API si pas en cache ou expiré
        $this->logger->info("🌐 Cache miss - Récupération API pour {$nomCommune} ({$codeInsee})");
        $geometry = $this->fetchFromApi($codeInsee, $nomCommune);

        // 3. Mettre en cache le résultat
        if ($geometry) {
            $this->cacheGeometry($codeInsee, $nomCommune, $geometry, 'geo.api.gouv.fr');
            return $geometry;
        }

        // 4. Marquer comme invalide si échec API
        $this->cacheError($codeInsee, $nomCommune, "Échec récupération API");
        return null;
    }

    /**
     * Récupère les géométries de plusieurs communes en optimisant les appels
     */
    public function getMultipleCommunesGeometry(array $communesData): array
    {
        $this->logger->info("🔍 Récupération géométries pour " . count($communesData) . " communes");
        
        // 1. Récupérer ce qui est en cache
        $cached = $this->repository->findByEpciCommunes($communesData);
        $results = [];
        $missing = [];

        foreach ($communesData as $commune) {
            $codeInsee = $commune['codeInseeCommune'];
            $nomCommune = $commune['nomCommune'];

            if (isset($cached[$codeInsee]) && !$cached[$codeInsee]->isExpired()) {
                $results[$codeInsee] = [
                    'nom' => $nomCommune,
                    'code_insee' => $codeInsee,
                    'coordinates' => $cached[$codeInsee]->getFormattedCoordinates(),
                    'source' => 'cache'
                ];
                $this->logger->debug("📦 Cache hit: {$nomCommune}");
            } else {
                $missing[] = $commune;
            }
        }

        $this->logger->info("📊 Cache stats: " . count($results) . " hits, " . count($missing) . " misses");

        // 2. Récupérer les manquantes via API (avec limite pour éviter timeout)
        $apiLimit = 50; // Limite pour éviter les timeouts
        $apiCalls = 0;

        foreach ($missing as $commune) {
            if ($apiCalls >= $apiLimit) {
                $this->logger->warning("⚠️ Limite API atteinte ({$apiLimit}), arrêt des récupérations");
                break;
            }

            $codeInsee = $commune['codeInseeCommune'];
            $nomCommune = $commune['nomCommune'];
            
            $geometry = $this->fetchFromApi($codeInsee, $nomCommune);
            
            if ($geometry) {
                $this->cacheGeometry($codeInsee, $nomCommune, $geometry, 'geo.api.gouv.fr');
                $results[$codeInsee] = [
                    'nom' => $nomCommune,
                    'code_insee' => $codeInsee,
                    'coordinates' => $geometry['coordinates'] ?? [],
                    'source' => 'api'
                ];
                $this->logger->info("✅ API success: {$nomCommune}");
            } else {
                $this->cacheError($codeInsee, $nomCommune, "Échec API");
                $this->logger->warning("❌ API failure: {$nomCommune}");
            }
            
            $apiCalls++;
            
            // Pause pour éviter de surcharger l'API
            usleep(50000); // 50ms
        }

        return array_values($results);
    }

    /**
     * Met en cache une géométrie de commune
     */
    public function cacheGeometry(string $codeInsee, string $nomCommune, array $geometry, string $source): void
    {
        try {
            // Chercher une entrée existante
            $cached = $this->repository->findOneBy(['codeInsee' => $codeInsee]);
            
            if (!$cached) {
                $cached = new CommuneGeometryCache();
                $cached->setCodeInsee($codeInsee);
            }

            $cached->setNomCommune($nomCommune)
                   ->setGeometryData($geometry)
                   ->setSource($source)
                   ->setIsValid(true)
                   ->setErrorMessage(null)
                   ->setLastUpdated(new \DateTimeImmutable());

            $this->entityManager->persist($cached);
            $this->entityManager->flush();

            $this->logger->debug("💾 Géométrie mise en cache: {$nomCommune} ({$cached->getPointsCount()} points)");
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur cache: " . $e->getMessage());
        }
    }

    /**
     * Met en cache une erreur de récupération
     */
    public function cacheError(string $codeInsee, string $nomCommune, string $errorMessage): void
    {
        try {
            $cached = $this->repository->findOneBy(['codeInsee' => $codeInsee]);
            
            if (!$cached) {
                $cached = new CommuneGeometryCache();
                $cached->setCodeInsee($codeInsee);
            }

            $cached->setNomCommune($nomCommune)
                   ->setIsValid(false)
                   ->setErrorMessage($errorMessage)
                   ->setLastUpdated(new \DateTimeImmutable());

            $this->entityManager->persist($cached);
            $this->entityManager->flush();

            $this->logger->debug("❌ Erreur mise en cache: {$nomCommune} - {$errorMessage}");
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur cache erreur: " . $e->getMessage());
        }
    }

    /**
     * Récupère la géométrie depuis l'API geo.api.gouv.fr
     */
    private function fetchFromApi(string $codeInsee, string $nomCommune): ?array
    {
        try {
            $url = "https://geo.api.gouv.fr/communes/{$codeInsee}?geometry=contour&format=geojson";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET',
                    'header' => 'User-Agent: TechnoProd-ERP/1.0'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (!isset($data['geometry'])) {
                return null;
            }

            return $this->extractBoundariesFromGeoJSON($data['geometry']);
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur API pour {$codeInsee}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrait les coordonnées de frontière depuis un GeoJSON
     */
    private function extractBoundariesFromGeoJSON(array $geometry): ?array
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
                foreach ($geometry['coordinates'] as $polygon) {
                    $polygonCoords = $this->extractPolygonCoordinates($polygon);
                    $coordinates = array_merge($coordinates, $polygonCoords);
                }
                break;
                
            default:
                $this->logger->warning("❌ Type de géométrie non supporté: {$geometry['type']}");
                return null;
        }

        return ['coordinates' => $coordinates];
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
     * Statistiques du cache
     */
    public function getCacheStats(): array
    {
        return $this->repository->getCacheStats();
    }

    /**
     * Nettoie le cache (supprime les entrées expirées)
     */
    public function cleanCache(): int
    {
        $this->logger->info("🧹 Nettoyage du cache des géométries");
        return $this->repository->cleanExpiredEntries();
    }
}