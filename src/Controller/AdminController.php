<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Societe;
use App\Entity\FormeJuridique;
use App\Entity\Secteur;
use App\Entity\Produit;
use App\Entity\ModeReglement;
use App\Entity\ModePaiement;
use App\Entity\Banque;
use App\Entity\Tag;
use App\Entity\TauxTVA;
use App\Entity\Unite;
use App\Entity\Civilite;
use App\Entity\FraisPort;
use App\Entity\Transporteur;
use App\Entity\MethodeExpedition;
use App\Entity\ModeleDocument;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use App\Entity\AttributionSecteur;
use App\Entity\GroupeUtilisateur;
use App\Service\TenantService;
use App\Service\CommuneGeometryCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommuneGeometryCacheService $cacheService
    ) {}
    #[Route('/', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $entityManager, TenantService $tenantService): Response
    {
        // Vérifier les permissions d'administration
        $this->denyAccessUnlessGranted('ADMIN_ACCESS');
        
        // Récupération de la société courante
        $currentSociete = $tenantService->getCurrentSociete();
        $isSocieteMere = $currentSociete ? $currentSociete->isMere() : true;
        
        // Statistiques générales pour le dashboard admin
        $stats = [
            'users' => $entityManager->getRepository(User::class)->count([]),
            'formes_juridiques' => $entityManager->getRepository(FormeJuridique::class)->count([]),
            'users_actifs' => $entityManager->getRepository(User::class)->count(['isActive' => true]),
            'admins' => $entityManager->getConnection()->fetchOne(
                'SELECT COUNT(id) FROM "user" WHERE CAST(roles AS TEXT) LIKE ?',
                ['%ROLE_ADMIN%']
            ),
            'secteurs' => $entityManager->getRepository(Secteur::class)->count([]),
            'zones' => 0, // Zones obsolètes supprimées
            'produits' => $entityManager->getRepository(Produit::class)->count([]),
            'modes_reglement' => $entityManager->getRepository(ModeReglement::class)->count([]),
            'modes_paiement' => $entityManager->getRepository(ModePaiement::class)->count([]),
            'banques' => $entityManager->getRepository(Banque::class)->count([]),
            'tags' => $entityManager->getRepository(Tag::class)->count([]),
            'taux_tva' => $entityManager->getRepository(TauxTVA::class)->count([]),
            'unites' => $entityManager->getRepository(Unite::class)->count([]),
            'civilites' => $entityManager->getRepository(Civilite::class)->count([]),
            'frais_port' => $entityManager->getRepository(FraisPort::class)->count([]),
            'transporteurs' => $entityManager->getRepository(Transporteur::class)->count([]),
            'methodes_expedition' => $entityManager->getRepository(MethodeExpedition::class)->count([]),
            'modeles_document' => $entityManager->getRepository(ModeleDocument::class)->count([]),
            // Nouvelles entités système secteurs
            'divisions_administratives' => $entityManager->getRepository(DivisionAdministrative::class)->count(['actif' => true]),
            'types_secteur' => $entityManager->getRepository(TypeSecteur::class)->count(['actif' => true]),
            'attributions_secteur' => $entityManager->getRepository(AttributionSecteur::class)->count([]),
            // Statistiques sociétés
            'societes_meres' => $entityManager->getRepository(Societe::class)->count(['type' => 'mere']),
            'societes_filles' => $entityManager->getRepository(Societe::class)->count(['type' => 'fille']),
            // Statistiques groupes utilisateurs
            'groupes_utilisateurs' => $entityManager->getRepository(GroupeUtilisateur::class)->count([]),
            'groupes_actifs' => $entityManager->getRepository(GroupeUtilisateur::class)->count(['actif' => true]),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'google_maps_api_key' => $this->getParameter('google.maps.api.key'),
            'secteurs' => $entityManager->getRepository(Secteur::class)->findBy([], ['nomSecteur' => 'ASC']),
            'current_societe' => $currentSociete,
            'is_societe_mere' => $isSocieteMere,
        ]);
    }

    // ================================
    // FONCTIONS DEBUG TEMPORAIRES
    // ================================

    #[Route('/debug/secteurs', name: 'app_admin_debug_secteurs', methods: ['GET'])]
    public function debugSecteurs(EntityManagerInterface $entityManager): JsonResponse
    {
        $secteurs = $entityManager->getRepository(Secteur::class)->findAll();
        $data = [];
        
        foreach ($secteurs as $secteur) {
            $data[] = [
                'id' => $secteur->getId(),
                'nom' => $secteur->getNomSecteur(),
                'attributions_count' => $secteur->getAttributions()->count()
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/debug/attributions', name: 'app_admin_debug_attributions', methods: ['GET'])]
    public function debugAttributions(EntityManagerInterface $entityManager): Response
    {
        $attributions = $entityManager->getRepository(AttributionSecteur::class)->findAll();
        
        $data = [];
        foreach ($attributions as $attribution) {
            $data[] = [
                'id' => $attribution->getId(),
                'secteur' => $attribution->getSecteur()->getNomSecteur(),
                'division' => [
                    'code' => $attribution->getDivisionAdministrative()->getCode(),
                    'nom' => $attribution->getDivisionAdministrative()->getNom(),
                    'type' => $attribution->getDivisionAdministrative()->getType(),
                ]
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/debug-auth', name: 'app_admin_debug_auth', methods: ['GET'])]
    public function debugAuth(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'No user logged in']);
        }
        
        return $this->json([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'is_admin' => in_array('ROLE_ADMIN', $user->getRoles()),
            'has_admin_access' => $this->isGranted('ADMIN_ACCESS'),
        ]);
    }

    #[Route('/secteurs/all-geo-data', name: 'app_admin_secteurs_all_geo_data', methods: ['GET'])]
    public function getAllSecteursGeoData(): JsonResponse
    {
        error_log("🔍 DEBUG: getAllSecteursGeoData - APPROCHE hiérarchique restaurée du commit 88cdd1c - ADMINCONTROLLER");
        try {
            $secteurs = $this->entityManager->getRepository(Secteur::class)
                ->createQueryBuilder('s')
                ->where('s.isActive = true')
                ->orderBy('s.nomSecteur', 'ASC')
                ->getQuery()
                ->getResult();

            if (count($secteurs) === 0) {
                return $this->json(['success' => true, 'secteurs' => [], 'total' => 0]);
            }

            error_log("🎯 Secteurs actifs trouvés: " . count($secteurs));
            
            // ÉTAPE 1: Créer un mappage global commune → secteur selon priorité hiérarchique
            $communeVsSecteur = []; // codeInsee → secteurId
            
            // Collecter toutes les attributions par type
            $attributionsParType = [];
            foreach ($secteurs as $secteur) {
                foreach ($secteur->getAttributions() as $attribution) {
                    $type = $attribution->getTypeCritere();
                    if (!isset($attributionsParType[$type])) {
                        $attributionsParType[$type] = [];
                    }
                    $attributionsParType[$type][] = [
                        'secteur' => $secteur,
                        'attribution' => $attribution
                    ];
                }
            }
            
            // ÉTAPE 2: Traitement hiérarchique GLOBAL: communes → CP → EPCIs → départements → régions
            $ordreTraitement = ['commune', 'code_postal', 'epci', 'departement', 'region'];
            
            foreach ($ordreTraitement as $typeActuel) {
                if (!isset($attributionsParType[$typeActuel])) continue;
                
                error_log("🔄 Phase {$typeActuel}: " . count($attributionsParType[$typeActuel]) . " attributions");
                
                foreach ($attributionsParType[$typeActuel] as $data) {
                    $secteur = $data['secteur'];
                    $attribution = $data['attribution'];
                    $division = $attribution->getDivisionAdministrative();
                    
                    if (!$division) continue;
                    
                    // Récupérer toutes les communes de ce type
                    $communesDuType = $this->getCommunesPourType($typeActuel, $division, $this->entityManager);
                    
                    // Assigner chaque commune NON ENCORE ASSIGNÉE à ce secteur
                    $nouvellesCommunes = 0;
                    foreach ($communesDuType as $commune) {
                        $codeInsee = $commune['codeInseeCommune'];
                        if (!isset($communeVsSecteur[$codeInsee])) {
                            $communeVsSecteur[$codeInsee] = $secteur->getId();
                            $nouvellesCommunes++;
                        }
                    }
                    
                    if ($nouvellesCommunes > 0) {
                        error_log("📍 {$typeActuel} '{$attribution->getValeurCritere()}' → {$secteur->getNomSecteur()}: {$nouvellesCommunes} nouvelles communes");
                    }
                }
            }

            // ÉTAPE 3: Construire les données secteurs avec leurs communes assignées
            $secteursData = [];
            
            foreach ($secteurs as $secteur) {
                $secteurData = [
                    'id' => $secteur->getId(),
                    'nom' => $secteur->getNomSecteur(),
                    'couleur' => $secteur->getCouleurHex() ?: '#3498db',
                    'commercial' => $secteur->getCommercial() ? 
                        trim(($secteur->getCommercial()->getPrenom() ?: '') . ' ' . ($secteur->getCommercial()->getNom() ?: '')) : 
                        null,
                    'description' => $secteur->getDescription(),
                    'isActive' => $secteur->getIsActive(),
                    'attributions' => [],
                    'hasCoordinates' => false
                ];
                
                // Récupérer TOUTES les communes assignées à ce secteur
                $communesSecteur = [];
                foreach ($communeVsSecteur as $codeInsee => $secteurId) {
                    if ($secteurId === $secteur->getId()) {
                        $communesSecteur[] = [
                            'codeInseeCommune' => $codeInsee,
                            'nomCommune' => 'Commune ' . $codeInsee // Sera enrichi avec les vrais noms
                        ];
                    }
                }
                
                if (!empty($communesSecteur)) {
                    // Récupérer les géométries via le cache service
                    $communesAvecGeometries = $this->cacheService->getMultipleCommunesGeometry($communesSecteur);
                    
                    if (!empty($communesAvecGeometries)) {
                        // Créer UNE SEULE attribution "virtuelle" qui contient toutes les communes du secteur
                        $attributionGlobale = [
                            'id' => 'global_' . $secteur->getId(),
                            'type' => 'secteur_complet',
                            'valeur' => $secteur->getNomSecteur(),
                            'nom' => $secteur->getNomSecteur() . ' (toutes communes)',
                            'communes' => $communesAvecGeometries,
                            'boundary_type' => 'communes_reelles'
                        ];
                        
                        $secteurData['attributions'] = [$attributionGlobale];
                        $this->calculerBoundsSecteurHierarchique($secteurData, $secteur, $communesAvecGeometries);
                        
                        error_log("🗺️ Secteur {$secteur->getNomSecteur()}: " . count($communesAvecGeometries) . " communes avec géométries sur " . count($communesSecteur) . " assignées");
                    } else {
                        error_log("⚠️ Secteur {$secteur->getNomSecteur()}: aucune géométrie trouvée pour " . count($communesSecteur) . " communes assignées");
                    }
                }
                
                $secteursData[] = $secteurData;
            }
            
            error_log("🎯 Assignation globale terminée - " . count($communeVsSecteur) . " communes assignées");
            error_log("🎯 Secteurs data construits: " . count($secteursData));
            
            return $this->json([
                'success' => true,
                'secteurs' => $secteursData,
                'total' => count($secteursData),
                'debug' => [
                    'communes_assignees' => count($communeVsSecteur),
                    'methode' => 'hierarchique_global_restaure_admincontroller'
                ]
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getAllSecteursGeoData AdminController: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            return $this->json(['error' => 'Erreur lors de la récupération des secteurs: ' . $e->getMessage()], 500);
        }
    }

    private function getCommunesPourType(string $type, $division, EntityManagerInterface $entityManager): array
    {
        switch ($type) {
            case 'commune':
                // Une seule commune
                return [[
                    'codeInseeCommune' => $division->getCodeInseeCommune(),
                    'nomCommune' => $division->getNomCommune()
                ]];
                
            case 'code_postal':
                // Toutes les communes de ce code postal
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codePostal = :codePostal 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codePostal', $division->getCodePostal())
                ->getResult();
                return $communes;
                
            case 'epci':
                // Toutes les communes de cet EPCI
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :codeEpci 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeEpci', $division->getCodeEpci())
                ->getResult();
                return $communes;
                
            case 'departement':
                // Toutes les communes de ce département
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :codeDepartement 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeDepartement', $division->getCodeDepartement())
                ->getResult();
                return $communes;
                
            case 'region':
                // Toutes les communes de cette région
                $communes = $entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune 
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeRegion = :codeRegion 
                    AND d.codeInseeCommune IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('codeRegion', $division->getCodeRegion())
                ->getResult();
                return $communes;
                
            default:
                return [];
        }
    }

    /**
     * Calcule les bounds d'un secteur avec positionnement hiérarchique de la puce
     */
    private function calculerBoundsSecteurHierarchique(array &$secteurData, Secteur $secteur, array $communesAvecGeometries): void
    {
        $minLat = $minLng = PHP_FLOAT_MAX;
        $maxLat = $maxLng = PHP_FLOAT_MIN;
        $hasCoordinates = false;
        
        foreach ($communesAvecGeometries as $commune) {
            if (isset($commune['coordinates']) && is_array($commune['coordinates'])) {
                foreach ($commune['coordinates'] as $coord) {
                    if (isset($coord['lat']) && isset($coord['lng'])) {
                        $minLat = min($minLat, $coord['lat']);
                        $maxLat = max($maxLat, $coord['lat']);
                        $minLng = min($minLng, $coord['lng']);
                        $maxLng = max($maxLng, $coord['lng']);
                        $hasCoordinates = true;
                    }
                }
            }
        }
        
        if ($hasCoordinates) {
            // Mise à jour des bounds existants ou création
            if ($secteurData['hasCoordinates']) {
                // Etendre les bounds existants
                $currentBounds = $secteurData['bounds'];
                $minLat = min($minLat, $currentBounds['southwest']['lat']);
                $minLng = min($minLng, $currentBounds['southwest']['lng']);
                $maxLat = max($maxLat, $currentBounds['northeast']['lat']);
                $maxLng = max($maxLng, $currentBounds['northeast']['lng']);
            }
            
            $latMargin = ($maxLat - $minLat) * 0.1;
            $lngMargin = ($maxLng - $minLng) * 0.1;
            
            $secteurData['bounds'] = [
                'southwest' => [
                    'lat' => $minLat - $latMargin,
                    'lng' => $minLng - $lngMargin
                ],
                'northeast' => [
                    'lat' => $maxLat + $latMargin,
                    'lng' => $maxLng + $lngMargin
                ]
            ];
            
            // APPROCHE HIÉRARCHIQUE: EPCI > Code Postal > Commune
            $positionOptimale = $this->calculerPositionHierarchique($secteur, $communesAvecGeometries);
            
            if ($positionOptimale) {
                $secteurData['center'] = $positionOptimale['center'];
                error_log("🎯 Puce secteur {$secteurData['nom']} positionnée via {$positionOptimale['type']}: {$positionOptimale['entite']}");
            } else {
                // Fallback: centre géométrique global
                $secteurData['center'] = [
                    'lat' => ($minLat + $maxLat) / 2,
                    'lng' => ($minLng + $maxLng) / 2
                ];
                error_log("📍 Puce secteur {$secteurData['nom']} positionnée au centre géométrique (fallback)");
            }
            
            $secteurData['hasCoordinates'] = true;
        }
    }

    /**
     * Détermine le cluster principal de communes pour positionnement optimal de la puce
     */
    private function determinerClusterPrincipal(array $communesAvecGeometries): ?array
    {
        if (empty($communesAvecGeometries)) {
            return null;
        }

        // DEBUG: Analyser la structure complète des données
        error_log("🔍 DEBUG CLUSTERING - Nombre de communes: " . count($communesAvecGeometries));
        if (!empty($communesAvecGeometries)) {
            $firstCommune = $communesAvecGeometries[0];
            error_log("🔍 Structure première commune complète: " . json_encode($firstCommune, JSON_PRETTY_PRINT));
        }

        // Si une seule commune, retourner son centre
        if (count($communesAvecGeometries) === 1) {
            $commune = $communesAvecGeometries[0];
            if (isset($commune['coordinates']) && !empty($commune['coordinates'])) {
                $center = $this->calculerCentroideCommune($commune['coordinates']);
                error_log("🎯 Commune unique - Centre calculé: " . json_encode($center));
                return [
                    'center' => $center,
                    'communes' => 1,
                    'type' => 'commune_unique'
                ];
            }
            return null;
        }

        // APPROCHE OPTIMISÉE: Centre pondéré par taille des communes
        return $this->calculerCentreOptimise($communesAvecGeometries);
    }

    /**
     * Détecte les clusters de communes adjacentes
     */
    private function detecterClusters(array $communesAvecGeometries): array
    {
        $clusters = [];
        $communesTraitees = [];
        
        foreach ($communesAvecGeometries as $index => $commune) {
            if (in_array($index, $communesTraitees)) {
                continue;
            }
            
            // Nouveau cluster
            $cluster = [
                'communes' => [$commune],
                'indices' => [$index],
                'taille' => 1
            ];
            
            // Rechercher les communes adjacentes
            $this->ajouterCommunesAdjacentes($cluster, $communesAvecGeometries, $communesTraitees, $index);
            
            // Calculer le centre du cluster
            $cluster['center'] = $this->calculerCentroideCluster($cluster['communes']);
            
            $clusters[] = $cluster;
            
            // Marquer toutes les communes du cluster comme traitées
            foreach ($cluster['indices'] as $idx) {
                $communesTraitees[] = $idx;
            }
        }
        
        return $clusters;
    }

    /**
     * Ajoute récursivement les communes adjacentes au cluster
     */
    private function ajouterCommunesAdjacentes(array &$cluster, array $communesAvecGeometries, array &$communesTraitees, int $indexActuel): void
    {
        $communeActuelle = $communesAvecGeometries[$indexActuel];
        $seuilAdjacence = 0.02; // ~2km de distance approximative
        
        foreach ($communesAvecGeometries as $index => $autreCommune) {
            if (in_array($index, $communesTraitees) || in_array($index, $cluster['indices'])) {
                continue;
            }
            
            // Vérifier si les communes sont adjacentes
            if ($this->sontCommunesAdjacentes($communeActuelle, $autreCommune, $seuilAdjacence)) {
                $cluster['communes'][] = $autreCommune;
                $cluster['indices'][] = $index;
                $cluster['taille']++;
                
                // Recherche récursive des adjacentes de cette nouvelle commune
                $this->ajouterCommunesAdjacentes($cluster, $communesAvecGeometries, $communesTraitees, $index);
            }
        }
    }

    /**
     * Détermine si deux communes sont adjacentes basé sur la proximité géographique
     */
    private function sontCommunesAdjacentes(array $commune1, array $commune2, float $seuil): bool
    {
        $center1 = $this->calculerCentroideCommune($commune1['coordinates'] ?? []);
        $center2 = $this->calculerCentroideCommune($commune2['coordinates'] ?? []);
        
        if (!$center1 || !$center2) {
            return false;
        }
        
        // Distance euclidienne approximative (pour des distances courtes)
        $deltaLat = abs($center1['lat'] - $center2['lat']);
        $deltaLng = abs($center1['lng'] - $center2['lng']);
        $distance = sqrt($deltaLat * $deltaLat + $deltaLng * $deltaLng);
        
        return $distance <= $seuil;
    }

    /**
     * Calcule le centroïde géométrique réel d'une commune (algorithme de centroïde de polygone)
     */
    private function calculerCentroideCommune(array $coordinates): ?array
    {
        if (empty($coordinates)) {
            return null;
        }
        
        // Algorithme de centroïde de polygone basé sur la formule mathématique
        // pour calculer le vrai centre géométrique et non la moyenne des points de contour
        
        $area = 0;
        $centroidLat = 0;
        $centroidLng = 0;
        $n = count($coordinates);
        
        if ($n < 3) {
            // Si moins de 3 points, utiliser la moyenne simple
            $totalLat = $totalLng = 0;
            $count = 0;
            
            foreach ($coordinates as $coord) {
                if (isset($coord['lat']) && isset($coord['lng'])) {
                    $totalLat += $coord['lat'];
                    $totalLng += $coord['lng'];
                    $count++;
                }
            }
            
            return $count > 0 ? [
                'lat' => $totalLat / $count,
                'lng' => $totalLng / $count
            ] : null;
        }
        
        // Algorithme du centroïde de polygone (Shoelace formula)
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            
            if (!isset($coordinates[$i]['lat']) || !isset($coordinates[$i]['lng']) ||
                !isset($coordinates[$j]['lat']) || !isset($coordinates[$j]['lng'])) {
                continue;
            }
            
            $xi = $coordinates[$i]['lng'];
            $yi = $coordinates[$i]['lat'];
            $xj = $coordinates[$j]['lng'];
            $yj = $coordinates[$j]['lat'];
            
            $a = $xi * $yj - $xj * $yi;
            $area += $a;
            $centroidLat += ($yi + $yj) * $a;
            $centroidLng += ($xi + $xj) * $a;
        }
        
        $area *= 0.5;
        
        if (abs($area) < 1e-10) {
            // Zone trop petite, utiliser la moyenne simple
            $totalLat = $totalLng = 0;
            $count = 0;
            
            foreach ($coordinates as $coord) {
                if (isset($coord['lat']) && isset($coord['lng'])) {
                    $totalLat += $coord['lat'];
                    $totalLng += $coord['lng'];
                    $count++;
                }
            }
            
            return $count > 0 ? [
                'lat' => $totalLat / $count,
                'lng' => $totalLng / $count
            ] : null;
        }
        
        $centroidLat /= (6.0 * $area);
        $centroidLng /= (6.0 * $area);
        
        return [
            'lat' => $centroidLat,
            'lng' => $centroidLng
        ];
    }

    /**
     * Calcule le centroïde d'un cluster de communes
     */
    private function calculerCentroideCluster(array $communes): array
    {
        $totalLat = $totalLng = 0;
        $totalCommunes = 0;
        
        foreach ($communes as $commune) {
            $centroideCommune = $this->calculerCentroideCommune($commune['coordinates'] ?? []);
            if ($centroideCommune) {
                $totalLat += $centroideCommune['lat'];
                $totalLng += $centroideCommune['lng'];
                $totalCommunes++;
            }
        }
        
        if ($totalCommunes === 0) {
            // Fallback: centre France
            return ['lat' => 46.603354, 'lng' => 1.888334];
        }
        
        return [
            'lat' => $totalLat / $totalCommunes,
            'lng' => $totalLng / $totalCommunes
        ];
    }

    /**
     * Calcule le centre optimisé d'un secteur multi-zones
     * Solution robuste: bounding box center + validation géographique
     */
    private function calculerCentreOptimise(array $communesAvecGeometries): ?array
    {
        if (empty($communesAvecGeometries)) {
            return null;
        }

        error_log("🔍 Calcul centre optimisé pour " . count($communesAvecGeometries) . " communes");

        // SOLUTION SIMPLE ET ROBUSTE: Commune la plus centrale
        $communeCentrale = $this->trouverCommuneCentrale($communesAvecGeometries);
        
        if ($communeCentrale) {
            return $communeCentrale;
        }
        
        // FALLBACK: Centre pondéré par toutes les communes
        return $this->calculerCentrePondere($communesAvecGeometries);
    }

    /**
     * Trouve la commune la plus centrale du secteur (approche simple et robuste)
     */
    private function trouverCommuneCentrale(array $communesAvecGeometries): ?array
    {
        if (empty($communesAvecGeometries)) {
            return null;
        }

        try {
            // ÉTAPE 1: Centre géographique global du secteur
            $centreGlobal = $this->calculerCentreGeographiqueGlobal($communesAvecGeometries);
            
            // ÉTAPE 2: Trouver la commune la plus proche du centre global
            $communeOptimale = null;
            $distanceMinimale = PHP_FLOAT_MAX;
            
            foreach ($communesAvecGeometries as $commune) {
                if (!isset($commune['coordinates']) || empty($commune['coordinates'])) {
                    continue;
                }
                
                $bbox = $this->calculerBoundingBox($commune['coordinates']);
                if (!$bbox) {
                    continue;
                }
                
                $centreCommune = [
                    'lat' => ($bbox['minLat'] + $bbox['maxLat']) / 2,
                    'lng' => ($bbox['minLng'] + $bbox['maxLng']) / 2
                ];
                
                $distance = $this->calculerDistance($centreCommune, $centreGlobal);
                
                if ($distance < $distanceMinimale) {
                    $distanceMinimale = $distance;
                    $communeOptimale = $commune;
                }
            }
            
            if ($communeOptimale) {
                $bbox = $this->calculerBoundingBox($communeOptimale['coordinates']);
                if ($bbox) {
                    $centre = [
                        'lat' => ($bbox['minLat'] + $bbox['maxLat']) / 2,
                        'lng' => ($bbox['minLng'] + $bbox['maxLng']) / 2
                    ];
                    
                    $communeId = $communeOptimale['code_insee'] ?? $communeOptimale['nom'] ?? 'inconnu';
                    error_log("🎯 Commune centrale sélectionnée: {$communeId} (distance: " . number_format($distanceMinimale, 4) . ")");
                    
                    return [
                        'center' => $centre,
                        'communes' => count($communesAvecGeometries),
                        'type' => 'commune_centrale'
                    ];
                }
            }
            
        } catch (\Exception $e) {
            error_log("❌ Erreur calcul commune centrale: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Calcule la bounding box d'une commune
     */
    private function calculerBoundingBox(array $coordinates): ?array
    {
        if (empty($coordinates)) {
            return null;
        }
        
        $minLat = $minLng = PHP_FLOAT_MAX;
        $maxLat = $maxLng = PHP_FLOAT_MIN;
        
        foreach ($coordinates as $coord) {
            if (!isset($coord['lat']) || !isset($coord['lng'])) {
                continue;
            }
            
            $minLat = min($minLat, $coord['lat']);
            $maxLat = max($maxLat, $coord['lat']);
            $minLng = min($minLng, $coord['lng']);
            $maxLng = max($maxLng, $coord['lng']);
        }
        
        if ($minLat === PHP_FLOAT_MAX) {
            return null;
        }
        
        return [
            'minLat' => $minLat,
            'maxLat' => $maxLat,
            'minLng' => $minLng,
            'maxLng' => $maxLng
        ];
    }

    /**
     * Calcule le centre géographique global de toutes les communes
     */
    private function calculerCentreGeographiqueGlobal(array $communesAvecGeometries): array
    {
        $totalLat = $totalLng = 0;
        $nbCommunes = 0;
        
        foreach ($communesAvecGeometries as $commune) {
            if (!isset($commune['coordinates']) || empty($commune['coordinates'])) {
                continue;
            }
            
            $bbox = $this->calculerBoundingBox($commune['coordinates']);
            if ($bbox) {
                $totalLat += ($bbox['minLat'] + $bbox['maxLat']) / 2;
                $totalLng += ($bbox['minLng'] + $bbox['maxLng']) / 2;
                $nbCommunes++;
            }
        }
        
        if ($nbCommunes === 0) {
            return ['lat' => 46.603354, 'lng' => 1.888334]; // Centre France
        }
        
        return [
            'lat' => $totalLat / $nbCommunes,
            'lng' => $totalLng / $nbCommunes
        ];
    }

    /**
     * Calcule la distance euclidienne entre deux points
     */
    private function calculerDistance(array $point1, array $point2): float
    {
        $deltaLat = $point1['lat'] - $point2['lat'];
        $deltaLng = $point1['lng'] - $point2['lng'];
        return sqrt($deltaLat * $deltaLat + $deltaLng * $deltaLng);
    }

    /**
     * Calcule un centre pondéré par la taille des communes
     */
    private function calculerCentrePondere(array $communesAvecGeometries): ?array
    {
        $totalLat = 0;
        $totalLng = 0;
        $totalPoids = 0;
        
        foreach ($communesAvecGeometries as $commune) {
            if (!isset($commune['coordinates']) || empty($commune['coordinates'])) {
                continue;
            }
            
            $centroide = $this->calculerCentroideCommune($commune['coordinates']);
            if (!$centroide) {
                continue;
            }
            
            $poids = count($commune['coordinates']);
            $totalLat += $centroide['lat'] * $poids;
            $totalLng += $centroide['lng'] * $poids;
            $totalPoids += $poids;
        }
        
        if ($totalPoids === 0) {
            return null;
        }
        
        $centre = [
            'lat' => $totalLat / $totalPoids,
            'lng' => $totalLng / $totalPoids
        ];
        
        error_log("🎯 Centre pondéré calculé: " . json_encode($centre));
        
        return [
            'center' => $centre,
            'communes' => count($communesAvecGeometries),
            'type' => 'centre_pondere'
        ];
    }

    /**
     * Calcule la position hiérarchique optimale basée sur les attributions du secteur
     * Hiérarchie: EPCI > Code Postal > Commune
     */
    private function calculerPositionHierarchique(Secteur $secteur, array $communesAvecGeometries): ?array
    {
        try {
            // Analyser les attributions du secteur par ordre hiérarchique
            $attributions = $secteur->getAttributions();
            
            // 1. PRIORITÉ ÉPCI: Si le secteur contient des EPCI, utiliser le plus central
            $epcis = [];
            foreach ($attributions as $attribution) {
                if ($attribution->getTypeCritere() === TypeSecteur::TYPE_EPCI) {
                    $division = $attribution->getDivisionAdministrative();
                    if ($division && $division->getNomEpci()) {
                        $epcis[] = $division;
                    }
                }
            }
            
            if (!empty($epcis)) {
                $centre = $this->calculerCentreEntitesPrincipales($epcis, $communesAvecGeometries, 'epci');
                if ($centre) {
                    error_log("🎯 Position basée sur EPCI principal");
                    return [
                        'center' => $centre,
                        'type' => 'EPCI',
                        'entite' => count($epcis) . ' EPCI(s)'
                    ];
                }
            }
            
            // 2. PRIORITÉ DÉPARTEMENT: Si pas d'EPCI mais des départements
            $departements = [];
            foreach ($attributions as $attribution) {
                if ($attribution->getTypeCritere() === TypeSecteur::TYPE_DEPARTEMENT) {
                    $division = $attribution->getDivisionAdministrative();
                    if ($division && $division->getCodeDepartement()) {
                        $departements[] = $division;
                    }
                }
            }
            
            if (!empty($departements)) {
                $centre = $this->calculerCentreEntitesPrincipales($departements, $communesAvecGeometries, 'departement');
                if ($centre) {
                    error_log("🎯 Position basée sur département principal");
                    return [
                        'center' => $centre,
                        'type' => 'Département',
                        'entite' => count($departements) . ' département(s)'
                    ];
                }
            }
            
            // 3. FALLBACK: Centre géographique des communes
            if (!empty($communesAvecGeometries)) {
                error_log("📍 Position basée sur communes (fallback)");
                return $this->trouverCommuneCentrale($communesAvecGeometries);
            }
            
        } catch (\Exception $e) {
            error_log("❌ Erreur calcul position hiérarchique: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Calcule le centre d'entités principales (EPCI, départements)
     */
    private function calculerCentreEntitesPrincipales(array $entites, array $communesAvecGeometries, string $type): ?array
    {
        if (empty($entites) || empty($communesAvecGeometries)) {
            return null;
        }
        
        // Si une seule entité, prendre son centre géographique
        if (count($entites) === 1) {
            return $this->calculerCentreGeographiqueGlobal($communesAvecGeometries);
        }
        
        // Si plusieurs entités, prendre celle la plus centrale
        $centreGlobal = $this->calculerCentreGeographiqueGlobal($communesAvecGeometries);
        $entiteOptimale = null;
        $distanceMin = PHP_FLOAT_MAX;
        
        foreach ($entites as $entite) {
            // Approximation: utiliser les coordonnées de la première commune de l'entité
            $coordsEntite = $this->getCoordonneesPourEntite($entite, $communesAvecGeometries, $type);
            
            if ($coordsEntite) {
                $distance = $this->calculerDistance($coordsEntite, $centreGlobal);
                if ($distance < $distanceMin) {
                    $distanceMin = $distance;
                    $entiteOptimale = $coordsEntite;
                }
            }
        }
        
        return $entiteOptimale;
    }

    /**
     * Récupère les coordonnées représentatives d'une entité
     */
    private function getCoordonneesPourEntite($entite, array $communesAvecGeometries, string $type): ?array
    {
        // Pour les EPCI/départements, utiliser le centre des communes qui en font partie
        $communesPertinentes = [];
        
        // Obtenir le code de référence de l'entité selon son type
        $codeReference = null;
        if ($type === 'epci' && method_exists($entite, 'getCodeEpci')) {
            $codeReference = $entite->getCodeEpci();
        } elseif ($type === 'departement' && method_exists($entite, 'getCodeDepartement')) {
            $codeReference = $entite->getCodeDepartement();
        }
        
        if (!$codeReference) {
            error_log("⚠️ getCoordonneesPourEntite: Aucun code de référence trouvé pour le type '$type'");
            return null;
        }
        
        // Récupérer toutes les communes appartenant à cette entité depuis la base
        $communes = [];
        try {
            if ($type === 'epci') {
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune, d.latitude, d.longitude
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeEpci = :code 
                    AND d.codeInseeCommune IS NOT NULL
                    AND d.latitude IS NOT NULL 
                    AND d.longitude IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('code', $codeReference)
                ->getResult();
            } elseif ($type === 'departement') {
                $communes = $this->entityManager->createQuery('
                    SELECT d.codeInseeCommune, d.nomCommune, d.latitude, d.longitude
                    FROM App\Entity\DivisionAdministrative d 
                    WHERE d.codeDepartement = :code 
                    AND d.codeInseeCommune IS NOT NULL
                    AND d.latitude IS NOT NULL 
                    AND d.longitude IS NOT NULL
                    ORDER BY d.nomCommune
                ')
                ->setParameter('code', $codeReference)
                ->getResult();
            }
        } catch (\Exception $e) {
            error_log("❌ Erreur lors de la récupération des communes pour $type $codeReference: " . $e->getMessage());
            return null;
        }
        
        if (empty($communes)) {
            error_log("⚠️ Aucune commune trouvée pour $type $codeReference");
            return null;
        }
        
        // Filtrer les communes avec géométries qui appartiennent à cette entité
        foreach ($communesAvecGeometries as $commune) {
            // BUG FIX: Le cache service retourne 'code_insee' et non 'codeInseeCommune'
            $codeInseeCommune = $commune['code_insee'] ?? $commune['codeInseeCommune'] ?? null;
            if (!$codeInseeCommune) {
                continue;
            }
            
            // Vérifier si cette commune appartient à l'entité
            $appartientAEntite = false;
            foreach ($communes as $communeEntite) {
                if ($communeEntite['codeInseeCommune'] === $codeInseeCommune) {
                    $appartientAEntite = true;
                    break;
                }
            }
            
            if ($appartientAEntite) {
                $communesPertinentes[] = $commune;
            }
        }
        
        error_log("🔍 $type $codeReference: " . count($communes) . " communes dans l'entité, " . 
                 count($communesPertinentes) . " communes pertinentes avec géométries");
        
        if (!empty($communesPertinentes)) {
            return $this->calculerCentreGeographiqueGlobal($communesPertinentes);
        }
        
        // Fallback: si aucune commune avec géométries, utiliser les coordonnées de l'entité
        if (!empty($communes)) {
            $latSum = $lngSum = 0;
            $count = 0;
            
            foreach ($communes as $commune) {
                if ($commune['latitude'] && $commune['longitude']) {
                    $latSum += $commune['latitude'];
                    $lngSum += $commune['longitude'];
                    $count++;
                }
            }
            
            if ($count > 0) {
                error_log("🎯 Fallback: Utilisation centre géographique simple pour $type $codeReference");
                return [
                    'lat' => $latSum / $count,
                    'lng' => $lngSum / $count
                ];
            }
        }
        
        return null;
    }

    // ================================
    // GESTION GROUPES UTILISATEURS
    // ================================

    #[Route('/groupes-utilisateurs/{id}', name: 'app_admin_groupes_get', methods: ['GET'])]
    public function getGroupe(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $groupe = $entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return $this->json(['error' => 'Groupe non trouvé'], 404);
        }

        // Récupérer les sociétés associées
        $societes = [];
        foreach ($groupe->getSocietes() as $societe) {
            $societes[] = [
                'id' => $societe->getId(),
                'nom' => $societe->getNom()
            ];
        }

        return $this->json([
            'id' => $groupe->getId(),
            'nom' => $groupe->getNom(),
            'description' => $groupe->getDescription(),
            'niveau' => $groupe->getNiveau(),
            'couleur' => $groupe->getCouleur(),
            'parent_id' => $groupe->getParent() ? $groupe->getParent()->getId() : null,
            'actif' => $groupe->isActif(),
            'ordre' => $groupe->getOrdre(),
            'permissions' => $groupe->getPermissions(),
            'societes' => $societes
        ]);
    }

    #[Route('/groupes-utilisateurs/{id}', name: 'app_admin_groupes_update', methods: ['PUT'])]
    public function updateGroupe(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $groupe = $entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return $this->json(['error' => 'Groupe non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs
        if (isset($data['nom'])) {
            $groupe->setNom($data['nom']);
        }
        if (isset($data['description'])) {
            $groupe->setDescription($data['description']);
        }
        if (isset($data['niveau'])) {
            $groupe->setNiveau($data['niveau']);
        }
        if (isset($data['couleur'])) {
            $groupe->setCouleur($data['couleur']);
        }
        if (isset($data['actif'])) {
            $groupe->setActif($data['actif']);
        }
        if (isset($data['parent_id'])) {
            if ($data['parent_id']) {
                $parent = $entityManager->getRepository(GroupeUtilisateur::class)->find($data['parent_id']);
                $groupe->setParent($parent);
            } else {
                $groupe->setParent(null);
            }
        }
        if (isset($data['permissions'])) {
            $groupe->setPermissions($data['permissions']);
        }
        if (isset($data['societes'])) {
            // Supprimer toutes les sociétés existantes
            $groupe->getSocietes()->clear();
            
            // Ajouter les nouvelles sociétés
            foreach ($data['societes'] as $societeId) {
                $societe = $entityManager->getRepository(Societe::class)->find($societeId);
                if ($societe) {
                    $groupe->addSociete($societe);
                }
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Groupe mis à jour avec succès'
        ]);
    }

    #[Route('/groupes-utilisateurs', name: 'app_admin_groupes_create', methods: ['POST'])]
    public function createGroupe(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $groupe = new GroupeUtilisateur();
        $groupe->setNom($data['nom']);
        $groupe->setDescription($data['description'] ?? '');
        $groupe->setNiveau($data['niveau'] ?? 1);
        $groupe->setCouleur($data['couleur'] ?? '#6c757d');
        $groupe->setActif($data['actif'] ?? true);
        $groupe->setOrdre($data['ordre'] ?? 0);
        $groupe->setPermissions($data['permissions'] ?? []);

        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = $entityManager->getRepository(GroupeUtilisateur::class)->find($data['parent_id']);
            $groupe->setParent($parent);
        }

        $entityManager->persist($groupe);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Groupe créé avec succès',
            'id' => $groupe->getId()
        ]);
    }

    #[Route('/groupes-utilisateurs/{id}', name: 'app_admin_groupes_delete', methods: ['DELETE'])]
    public function deleteGroupe(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $groupe = $entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return $this->json(['error' => 'Groupe non trouvé'], 404);
        }

        // Vérifier si le groupe est utilisé par des utilisateurs
        $usersCount = $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->join('u.groupes', 'g')
            ->where('g.id = :groupeId')
            ->setParameter('groupeId', $id)
            ->getQuery()
            ->getSingleScalarResult();

        if ($usersCount > 0) {
            return $this->json([
                'error' => "Impossible de supprimer ce groupe car il est utilisé par {$usersCount} utilisateur(s)"
            ], 400);
        }

        $entityManager->remove($groupe);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Groupe supprimé avec succès'
        ]);
    }

    #[Route('/groupes-utilisateurs/{id}/toggle', name: 'app_admin_groupes_toggle', methods: ['POST'])]
    public function toggleGroupe(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $groupe = $entityManager->getRepository(GroupeUtilisateur::class)->find($id);
        
        if (!$groupe) {
            return $this->json(['error' => 'Groupe non trouvé'], 404);
        }

        $groupe->setActif(!$groupe->isActif());
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $groupe->isActif() ? 'Groupe activé' : 'Groupe désactivé'
        ]);
    }
}