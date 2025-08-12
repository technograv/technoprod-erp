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

    // ================================
    // DIVISIONS ADMINISTRATIVES
    // ================================

    #[Route('/divisions-administratives/recherche', name: 'app_admin_divisions_recherche', methods: ['GET'])]
    public function rechercherDivisions(Request $request): JsonResponse
    {
        $terme = $request->query->get('terme', '');
        $type = $request->query->get('type', '');
        
        if (strlen($terme) < 2) {
            return $this->json(['success' => true, 'results' => []]);
        }
        
        $queryBuilder = $this->entityManager
            ->getRepository(DivisionAdministrative::class)
            ->createQueryBuilder('d');
        
        // Spécialisation pour codes postaux : recherche pure par code postal
        if ($type === 'code_postal') {
            // Recherche uniquement par code postal, pas par nom de commune
            $queryBuilder->where('LOWER(d.codePostal) LIKE LOWER(:terme)')
                ->setParameter('terme', '%' . $terme . '%')
                ->orderBy('d.codePostal', 'ASC');
            
            $divisions = $queryBuilder->getQuery()->getResult();
            
            // Compter les communes par code postal
            $postalCodeCounts = [];
            foreach ($divisions as $division) {
                $codePostal = $division->getCodePostal();
                if ($codePostal) {
                    if (!isset($postalCodeCounts[$codePostal])) {
                        $postalCodeCounts[$codePostal] = 0;
                    }
                    $postalCodeCounts[$codePostal]++;
                }
            }
            
            // Déduplication par code postal
            $results = [];
            $seenPostalCodes = [];
            
            foreach ($divisions as $division) {
                $codePostal = $division->getCodePostal();
                if ($codePostal && !in_array($codePostal, $seenPostalCodes)) {
                    $seenPostalCodes[] = $codePostal;
                    
                    $count = $postalCodeCounts[$codePostal] ?? 1;
                    $results[] = [
                        'nom' => $codePostal,
                        'type' => 'code_postal',
                        'code' => $codePostal,
                        'description' => 'Code postal ' . $codePostal . ' (' . $count . ' commune' . ($count > 1 ? 's' : '') . ')',
                        'details' => $count . ' commune' . ($count > 1 ? 's' : '')
                    ];
                }
            }
        } else {
            // Recherche normale pour autres types
            $queryBuilder->where('LOWER(d.nomCommune) LIKE LOWER(:terme) OR LOWER(d.nomDepartement) LIKE LOWER(:terme) OR LOWER(d.nomRegion) LIKE LOWER(:terme) OR LOWER(d.nomEpci) LIKE LOWER(:terme) OR LOWER(d.codePostal) LIKE LOWER(:terme)')
                ->setParameter('terme', '%' . $terme . '%')
                ->setMaxResults(50)
                ->orderBy('d.nomCommune', 'ASC');
            
            if (!empty($type) && $type !== 'all') {
                $queryBuilder->andWhere('d.type = :type')
                    ->setParameter('type', $type);
            }
            
            $divisions = $queryBuilder->getQuery()->getResult();
            
            $results = [];
            foreach ($divisions as $division) {
                $typeResult = $this->determineTypeFromSearch($division, $terme);
                if ($typeResult) {
                    $results[] = $typeResult;
                }
            }
        }
        
        return $this->json([
            'success' => true,
            'results' => $results
        ]);
    }

    private function determineTypeFromSearch(DivisionAdministrative $division, string $terme): ?array
    {
        $terme = strtolower($terme);
        
        // Déterminer le type le plus pertinent selon ce qui a été recherché
        if ($division->getCodePostal() && stripos($division->getCodePostal(), $terme) !== false) {
            return [
                'nom' => $division->getCodePostal(),
                'type' => 'code_postal',
                'code' => $division->getCodePostal(),
                'description' => 'Code postal ' . $division->getCodePostal(),
                'details' => 'Code postal'
            ];
        } elseif ($division->getNomCommune() && stripos($division->getNomCommune(), $terme) !== false) {
            return [
                'nom' => $division->getNomCommune(),
                'type' => 'commune',
                'code' => $division->getCodeInseeCommune(),
                'description' => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')',
                'details' => 'Commune'
            ];
        } elseif ($division->getNomEpci() && stripos($division->getNomEpci(), $terme) !== false) {
            return [
                'nom' => $division->getNomEpci(),
                'type' => 'epci',
                'code' => $division->getCodeEpci(),
                'description' => $division->getNomEpci(),
                'details' => 'EPCI'
            ];
        } elseif ($division->getNomDepartement() && stripos($division->getNomDepartement(), $terme) !== false) {
            return [
                'nom' => $division->getNomDepartement(),
                'type' => 'departement',
                'code' => $division->getCodeDepartement(),
                'description' => $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')',
                'details' => 'Département'
            ];
        } elseif ($division->getNomRegion() && stripos($division->getNomRegion(), $terme) !== false) {
            return [
                'nom' => $division->getNomRegion(),
                'type' => 'region',
                'code' => $division->getCodeRegion(),
                'description' => $division->getNomRegion(),
                'details' => 'Région'
            ];
        }
        
        return null;
    }

    #[Route('/divisions-administratives/search', name: 'app_admin_divisions_search', methods: ['GET'])]
    public function searchDivisions(Request $request): JsonResponse
    {
        return $this->rechercherDivisions($request);
    }

    // ================================
    // AUTRES MÉTHODES...
    // ================================
}