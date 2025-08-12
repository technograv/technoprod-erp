<?php

namespace App\Controller\Admin;

use App\Entity\Secteur;
use App\Entity\AttributionSecteur;
use App\Entity\ExclusionSecteur;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use App\Service\EpciBoundariesService;
use App\Service\CommuneGeometryService;
use App\Service\GeographicBoundariesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SecteurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GeographicBoundariesService $boundariesService,
        private CommuneGeometryService $communeGeometryService,
        private EpciBoundariesService $epciBoundariesService
    ) {}

    // ================================
    // SECTEURS ADMINISTRATION
    // ================================

    #[Route('/secteurs-admin', name: 'app_admin_secteurs_moderne', methods: ['GET'])]
    public function secteursAdmin(): Response
    {
        $secteurs = $this->entityManager
            ->getRepository(Secteur::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $typesSecteur = $this->entityManager
            ->getRepository(TypeSecteur::class)
            ->findBy(['actif' => true], ['nom' => 'ASC']);
        
        return $this->render('admin/secteur/secteurs_admin.html.twig', [
            'secteurs' => $secteurs,
            'types_secteur' => $typesSecteur
        ]);
    }

    #[Route('/secteur/{id}/attributions', name: 'app_admin_secteur_attributions', methods: ['GET'])]
    public function getSecteurAttributions(Secteur $secteur): JsonResponse
    {
        $attributions = [];
        foreach ($secteur->getAttributions() as $attribution) {
            $attributions[] = [
                'id' => $attribution->getId(),
                'type' => $attribution->getType(),
                'identifiant' => $attribution->getIdentifiant(),
                'nom' => $attribution->getNom(),
                'exclusions_count' => $attribution->getExclusions()->count()
            ];
        }
        
        return $this->json(['attributions' => $attributions]);
    }

    #[Route('/secteur/attribution/create', name: 'app_admin_secteur_attribution_create', methods: ['POST'])]
    public function createAttribution(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['secteurId']) || !isset($data['typeCritere']) || !isset($data['valeurCritere'])) {
                return $this->json(['error' => 'Données manquantes (secteurId, typeCritere, valeurCritere requis)'], 400);
            }

            $secteur = $this->entityManager->find(Secteur::class, $data['secteurId']);
            if (!$secteur) {
                return $this->json(['error' => 'Secteur non trouvé'], 404);
            }

            // Trouver la division administrative correspondante
            $divisionId = $data['divisionId'] ?? null;
            if (!$divisionId) {
                return $this->json(['error' => 'ID de division administrative manquant'], 400);
            }

            $division = $this->entityManager->find(DivisionAdministrative::class, $divisionId);
            if (!$division) {
                return $this->json(['error' => 'Division administrative non trouvée'], 404);
            }

            $attribution = new AttributionSecteur();
            $attribution->setSecteur($secteur);
            $attribution->setDivisionAdministrative($division);
            $attribution->setTypeCritere($data['typeCritere']);
            $attribution->setValeurCritere($data['valeurCritere']);
            $attribution->setNotes($data['notes'] ?? null);
            
            $this->entityManager->persist($attribution);
            $this->entityManager->flush();

            // Créer automatiquement les exclusions géographiques
            $this->createGeographicExclusions($attribution);

            return $this->json([
                'success' => true,
                'message' => 'Attribution créée avec succès',
                'attribution' => [
                    'id' => $attribution->getId(),
                    'type' => $attribution->getTypeCritere(),
                    'valeur' => $attribution->getValeurCritere(),
                    'nom' => (string)$attribution
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/secteur/attribution/{id}', name: 'app_admin_secteur_attribution_delete', methods: ['DELETE'])]
    public function deleteAttribution(AttributionSecteur $attribution): JsonResponse
    {
        try {
            // Supprimer les exclusions associées
            foreach ($attribution->getExclusions() as $exclusion) {
                $this->entityManager->remove($exclusion);
            }
            
            $this->entityManager->remove($attribution);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Attribution supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // DONNÉES GÉOGRAPHIQUES
    // ================================

    #[Route('/secteur/{id}/geo-data', name: 'app_admin_secteur_geo_data', methods: ['GET'])]
    public function getSecteurGeoData(Secteur $secteur): JsonResponse
    {
        try {
            $geoData = [];
            
            foreach ($secteur->getAttributions() as $attribution) {
                $boundaries = $this->boundariesService->getBoundaries(
                    $attribution->getType(),
                    $attribution->getIdentifiant()
                );
                
                if ($boundaries) {
                    $geoData[] = [
                        'attribution_id' => $attribution->getId(),
                        'type' => $attribution->getType(),
                        'identifiant' => $attribution->getIdentifiant(),
                        'nom' => $attribution->getNom(),
                        'boundaries' => $boundaries
                    ];
                }
            }
            
            return $this->json(['geo_data' => $geoData]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/secteurs/all-geo-data', name: 'app_admin_secteurs_all_geo_data', methods: ['GET'])]
    public function getAllSecteursGeoData(): JsonResponse
    {
        error_log("🔍 DEBUG: getAllSecteursGeoData - APPROCHE hiérarchique restaurée du commit 88cdd1c");
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

            // ÉTAPE 1: Créer un mappage global commune → secteur selon priorité hiérarchique
            $communeVsSecteur = []; // codeInsee → secteurId
            $attributionsTraitees = [];
            
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
            
            // Traitement hiérarchique GLOBAL: communes → CP → EPCIs → départements → régions
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
                    $communesDuType = $this->getCommunesPourType($typeActuel, $division);
                    
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
                        $attributionsTraitees[] = [
                            'secteur' => $secteur,
                            'attribution' => $attribution,
                            'nouvelles_communes' => $nouvellesCommunes
                        ];
                    }
                    
                    error_log("📍 {$typeActuel} '{$attribution->getValeurCritere()}' → {$secteur->getNomSecteur()}: {$nouvellesCommunes} nouvelles communes");
                }
            }
            
            // ÉTAPE 2: Construire les données secteurs avec SEULEMENT leurs communes assignées
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
                    'bounds' => null,
                    'center' => null,
                    'hasCoordinates' => false
                ];
                
                // Récupérer TOUTES les communes assignées à ce secteur
                $communesSecteur = [];
                foreach ($communeVsSecteur as $codeInsee => $secteurId) {
                    if ($secteurId === $secteur->getId()) {
                        $communesSecteur[] = [
                            'codeInseeCommune' => $codeInsee,
                            'nomCommune' => 'Commune ' . $codeInsee // Sera enrichi avec la géométrie
                        ];
                    }
                }
                
                if (!empty($communesSecteur)) {
                    // Récupérer les géométries depuis le cache
                    $communesAvecGeometries = [];
                    foreach ($communesSecteur as $commune) {
                        $codeInsee = $commune['codeInseeCommune'];
                        $geometry = $this->communeGeometryService->getGeometry($codeInsee);
                        if ($geometry && !empty($geometry)) {
                            $communesAvecGeometries[] = [
                                'codeInsee' => $codeInsee,
                                'nom' => $commune['nomCommune'],
                                'boundaries' => $geometry
                            ];
                        }
                    }
                    
                    if (!empty($communesAvecGeometries)) {
                        // Créer une attribution globale "secteur_complet"
                        $attributionGlobale = [
                            'id' => 'secteur_' . $secteur->getId(),
                            'type' => 'secteur_complet',
                            'boundary_type' => 'communes_reelles',
                            'nom' => $secteur->getNomSecteur(),
                            'communes' => $communesAvecGeometries,
                            'hasCoordinates' => true
                        ];
                        
                        $secteurData['attributions'] = [$attributionGlobale];
                        $this->calculerBoundsSecteur($secteurData, $communesAvecGeometries);
                    }
                }
                
                $secteursData[] = $secteurData;
            }
            
            error_log("🎯 Assignation globale terminée - " . count($communeVsSecteur) . " communes assignées");
            
            return $this->json([
                'success' => true,
                'secteurs' => $secteursData,
                'total' => count($secteursData),
                'debug' => [
                    'communes_assignees' => count($communeVsSecteur),
                    'methode' => 'hierarchique_global_restaure'
                ]
            ]);

        } catch (\Exception $e) {
            error_log("❌ Erreur getAllSecteursGeoData: " . $e->getMessage());
            return $this->json(['error' => 'Erreur lors de la récupération des secteurs'], 500);
        }
    }

    private function getCommunesPourType(string $type, $division): array
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
                $communes = $this->entityManager->createQuery('
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
                $communes = $this->entityManager->createQuery('
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
                $communes = $this->entityManager->createQuery('
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
                $communes = $this->entityManager->createQuery('
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

    private function calculerBoundsSecteur(array &$secteurData, array $communesAvecGeometries): void
    {
        if (empty($communesAvecGeometries)) {
            return;
        }

        $allLatitudes = [];
        $allLongitudes = [];

        // Collecter toutes les coordonnées de toutes les communes
        foreach ($communesAvecGeometries as $commune) {
            if (isset($commune['boundaries']) && !empty($commune['boundaries'])) {
                foreach ($commune['boundaries'] as $point) {
                    if (isset($point['lat']) && isset($point['lng'])) {
                        $allLatitudes[] = $point['lat'];
                        $allLongitudes[] = $point['lng'];
                    }
                }
            }
        }

        if (!empty($allLatitudes) && !empty($allLongitudes)) {
            $secteurData['bounds'] = [
                'north' => max($allLatitudes),
                'south' => min($allLatitudes),
                'east' => max($allLongitudes),
                'west' => min($allLongitudes)
            ];

            // Calculer le centre
            $secteurData['center'] = [
                'lat' => (max($allLatitudes) + min($allLatitudes)) / 2,
                'lng' => (max($allLongitudes) + min($allLongitudes)) / 2
            ];

            $secteurData['hasCoordinates'] = true;
        }
    }

    private function getDivisionAdministrativeNom($attribution): string
    {
        if (!$attribution->getDivisionAdministrative()) {
            return 'Division inconnue';
        }
        
        $division = $attribution->getDivisionAdministrative();
        $typeCritere = $attribution->getTypeCritere();
        
        // Selon le type de critère, utiliser la bonne méthode
        switch ($typeCritere) {
            case 'commune':
            case 'code_postal':
                return $division->getNomCommune() ?: 'Commune inconnue';
            case 'departement':
                return $division->getNomDepartement() ?: 'Département inconnu';
            case 'region':
                return $division->getNomRegion() ?: 'Région inconnue';
            case 'epci':
                return $division->getNomEpci() ?: 'EPCI inconnu';
            case 'canton':
                return $division->getNomCanton() ?: 'Canton inconnu';
            default:
                // Essayer d'utiliser le nom de commune en premier par défaut
                return $division->getNomCommune() 
                    ?: $division->getNomDepartement() 
                    ?: $division->getNomRegion() 
                    ?: $division->getNomEpci()
                    ?: 'Division inconnue';
        }
    }

    #[Route('/commune/{codeInsee}/geometry', name: 'app_admin_commune_geometry', methods: ['GET'])]
    public function getCommuneGeometry(string $codeInsee): JsonResponse
    {
        try {
            $geometry = $this->communeGeometryService->getGeometry($codeInsee);
            
            if (!$geometry) {
                return $this->json(['error' => 'Géométrie non trouvée'], 404);
            }
            
            return $this->json(['geometry' => $geometry]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // BOUNDARIES API
    // ================================

    #[Route('/boundaries/{type}/{code}', name: 'app_admin_boundaries', methods: ['GET'])]
    public function getBoundaries(string $type, string $code): JsonResponse
    {
        try {
            $boundaries = $this->boundariesService->getBoundaries($type, $code);
            
            if (!$boundaries) {
                return $this->json(['error' => 'Boundaries non trouvées'], 404);
            }
            
            return $this->json(['boundaries' => $boundaries]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/code-postal/{codePostal}/boundaries', name: 'app_admin_code_postal_boundaries', methods: ['GET'])]
    public function getCodePostalBoundaries(string $codePostal): JsonResponse
    {
        try {
            // Récupérer toutes les communes de ce code postal
            $communes = $this->entityManager
                ->getRepository(DivisionAdministrative::class)
                ->findBy(['codePostal' => $codePostal, 'type' => 'commune']);
            
            $boundaries = [];
            foreach ($communes as $commune) {
                $geometry = $this->communeGeometryService->getGeometry($commune->getCodeInsee());
                if ($geometry) {
                    $boundaries[] = [
                        'code_insee' => $commune->getCodeInsee(),
                        'nom' => $commune->getNom(),
                        'geometry' => $geometry
                    ];
                }
            }
            
            return $this->json(['boundaries' => $boundaries]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/canton/{codeCanton}/boundaries', name: 'app_admin_canton_boundaries', methods: ['GET'])]
    public function getCantonBoundaries(string $codeCanton): JsonResponse
    {
        try {
            $boundaries = $this->boundariesService->getBoundaries('canton', $codeCanton);
            return $this->json(['boundaries' => $boundaries]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/departement/{codeDepartement}/boundaries', name: 'app_admin_departement_boundaries', methods: ['GET'])]
    public function getDepartementBoundaries(string $codeDepartement): JsonResponse
    {
        try {
            $boundaries = $this->boundariesService->getBoundaries('departement', $codeDepartement);
            return $this->json(['boundaries' => $boundaries]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/region/{codeRegion}/boundaries', name: 'app_admin_region_boundaries', methods: ['GET'])]
    public function getRegionBoundaries(string $codeRegion): JsonResponse
    {
        try {
            $boundaries = $this->boundariesService->getBoundaries('region', $codeRegion);
            return $this->json(['boundaries' => $boundaries]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/epci/{codeEpci}/boundaries', name: 'app_admin_epci_boundaries', methods: ['GET'])]
    public function getEpciBoundaries(string $codeEpci): JsonResponse
    {
        try {
            // Utiliser la route générique pour récupérer les frontières EPCI
            $boundaries = $this->boundariesService->getBoundaries('epci', $codeEpci);
            
            if ($boundaries) {
                return $this->json($boundaries);
            } else {
                return $this->json(['error' => 'Frontières EPCI non trouvées'], 404);
            }
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // DIVISIONS ADMINISTRATIVES
    // ================================

    #[Route('/divisions-administratives/recherche', name: 'app_admin_divisions_recherche', methods: ['GET'])]
    public function rechercherDivisions(Request $request): JsonResponse
    {
        $terme = $request->query->get('terme', '');
        $type = $request->query->get('type', '');
        
        if (strlen($terme) < 2) {
            return $this->json(['divisions' => []]);
        }
        
        // Recherche dans plusieurs champs selon le type demandé
        $queryBuilder = $this->entityManager
            ->getRepository(DivisionAdministrative::class)
            ->createQueryBuilder('d');
        
        switch ($type) {
            case 'commune':
                $queryBuilder->where('LOWER(d.nomCommune) LIKE LOWER(:terme)')
                           ->setParameter('terme', '%' . $terme . '%')
                           ->orderBy('d.nomCommune', 'ASC');
                break;
                
            case 'departement':
                $queryBuilder->where('LOWER(d.nomDepartement) LIKE LOWER(:terme) OR LOWER(d.codeDepartement) LIKE LOWER(:terme)')
                           ->setParameter('terme', '%' . $terme . '%')
                           ->orderBy('d.nomDepartement', 'ASC');
                break;
                
            case 'region':
                $queryBuilder->where('LOWER(d.nomRegion) LIKE LOWER(:terme) OR LOWER(d.codeRegion) LIKE LOWER(:terme)')
                           ->setParameter('terme', '%' . $terme . '%')
                           ->orderBy('d.nomRegion', 'ASC');
                break;
                
            case 'epci':
                $queryBuilder->where('LOWER(d.nomEpci) LIKE LOWER(:terme) OR LOWER(d.codeEpci) LIKE LOWER(:terme)')
                           ->setParameter('terme', '%' . $terme . '%')
                           ->orderBy('d.nomEpci', 'ASC');
                break;
                
            case 'code_postal':
                $queryBuilder->where('LOWER(d.codePostal) LIKE LOWER(:terme)')
                           ->setParameter('terme', '%' . $terme . '%')
                           ->orderBy('d.codePostal', 'ASC');
                break;
                
            default:
                // Recherche globale dans tous les noms
                $queryBuilder->where('LOWER(d.nomCommune) LIKE LOWER(:terme) OR LOWER(d.nomDepartement) LIKE LOWER(:terme) OR LOWER(d.nomRegion) LIKE LOWER(:terme) OR LOWER(d.nomEpci) LIKE LOWER(:terme) OR LOWER(d.codePostal) LIKE LOWER(:terme)')
                           ->setParameter('terme', '%' . $terme . '%')
                           ->orderBy('d.nomCommune', 'ASC');
                break;
        }
        
        $queryBuilder->setMaxResults(50);
        $divisions = $queryBuilder->getQuery()->getResult();
        
        $result = [];
        $seen = []; // Pour éviter les doublons
        $postalCodeCounts = []; // Pour compter les communes par code postal
        
        // Premier passage : compter les communes par code postal si on recherche par code postal
        if ($type === 'code_postal') {
            foreach ($divisions as $division) {
                $codePostal = $division->getCodePostal();
                if (!isset($postalCodeCounts[$codePostal])) {
                    $postalCodeCounts[$codePostal] = 0;
                }
                $postalCodeCounts[$codePostal]++;
            }
        }
        
        foreach ($divisions as $division) {
            // Déterminer le type principal basé sur ce qui correspond le mieux à la recherche
            $typeResult = $this->determineTypeFromSearch($division, $terme, $type);
            
            // Clé unique pour déduplication basée sur le type et le code
            $uniqueKey = $typeResult['type'] . '_' . $typeResult['code'];
            
            // Éviter les doublons
            if (isset($seen[$uniqueKey])) {
                continue;
            }
            $seen[$uniqueKey] = true;
            
            // Enrichir la description pour les codes postaux avec le nombre de communes
            if ($typeResult['type'] === 'code_postal' && isset($postalCodeCounts[$typeResult['code']])) {
                $count = $postalCodeCounts[$typeResult['code']];
                $typeResult['description'] = 'Code postal ' . $typeResult['code'] . ' (' . $count . ' commune' . ($count > 1 ? 's' : '') . ')';
            }
            
            $result[] = [
                'id' => $division->getId(),
                'nom' => $typeResult['nom'],
                'type' => $typeResult['type'],
                'code' => $typeResult['code'],
                'valeur' => $typeResult['code'], // Pour la sélection
                'details' => $typeResult['description'], // Description affichée
                'code_insee' => $division->getCodeInseeCommune(),
                'code_postal' => $division->getCodePostal()
            ];
        }
        
        return $this->json([
            'success' => true,
            'results' => $result,
            'divisions' => $result // Garde la compatibilité
        ]);
    }

    #[Route('/divisions-administratives', name: 'app_admin_divisions_administratives', methods: ['GET'])]
    public function divisionsAdministratives(): JsonResponse
    {
        $divisions = $this->entityManager
            ->getRepository(DivisionAdministrative::class)
            ->findBy([], ['nomCommune' => 'ASC'], 100);
        
        $result = [];
        foreach ($divisions as $division) {
            // Créer un résultat pour chaque type de division présent
            $typeResult = $this->determineTypeFromSearch($division, '', '');
            
            $result[] = [
                'id' => $division->getId(),
                'nom' => $typeResult['nom'],
                'type' => $typeResult['type'],
                'code' => $typeResult['code'],
                'code_insee' => $division->getCodeInseeCommune(),
                'population' => $division->getPopulation(),
                'description' => $typeResult['description']
            ];
        }
        
        return $this->json(['divisions' => $result]);
    }

    #[Route('/divisions-administratives/search', name: 'app_admin_divisions_search', methods: ['GET'])]
    public function searchDivisions(Request $request): JsonResponse
    {
        return $this->rechercherDivisions($request);
    }

    // ================================
    // TYPES SECTEUR
    // ================================

    #[Route('/types-secteur', name: 'app_admin_types_secteur', methods: ['GET'])]
    public function typesSecteur(): JsonResponse
    {
        $types = $this->entityManager
            ->getRepository(TypeSecteur::class)
            ->findBy([], ['nom' => 'ASC']);
        
        $result = [];
        foreach ($types as $type) {
            $result[] = [
                'id' => $type->getId(),
                'nom' => $type->getNom(),
                'description' => $type->getDescription(),
                'actif' => $type->isActif()
            ];
        }
        
        return $this->json(['types' => $result]);
    }

    #[Route('/types-secteur/create', name: 'app_admin_types_secteur_create', methods: ['POST'])]
    public function createTypeSecteur(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['nom'])) {
                return $this->json(['error' => 'Le nom est obligatoire'], 400);
            }

            $type = new TypeSecteur();
            $type->setNom($data['nom']);
            $type->setDescription($data['description'] ?? '');
            $type->setActif($data['actif'] ?? true);
            
            $this->entityManager->persist($type);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Type de secteur créé avec succès',
                'type' => [
                    'id' => $type->getId(),
                    'nom' => $type->getNom(),
                    'actif' => $type->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-secteur/{id}', name: 'app_admin_types_secteur_update', methods: ['PUT'])]
    public function updateTypeSecteur(Request $request, TypeSecteur $type): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (isset($data['nom'])) {
                $type->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $type->setDescription($data['description']);
            }
            if (isset($data['actif'])) {
                $type->setActif($data['actif']);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Type de secteur mis à jour avec succès',
                'type' => [
                    'id' => $type->getId(),
                    'nom' => $type->getNom(),
                    'actif' => $type->isActif()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-secteur/{id}', name: 'app_admin_types_secteur_delete', methods: ['DELETE'])]
    public function deleteTypeSecteur(TypeSecteur $type): JsonResponse
    {
        try {
            $this->entityManager->remove($type);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Type de secteur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // DEBUG & TEST
    // ================================

    #[Route('/test/secteur-data/{id}', name: 'app_admin_test_secteur_data', methods: ['GET'])]
    public function testSecteurData(Secteur $secteur): JsonResponse
    {
        $data = [
            'secteur' => [
                'id' => $secteur->getId(),
                'nom' => $secteur->getNom(),
                'attributions_count' => $secteur->getAttributions()->count()
            ],
            'attributions' => []
        ];
        
        foreach ($secteur->getAttributions() as $attribution) {
            $data['attributions'][] = [
                'id' => $attribution->getId(),
                'type' => $attribution->getType(),
                'identifiant' => $attribution->getIdentifiant(),
                'nom' => $attribution->getNom(),
                'exclusions_count' => $attribution->getExclusions()->count()
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/debug/exclusions/{id}', name: 'app_admin_debug_exclusions', methods: ['GET'])]
    public function debugExclusions(AttributionSecteur $attribution): JsonResponse
    {
        $exclusions = [];
        foreach ($attribution->getExclusions() as $exclusion) {
            $exclusions[] = [
                'id' => $exclusion->getId(),
                'type_exclusion' => $exclusion->getTypeExclusion(),
                'identifiant_exclusion' => $exclusion->getIdentifiantExclusion(),
                'nom_exclusion' => $exclusion->getNomExclusion(),
                'raison' => $exclusion->getRaison()
            ];
        }
        
        return $this->json(['exclusions' => $exclusions]);
    }

    // ================================
    // HELPER METHODS
    // ================================

    private function createGeographicExclusions(AttributionSecteur $attribution): void
    {
        // Implémentation de la création automatique des exclusions géographiques
        // Basée sur la logique métier spécifique au système TechnoProd
        try {
            // Cette méthode créerait automatiquement les exclusions selon les règles métier
            // Par exemple : si attribution = département, exclure les EPCIs qui chevauchent
            // Le détail de l'implémentation dépendrait des règles métier exactes
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer la création de l'attribution
            error_log("Erreur création exclusions géographiques: " . $e->getMessage());
        }
    }

    /**
     * Détermine le type et les informations appropriés basés sur la recherche
     */
    private function determineTypeFromSearch(DivisionAdministrative $division, string $terme, string $typeRecherche): array
    {
        // Si un type spécifique est demandé, l'utiliser
        if ($typeRecherche) {
            switch ($typeRecherche) {
                case 'commune':
                    return [
                        'nom' => $division->getNomCommune(),
                        'type' => 'commune',
                        'code' => $division->getCodeInseeCommune(),
                        'description' => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')'
                    ];
                    
                case 'departement':
                    return [
                        'nom' => $division->getNomDepartement(),
                        'type' => 'departement', 
                        'code' => $division->getCodeDepartement(),
                        'description' => $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')'
                    ];
                    
                case 'region':
                    return [
                        'nom' => $division->getNomRegion(),
                        'type' => 'region',
                        'code' => $division->getCodeRegion(),
                        'description' => $division->getNomRegion() . ' (' . $division->getCodeRegion() . ')'
                    ];
                    
                case 'epci':
                    return [
                        'nom' => $division->getNomEpci(),
                        'type' => 'epci',
                        'code' => $division->getCodeEpci(),
                        'description' => $division->getNomEpci() . ' (' . $division->getTypeEpci() . ')'
                    ];
                    
                case 'code_postal':
                    return [
                        'nom' => $division->getCodePostal(),
                        'type' => 'code_postal',
                        'code' => $division->getCodePostal(),
                        'description' => 'Code postal ' . $division->getCodePostal()
                    ];
            }
        }
        
        // Sinon, déterminer automatiquement le meilleur match basé sur le terme recherché
        $terme = strtolower($terme);
        
        // Recherche dans le code postal d'abord (plus spécifique)
        if ($division->getCodePostal() && str_contains(strtolower($division->getCodePostal()), $terme)) {
            return [
                'nom' => $division->getCodePostal(),
                'type' => 'code_postal',
                'code' => $division->getCodePostal(),
                'description' => 'Code postal ' . $division->getCodePostal()
            ];
        }
        
        // Puis dans le nom de commune
        if ($division->getNomCommune() && str_contains(strtolower($division->getNomCommune()), $terme)) {
            return [
                'nom' => $division->getNomCommune(),
                'type' => 'commune',
                'code' => $division->getCodeInseeCommune(),
                'description' => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')'
            ];
        }
        
        // Puis EPCI
        if ($division->getNomEpci() && str_contains(strtolower($division->getNomEpci()), $terme)) {
            return [
                'nom' => $division->getNomEpci(),
                'type' => 'epci',
                'code' => $division->getCodeEpci(),
                'description' => $division->getNomEpci() . ' (' . $division->getTypeEpci() . ')'
            ];
        }
        
        // Puis département
        if ($division->getNomDepartement() && str_contains(strtolower($division->getNomDepartement()), $terme)) {
            return [
                'nom' => $division->getNomDepartement(),
                'type' => 'departement',
                'code' => $division->getCodeDepartement(),
                'description' => $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')'
            ];
        }
        
        // Enfin région
        if ($division->getNomRegion() && str_contains(strtolower($division->getNomRegion()), $terme)) {
            return [
                'nom' => $division->getNomRegion(),
                'type' => 'region',
                'code' => $division->getCodeRegion(),
                'description' => $division->getNomRegion() . ' (' . $division->getCodeRegion() . ')'
            ];
        }
        
        // Par défaut, retourner la commune
        return [
            'nom' => $division->getNomCommune(),
            'type' => 'commune',
            'code' => $division->getCodeInseeCommune(),
            'description' => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')'
        ];
    }
}