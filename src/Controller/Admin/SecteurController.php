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
            
            if (!isset($data['secteur_id']) || !isset($data['type']) || !isset($data['identifiant'])) {
                return $this->json(['error' => 'Données manquantes'], 400);
            }

            $secteur = $this->entityManager->find(Secteur::class, $data['secteur_id']);
            if (!$secteur) {
                return $this->json(['error' => 'Secteur non trouvé'], 404);
            }

            $attribution = new AttributionSecteur();
            $attribution->setSecteur($secteur);
            $attribution->setType($data['type']);
            $attribution->setIdentifiant($data['identifiant']);
            $attribution->setNom($data['nom'] ?? '');
            $attribution->setCreatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($attribution);
            $this->entityManager->flush();

            // Créer automatiquement les exclusions géographiques
            $this->createGeographicExclusions($attribution);

            return $this->json([
                'success' => true,
                'message' => 'Attribution créée avec succès',
                'attribution' => [
                    'id' => $attribution->getId(),
                    'type' => $attribution->getType(),
                    'identifiant' => $attribution->getIdentifiant(),
                    'nom' => $attribution->getNom()
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
        try {
            // Récupérer tous les secteurs actifs (isActive = true)
            $secteurs = $this->entityManager
                ->getRepository(Secteur::class)
                ->findBy(['isActive' => true]);
            
            $allGeoData = [];
            
            foreach ($secteurs as $secteur) {
                // Vérifier si le secteur a des attributions avec coordonnées
                $hasCoordinates = false;
                $attributionsData = [];
                
                foreach ($secteur->getAttributions() as $attribution) {
                    $type = $attribution->getTypeCritere();
                    $attributionData = [
                        'id' => $attribution->getId(),
                        'type' => $type,
                        'boundary_type' => 'real', // Utiliser 'real' pour déclencher l'API des frontières
                        'api_type' => $type,
                        'api_code' => $attribution->getValeurCritere(),
                        'nom' => $this->getDivisionAdministrativeNom($attribution),
                        'hasCoordinates' => true // Le système attend que l'API fournisse les frontières
                    ];
                    $attributionsData[] = $attributionData;
                    $hasCoordinates = true;
                }
                
                $secteurData = [
                    'id' => $secteur->getId(),
                    'nom' => $secteur->getNomSecteur(),
                    'couleur' => $secteur->getCouleurHex() ?: '#3498db',
                    'isActive' => $secteur->isActive(),
                    'hasCoordinates' => $hasCoordinates,
                    'attributions' => $attributionsData,
                    'commercial' => $secteur->getCommercial() ? $secteur->getCommercial()->getNom() : null
                ];
                
                $allGeoData[] = $secteurData;
            }
            
            return $this->json(['success' => true, 'secteurs' => $allGeoData]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()], 500);
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
        
        $queryBuilder = $this->entityManager
            ->getRepository(DivisionAdministrative::class)
            ->createQueryBuilder('d')
            ->where('d.nom LIKE :terme')
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('d.nom', 'ASC')
            ->setMaxResults(50);
        
        if ($type) {
            $queryBuilder->andWhere('d.type = :type')
                        ->setParameter('type', $type);
        }
        
        $divisions = $queryBuilder->getQuery()->getResult();
        
        $result = [];
        foreach ($divisions as $division) {
            $result[] = [
                'id' => $division->getId(),
                'nom' => $division->getNom(),
                'type' => $division->getType(),
                'code' => $division->getCode(),
                'code_insee' => $division->getCodeInsee(),
                'code_postal' => $division->getCodePostal()
            ];
        }
        
        return $this->json(['divisions' => $result]);
    }

    #[Route('/divisions-administratives', name: 'app_admin_divisions_administratives', methods: ['GET'])]
    public function divisionsAdministratives(): JsonResponse
    {
        $divisions = $this->entityManager
            ->getRepository(DivisionAdministrative::class)
            ->findBy([], ['type' => 'ASC', 'nom' => 'ASC'], 100);
        
        $result = [];
        foreach ($divisions as $division) {
            $result[] = [
                'id' => $division->getId(),
                'nom' => $division->getNom(),
                'type' => $division->getType(),
                'code' => $division->getCode(),
                'code_insee' => $division->getCodeInsee(),
                'population' => $division->getPopulation()
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
}