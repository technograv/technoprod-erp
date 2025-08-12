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
                        $this->calculerBoundsSecteur($secteurData, $communesAvecGeometries);
                        
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
     * Calcule et met à jour les bounds d'un secteur avec de nouvelles géométries
     */
    private function calculerBoundsSecteur(array &$secteurData, array $communesAvecGeometries): void
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
            
            $secteurData['center'] = [
                'lat' => ($minLat + $maxLat) / 2,
                'lng' => ($minLng + $maxLng) / 2
            ];
            
            $secteurData['hasCoordinates'] = true;
        }
    }
}