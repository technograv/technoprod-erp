<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour récupérer les vraies frontières géographiques des EPCI
 * depuis l'API officielle française geo.api.gouv.fr
 */
class EpciBoundariesService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Calcule des frontières précises pour un EPCI en utilisant les vraies géométries des communes
     * 
     * @param string $codeEpci Code SIREN de l'EPCI
     * @param array $communesData Données des communes avec codes INSEE
     * @return array|null Tableau de coordonnées [lat, lng] ou null si erreur
     */
    public function getEpciBoundaries(string $codeEpci, array $communesData = []): ?array
    {
        try {
            $this->logger->info("🌍 Calcul frontières réelles EPCI {$codeEpci} avec géométries officielles");

            if (empty($communesData)) {
                $this->logger->warning("❌ Pas de données de communes pour EPCI {$codeEpci}");
                return null;
            }

            // Récupérer les vraies géométries des communes depuis l'API officielle
            $communeGeometries = $this->fetchCommuneGeometries($communesData);
            
            if (empty($communeGeometries)) {
                $this->logger->warning("❌ Aucune géométrie récupérée pour EPCI {$codeEpci}");
                // Fallback vers l'ancien système avec coordonnées centrales
                $coordinates = array_map(fn($commune) => [
                    'lat' => (float) $commune['latitude'], 
                    'lng' => (float) $commune['longitude']
                ], $communesData);
                return $this->calculateAlphaShape($coordinates);
            }

            // Fusionner les géométries des communes pour créer la frontière EPCI
            return $this->mergePolygons($communeGeometries);

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur calcul frontières EPCI {$codeEpci}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calcule un contour concave réaliste en utilisant l'algorithme k-nearest neighbors
     * 
     * @param array $points Tableau de coordonnées [lat, lng]
     * @param int $k Nombre de voisins à considérer (plus petit = plus détaillé)
     * @return array Coordonnées du contour
     */
    private function calculateAlphaShape(array $points, float $alpha = 0.01): array
    {
        if (count($points) < 3) {
            return $points;
        }

        // Utiliser un algorithme de concave hull plus sophistiqué
        return $this->calculateConcaveHull($points, max(3, min(15, count($points) / 4)));
    }

    /**
     * Algorithme de concave hull utilisant k-nearest neighbors
     * Beaucoup plus précis pour les formes géographiques complexes
     */
    private function calculateConcaveHull(array $points, int $k = 3): array
    {
        if (count($points) < 3) {
            return $points;
        }

        // Étape 1: Calculer l'enveloppe convexe comme référence
        $convexHull = $this->calculateConvexHull($points);
        
        if (count($points) <= count($convexHull) + 2) {
            // Pas assez de points pour améliorer
            return $convexHull;
        }

        // Étape 2: Utiliser l'algorithme k-nearest neighbors pour créer un contour concave
        $hull = $this->kNearestNeighborsConcaveHull($points, $k);
        
        // Étape 3: Vérifier que le contour est valide
        if (count($hull) < 3 || !$this->isValidPolygon($hull)) {
            // Retourner au convex hull si le concave hull échoue
            return $convexHull;
        }
        
        return $hull;
    }

    /**
     * Implémentation de l'algorithme k-nearest neighbors pour concave hull
     */
    private function kNearestNeighborsConcaveHull(array $points, int $k): array
    {
        $hull = [];
        $remaining = $points;
        
        // Commencer par le point le plus à l'ouest
        $start = $this->findWestmostPoint($remaining);
        $current = $start;
        $hull[] = $current;
        $remaining = array_filter($remaining, fn($p) => $p !== $current);
        
        $previous = null;
        $iterations = 0;
        $maxIterations = count($points) * 2; // Protection contre boucles infinies
        
        while (!empty($remaining) && $iterations < $maxIterations) {
            $iterations++;
            
            // Trouver les k plus proches voisins
            $neighbors = $this->findKNearestNeighbors($current, $remaining, $k);
            
            if (empty($neighbors)) {
                break;
            }
            
            // Choisir le meilleur candidat parmi les voisins
            $next = $this->selectBestCandidate($current, $previous, $neighbors, $hull);
            
            if ($next === null) {
                break;
            }
            
            // Vérifier si on peut fermer le polygone
            if (count($hull) > 3 && $this->calculateDistance($next, $start) < $this->calculateDistance($next, $current) * 0.3) {
                break; // Fermer le polygone
            }
            
            $hull[] = $next;
            $previous = $current;
            $current = $next;
            $remaining = array_filter($remaining, fn($p) => $p !== $current);
        }
        
        return $hull;
    }

    /**
     * Trouve le point le plus à l'ouest (longitude minimale)
     */
    private function findWestmostPoint(array $points): array
    {
        return array_reduce($points, function($min, $point) {
            return ($min === null || $point['lng'] < $min['lng']) ? $point : $min;
        });
    }

    /**
     * Trouve les k plus proches voisins d'un point
     */
    private function findKNearestNeighbors(array $point, array $candidates, int $k): array
    {
        if (empty($candidates)) {
            return [];
        }
        
        $distances = [];
        foreach ($candidates as $i => $candidate) {
            $distances[$i] = $this->calculateDistance($point, $candidate);
        }
        
        asort($distances);
        $nearestIndices = array_slice(array_keys($distances), 0, $k, true);
        
        return array_intersect_key($candidates, array_flip($nearestIndices));
    }

    /**
     * Sélectionne le meilleur candidat parmi les voisins
     */
    private function selectBestCandidate(array $current, ?array $previous, array $candidates, array $hull): ?array
    {
        if (empty($candidates)) {
            return null;
        }
        
        $best = null;
        $bestScore = -PHP_FLOAT_MAX;
        
        foreach ($candidates as $candidate) {
            $score = $this->scoreCandidate($current, $previous, $candidate, $hull);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }
        
        return $best;
    }

    /**
     * Calcule un score pour un candidat basé sur plusieurs critères
     */
    private function scoreCandidate(array $current, ?array $previous, array $candidate, array $hull): float
    {
        $score = 0;
        
        // Critère 1: Préférer les points proches
        $distance = $this->calculateDistance($current, $candidate);
        $score += 100 / (1 + $distance * 1000); // Plus proche = meilleur score
        
        // Critère 2: Éviter les angles trop aigus
        if ($previous !== null) {
            $angle = $this->calculateAngle($previous, $current, $candidate);
            $score += sin($angle) * 50; // Préférer les angles proches de 90°
        }
        
        // Critère 3: Éviter les auto-intersections
        if (count($hull) > 2) {
            $wouldIntersect = $this->wouldCreateIntersection($current, $candidate, $hull);
            if ($wouldIntersect) {
                $score -= 200; // Forte pénalité pour les intersections
            }
        }
        
        // Critère 4: Préférer rester à l'extérieur du polygone en formation
        if (count($hull) > 2) {
            $isInside = $this->isPointInsidePolygon($candidate, $hull);
            if ($isInside) {
                $score -= 100; // Pénalité pour les points intérieurs
            }
        }
        
        return $score;
    }

    /**
     * Calcule l'angle entre trois points
     */
    private function calculateAngle(array $p1, array $p2, array $p3): float
    {
        $v1 = ['x' => $p1['lng'] - $p2['lng'], 'y' => $p1['lat'] - $p2['lat']];
        $v2 = ['x' => $p3['lng'] - $p2['lng'], 'y' => $p3['lat'] - $p2['lat']];
        
        $dot = $v1['x'] * $v2['x'] + $v1['y'] * $v2['y'];
        $mag1 = sqrt($v1['x'] * $v1['x'] + $v1['y'] * $v1['y']);
        $mag2 = sqrt($v2['x'] * $v2['x'] + $v2['y'] * $v2['y']);
        
        if ($mag1 == 0 || $mag2 == 0) {
            return 0;
        }
        
        $cos = $dot / ($mag1 * $mag2);
        $cos = max(-1, min(1, $cos)); // Clamp entre -1 et 1
        
        return acos($cos);
    }

    /**
     * Vérifie si ajouter un point créerait une intersection
     */
    private function wouldCreateIntersection(array $from, array $to, array $hull): bool
    {
        if (count($hull) < 3) {
            return false;
        }
        
        // Vérifier intersection avec les arêtes existantes du hull
        for ($i = 0; $i < count($hull) - 1; $i++) {
            if ($this->linesIntersect($from, $to, $hull[$i], $hull[$i + 1])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Teste si deux lignes se croisent
     */
    private function linesIntersect(array $p1, array $p2, array $p3, array $p4): bool
    {
        $d1 = $this->orientation($p1, $p2, $p3);
        $d2 = $this->orientation($p1, $p2, $p4);
        $d3 = $this->orientation($p3, $p4, $p1);
        $d4 = $this->orientation($p3, $p4, $p2);
        
        return ($d1 != $d2 && $d3 != $d4);
    }

    /**
     * Calcule l'orientation de trois points ordonnés
     */
    private function orientation(array $p1, array $p2, array $p3): int
    {
        $val = ($p2['lat'] - $p1['lat']) * ($p3['lng'] - $p2['lng']) - 
               ($p2['lng'] - $p1['lng']) * ($p3['lat'] - $p2['lat']);
        
        if (abs($val) < 1e-10) return 0; // Colinéaires
        return ($val > 0) ? 1 : 2; // Sens horaire ou anti-horaire
    }

    /**
     * Teste si un point est à l'intérieur d'un polygone (ray casting)
     */
    private function isPointInsidePolygon(array $point, array $polygon): bool
    {
        $x = $point['lng'];
        $y = $point['lat'];
        $n = count($polygon);
        $inside = false;
        
        $j = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $xi = $polygon[$i]['lng'];
            $yi = $polygon[$i]['lat'];
            $xj = $polygon[$j]['lng'];
            $yj = $polygon[$j]['lat'];
            
            if ((($yi > $y) != ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
            $j = $i;
        }
        
        return $inside;
    }

    /**
     * Vérifie qu'un polygone est valide (pas d'auto-intersection)
     */
    private function isValidPolygon(array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }
        
        // Vérifier les auto-intersections
        $n = count($polygon);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 2; $j < $n; $j++) {
                if ($j == $n - 1 && $i == 0) continue; // Skip adjacent edges
                
                $p1 = $polygon[$i];
                $p2 = $polygon[($i + 1) % $n];
                $p3 = $polygon[$j];
                $p4 = $polygon[($j + 1) % $n];
                
                if ($this->linesIntersect($p1, $p2, $p3, $p4)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Calcule l'enveloppe convexe des points
     */
    private function calculateConvexHull(array $points): array
    {
        if (count($points) < 3) {
            return $points;
        }

        // Algorithme de Graham Scan
        $coords = array_map(function($p) {
            return ['x' => $p['lng'], 'y' => $p['lat'], 'original' => $p];
        }, $points);

        // Trouver le point le plus bas
        $bottom = $coords[0];
        foreach ($coords as $coord) {
            if ($coord['y'] < $bottom['y'] || ($coord['y'] === $bottom['y'] && $coord['x'] < $bottom['x'])) {
                $bottom = $coord;
            }
        }

        // Trier par angle polaire
        $otherCoords = array_filter($coords, fn($c) => $c !== $bottom);
        usort($otherCoords, function($a, $b) use ($bottom) {
            $angleA = atan2($a['y'] - $bottom['y'], $a['x'] - $bottom['x']);
            $angleB = atan2($b['y'] - $bottom['y'], $b['x'] - $bottom['x']);
            return $angleA <=> $angleB;
        });

        $hull = [$bottom];
        
        foreach ($otherCoords as $coord) {
            while (count($hull) > 1) {
                $p1 = $hull[count($hull) - 2];
                $p2 = $hull[count($hull) - 1];
                $cross = ($p2['x'] - $p1['x']) * ($coord['y'] - $p1['y']) - ($p2['y'] - $p1['y']) * ($coord['x'] - $p1['x']);
                if ($cross <= 0) {
                    array_pop($hull);
                } else {
                    break;
                }
            }
            $hull[] = $coord;
        }

        return array_map(fn($h) => $h['original'], $hull);
    }

    /**
     * Ajoute des points concaves pour améliorer la forme
     */
    private function addConcavePoints(array $convexHull, array $allPoints, float $alpha): array
    {
        $improvedBoundary = [];
        
        for ($i = 0; $i < count($convexHull); $i++) {
            $current = $convexHull[$i];
            $next = $convexHull[($i + 1) % count($convexHull)];
            
            $improvedBoundary[] = $current;
            
            // Chercher des points entre current et next qui pourraient améliorer la forme
            $edgePoints = $this->findPointsNearEdge($current, $next, $allPoints, $alpha);
            
            foreach ($edgePoints as $point) {
                $improvedBoundary[] = $point;
            }
        }
        
        return $improvedBoundary;
    }

    /**
     * Trouve les points proches d'une arête pour créer des concavités
     */
    private function findPointsNearEdge(array $start, array $end, array $allPoints, float $alpha): array
    {
        $edgePoints = [];
        $maxDistance = $alpha * $this->calculateDistance($start, $end);
        
        foreach ($allPoints as $point) {
            $distanceToEdge = $this->pointToLineDistance($point, $start, $end);
            
            if ($distanceToEdge < $maxDistance && $distanceToEdge > 0.001) {
                // Vérifier que le point est "à l'intérieur" de l'arête
                if ($this->isPointBetweenProjections($point, $start, $end)) {
                    $edgePoints[] = $point;
                }
            }
        }
        
        // Trier les points par leur position le long de l'arête
        usort($edgePoints, function($a, $b) use ($start, $end) {
            $projA = $this->projectPointOnLine($a, $start, $end);
            $projB = $this->projectPointOnLine($b, $start, $end);
            return $this->calculateDistance($start, $projA) <=> $this->calculateDistance($start, $projB);
        });
        
        return $edgePoints;
    }

    /**
     * Calcule la distance entre deux points géographiques
     */
    private function calculateDistance(array $point1, array $point2): float
    {
        $latDiff = $point2['lat'] - $point1['lat'];
        $lngDiff = $point2['lng'] - $point1['lng'];
        return sqrt($latDiff * $latDiff + $lngDiff * $lngDiff);
    }

    /**
     * Récupère les vraies géométries des communes depuis l'API officielle française
     * 
     * @param array $communesData Données des communes avec codes INSEE
     * @return array Tableau des géométries par commune
     */
    private function fetchCommuneGeometries(array $communesData): array
    {
        $geometries = [];
        
        foreach ($communesData as $commune) {
            $codeInsee = $commune['codeInseeCommune'] ?? null;
            if (!$codeInsee) {
                continue;
            }
            
            try {
                $this->logger->info("🏘️ Récupération géométrie commune {$codeInsee}");
                
                // Appel API geo.api.gouv.fr pour la géométrie
                $response = $this->httpClient->request('GET', "https://geo.api.gouv.fr/communes/{$codeInsee}", [
                    'query' => [
                        'geometry' => 'contour',
                        'format' => 'geojson'
                    ],
                    'timeout' => 10
                ]);
                
                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    
                    if (isset($data['geometry'])) {
                        $boundaries = $this->extractBoundariesFromGeometry($data['geometry']);
                        if ($boundaries && count($boundaries) >= 3) {
                            $geometries[$codeInsee] = [
                                'nom' => $commune['nomCommune'] ?? "Commune {$codeInsee}",
                                'boundaries' => $boundaries,
                                'source' => 'api_officielle'
                            ];
                            $this->logger->info("✅ Géométrie récupérée pour {$codeInsee}: " . count($boundaries) . " points");
                        }
                    }
                } else {
                    $this->logger->warning("⚠️ API non disponible pour commune {$codeInsee}");
                }
                
                // Pause pour éviter de surcharger l'API
                usleep(100000); // 100ms
                
            } catch (\Exception $e) {
                $this->logger->error("❌ Erreur récupération géométrie {$codeInsee}: " . $e->getMessage());
            }
        }
        
        return $geometries;
    }
    
    /**
     * Fusionne les polygones des communes pour créer une frontière EPCI unifiée
     * 
     * @param array $communeGeometries Géométries des communes
     * @return array|null Frontière EPCI fusionnée
     */
    private function mergePolygons(array $communeGeometries): ?array
    {
        if (empty($communeGeometries)) {
            return null;
        }
        
        $this->logger->info("🔗 Fusion de " . count($communeGeometries) . " géométries communales");
        
        // Si une seule commune, retourner sa géométrie
        if (count($communeGeometries) === 1) {
            return array_values($communeGeometries)[0]['boundaries'];
        }
        
        // Fusionner toutes les frontières dans un seul tableau
        $allPoints = [];
        foreach ($communeGeometries as $geometry) {
            $allPoints = array_merge($allPoints, $geometry['boundaries']);
        }
        
        // Calculer l'enveloppe convexe de tous les points
        // Pour une vraie fusion de polygones, il faudrait une bibliothèque comme GEOS
        // Ici on utilise un algorithme simplifié
        $mergedBoundary = $this->calculateConvexHull($allPoints);
        
        // Améliorer avec des points concaves si possible
        $improvedBoundary = $this->addConcavePointsFromCommunes($mergedBoundary, $communeGeometries);
        
        $this->logger->info("✅ Fusion terminée: " . count($improvedBoundary) . " points de frontière");
        
        return $improvedBoundary;
    }
    
    /**
     * Améliore la frontière fusionnée en ajoutant des points concaves des communes
     */
    private function addConcavePointsFromCommunes(array $convexHull, array $communeGeometries): array
    {
        $improvedBoundary = $convexHull;
        
        // Pour chaque arête de l'enveloppe convexe
        for ($i = 0; $i < count($convexHull); $i++) {
            $current = $convexHull[$i];
            $next = $convexHull[($i + 1) % count($convexHull)];
            
            // Chercher des points des communes qui sont proches de cette arête
            $edgePoints = [];
            foreach ($communeGeometries as $geometry) {
                foreach ($geometry['boundaries'] as $point) {
                    $distance = $this->pointToLineDistance($point, $current, $next);
                    if ($distance < 0.005 && $this->isPointBetweenProjections($point, $current, $next)) {
                        $edgePoints[] = $point;
                    }
                }
            }
            
            // Trier les points le long de l'arête et les ajouter
            if (!empty($edgePoints)) {
                usort($edgePoints, function($a, $b) use ($current, $next) {
                    $projA = $this->projectPointOnLine($a, $current, $next);
                    $projB = $this->projectPointOnLine($b, $current, $next);
                    return $this->calculateDistance($current, $projA) <=> $this->calculateDistance($current, $projB);
                });
                
                // Insérer les points dans la frontière améliorée
                array_splice($improvedBoundary, $i + 1, 0, $edgePoints);
                $i += count($edgePoints); // Ajuster l'index
            }
        }
        
        return $improvedBoundary;
    }

    /**
     * Calcule la distance d'un point à une ligne
     */
    private function pointToLineDistance(array $point, array $lineStart, array $lineEnd): float
    {
        $A = $lineEnd['lat'] - $lineStart['lat'];
        $B = $lineStart['lng'] - $lineEnd['lng'];
        $C = $lineEnd['lng'] * $lineStart['lat'] - $lineStart['lng'] * $lineEnd['lat'];
        
        $denominator = sqrt($A * $A + $B * $B);
        
        // Éviter la division par zéro (points identiques)
        if ($denominator < 1e-10) {
            return $this->calculateDistance($point, $lineStart);
        }
        
        return abs($A * $point['lng'] + $B * $point['lat'] + $C) / $denominator;
    }

    /**
     * Projette un point sur une ligne
     */
    private function projectPointOnLine(array $point, array $lineStart, array $lineEnd): array
    {
        $A = $point['lng'] - $lineStart['lng'];
        $B = $point['lat'] - $lineStart['lat'];
        $C = $lineEnd['lng'] - $lineStart['lng'];
        $D = $lineEnd['lat'] - $lineStart['lat'];
        
        $dot = $A * $C + $B * $D;
        $lenSq = $C * $C + $D * $D;
        
        if ($lenSq == 0) return $lineStart;
        
        $param = $dot / $lenSq;
        
        return [
            'lng' => $lineStart['lng'] + $param * $C,
            'lat' => $lineStart['lat'] + $param * $D
        ];
    }

    /**
     * Vérifie si un point est entre les projections des extrémités d'une ligne
     */
    private function isPointBetweenProjections(array $point, array $lineStart, array $lineEnd): bool
    {
        $projection = $this->projectPointOnLine($point, $lineStart, $lineEnd);
        
        $distanceToStart = $this->calculateDistance($lineStart, $projection);
        $distanceToEnd = $this->calculateDistance($lineEnd, $projection);
        $lineLength = $this->calculateDistance($lineStart, $lineEnd);
        
        return ($distanceToStart + $distanceToEnd) <= ($lineLength * 1.1); // Tolérance de 10%
    }

    /**
     * Extrait les coordonnées de frontière depuis la géométrie GeoJSON
     * 
     * @param array $geometry Objet geometry au format GeoJSON
     * @return array|null Tableau de coordonnées [lat, lng]
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
                foreach ($geometry['coordinates'] as $polygon) {
                    $polygonCoords = $this->extractPolygonCoordinates($polygon);
                    $coordinates = array_merge($coordinates, $polygonCoords);
                }
                break;
                
            default:
                $this->logger->warning("❌ Type de géométrie non supporté: {$geometry['type']}");
                return null;
        }

        return $coordinates;
    }

    /**
     * Extrait les coordonnées d'un polygone GeoJSON
     * 
     * @param array $polygonData Coordonnées du polygone
     * @return array Tableau de coordonnées [lat, lng]
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
     * Calcule le centre géographique (centroïde) d'un ensemble de coordonnées
     * 
     * @param array $coordinates Tableau de coordonnées [lat, lng]
     * @return array|null Centre [lat, lng] ou null si vide
     */
    public function calculateCentroid(array $coordinates): ?array
    {
        if (empty($coordinates)) {
            return null;
        }

        $latSum = 0;
        $lngSum = 0;
        $count = count($coordinates);

        foreach ($coordinates as $coord) {
            $latSum += $coord['lat'];
            $lngSum += $coord['lng'];
        }

        return [
            'lat' => $latSum / $count,
            'lng' => $lngSum / $count
        ];
    }

    /**
     * Vérifie si les contours sont valides (au moins 3 points)
     * 
     * @param array $coordinates Coordonnées à vérifier
     * @return bool True si valides
     */
    public function areBoundariesValid(array $coordinates): bool
    {
        return is_array($coordinates) && count($coordinates) >= 3;
    }

    /**
     * Simplifie les contours en réduisant le nombre de points
     * Utile pour améliorer les performances d'affichage
     * 
     * @param array $coordinates Coordonnées originales
     * @param float $tolerance Tolérance de simplification (en degrés)
     * @return array Coordonnées simplifiées
     */
    public function simplifyBoundaries(array $coordinates, float $tolerance = 0.001): array
    {
        if (count($coordinates) <= 3) {
            return $coordinates;
        }

        // Algorithme de simplification Douglas-Peucker simplifié
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
     * Calcule la distance perpendiculaire approximative d'un point à une ligne
     * 
     * @param array $point Point à tester
     * @param array $lineStart Début de la ligne
     * @param array $lineEnd Fin de la ligne
     * @return float Distance approximative
     */
    private function perpendicularDistance(array $point, array $lineStart, array $lineEnd): float
    {
        // Approximation simple pour les coordonnées géographiques
        $A = $lineEnd['lat'] - $lineStart['lat'];
        $B = $lineStart['lng'] - $lineEnd['lng'];
        $C = $lineEnd['lng'] * $lineStart['lat'] - $lineStart['lng'] * $lineEnd['lat'];
        
        $denominator = sqrt($A * $A + $B * $B);
        
        // Éviter la division par zéro (points identiques)
        if ($denominator < 1e-10) {
            return $this->calculateDistance($point, $lineStart);
        }
        
        $distance = abs($A * $point['lng'] + $B * $point['lat'] + $C) / $denominator;
        
        return $distance;
    }
}