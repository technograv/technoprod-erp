<?php

namespace App\Controller\Api;

use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public')]
final class PublicApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/divisions-administratives/recherche', name: 'app_api_public_divisions_recherche', methods: ['GET'])]
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
                        'id' => $division->getId(),
                        'nom' => $codePostal,
                        'type' => 'code_postal',
                        'code' => $codePostal,
                        'valeur' => $codePostal,
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
            
            // Filtrage par type basé sur la présence des champs appropriés
            if (!empty($type) && $type !== 'all') {
                switch ($type) {
                    case 'commune':
                        $queryBuilder->andWhere('d.codeInseeCommune IS NOT NULL')
                            ->andWhere('d.nomCommune IS NOT NULL');
                        break;
                    case 'departement':
                        $queryBuilder->andWhere('d.codeDepartement IS NOT NULL')
                            ->andWhere('d.nomDepartement IS NOT NULL');
                        break;
                    case 'region':
                        $queryBuilder->andWhere('d.codeRegion IS NOT NULL')
                            ->andWhere('d.nomRegion IS NOT NULL');
                        break;
                    case 'epci':
                        $queryBuilder->andWhere('d.codeEpci IS NOT NULL')
                            ->andWhere('d.nomEpci IS NOT NULL');
                        break;
                    case 'code_postal':
                        $queryBuilder->andWhere('d.codePostal IS NOT NULL');
                        break;
                }
            }
            
            $divisions = $queryBuilder->getQuery()->getResult();
            
            $results = [];
            foreach ($divisions as $division) {
                $typeResult = $this->determineTypeFromSearch($division, $terme, $type);
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

    private function determineTypeFromSearch(DivisionAdministrative $division, string $terme, string $typeFiltre = ''): ?array
    {
        $terme = strtolower($terme);
        
        // Si un type spécifique est demandé, ne retourner que ce type
        if (!empty($typeFiltre) && $typeFiltre !== 'all') {
            switch ($typeFiltre) {
                case 'commune':
                    if ($division->getNomCommune() && stripos($division->getNomCommune(), $terme) !== false) {
                        return [
                            'id' => $division->getId(),
                            'nom' => $division->getNomCommune(),
                            'type' => 'commune',
                            'code' => $division->getCodeInseeCommune(),
                            'valeur' => $division->getCodeInseeCommune(),
                            'description' => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')',
                            'details' => 'Commune'
                        ];
                    }
                    return null;
                    
                case 'epci':
                    if ($division->getNomEpci() && stripos($division->getNomEpci(), $terme) !== false) {
                        return [
                            'id' => $division->getId(),
                            'nom' => $division->getNomEpci(),
                            'type' => 'epci',
                            'code' => $division->getCodeEpci(),
                            'valeur' => $division->getCodeEpci(),
                            'description' => $division->getNomEpci(),
                            'details' => 'EPCI'
                        ];
                    }
                    return null;
                    
                case 'departement':
                    if ($division->getNomDepartement() && stripos($division->getNomDepartement(), $terme) !== false) {
                        return [
                            'id' => $division->getId(),
                            'nom' => $division->getNomDepartement(),
                            'type' => 'departement',
                            'code' => $division->getCodeDepartement(),
                            'valeur' => $division->getCodeDepartement(),
                            'description' => $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')',
                            'details' => 'Département'
                        ];
                    }
                    return null;
                    
                case 'region':
                    if ($division->getNomRegion() && stripos($division->getNomRegion(), $terme) !== false) {
                        return [
                            'id' => $division->getId(),
                            'nom' => $division->getNomRegion(),
                            'type' => 'region',
                            'code' => $division->getCodeRegion(),
                            'valeur' => $division->getCodeRegion(),
                            'description' => $division->getNomRegion(),
                            'details' => 'Région'
                        ];
                    }
                    return null;
                    
                case 'code_postal':
                    if ($division->getCodePostal() && stripos($division->getCodePostal(), $terme) !== false) {
                        return [
                            'id' => $division->getId(),
                            'nom' => $division->getCodePostal(),
                            'type' => 'code_postal',
                            'code' => $division->getCodePostal(),
                            'valeur' => $division->getCodePostal(),
                            'description' => 'Code postal ' . $division->getCodePostal(),
                            'details' => 'Code postal'
                        ];
                    }
                    return null;
            }
        }
        
        // Logique originale si aucun filtre spécifique ou filtre "all"
        if ($division->getCodePostal() && stripos($division->getCodePostal(), $terme) !== false) {
            return [
                'id' => $division->getId(),
                'nom' => $division->getCodePostal(),
                'type' => 'code_postal',
                'code' => $division->getCodePostal(),
                'valeur' => $division->getCodePostal(),
                'description' => 'Code postal ' . $division->getCodePostal(),
                'details' => 'Code postal'
            ];
        } elseif ($division->getNomCommune() && stripos($division->getNomCommune(), $terme) !== false) {
            return [
                'id' => $division->getId(),
                'nom' => $division->getNomCommune(),
                'type' => 'commune',
                'code' => $division->getCodeInseeCommune(),
                'valeur' => $division->getCodeInseeCommune(),
                'description' => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')',
                'details' => 'Commune'
            ];
        } elseif ($division->getNomEpci() && stripos($division->getNomEpci(), $terme) !== false) {
            return [
                'id' => $division->getId(),
                'nom' => $division->getNomEpci(),
                'type' => 'epci',
                'code' => $division->getCodeEpci(),
                'valeur' => $division->getCodeEpci(),
                'description' => $division->getNomEpci(),
                'details' => 'EPCI'
            ];
        } elseif ($division->getNomDepartement() && stripos($division->getNomDepartement(), $terme) !== false) {
            return [
                'id' => $division->getId(),
                'nom' => $division->getNomDepartement(),
                'type' => 'departement',
                'code' => $division->getCodeDepartement(),
                'valeur' => $division->getCodeDepartement(),
                'description' => $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')',
                'details' => 'Département'
            ];
        } elseif ($division->getNomRegion() && stripos($division->getNomRegion(), $terme) !== false) {
            return [
                'id' => $division->getId(),
                'nom' => $division->getNomRegion(),
                'type' => 'region',
                'code' => $division->getCodeRegion(),
                'valeur' => $division->getCodeRegion(),
                'description' => $division->getNomRegion(),
                'details' => 'Région'
            ];
        }
        
        return null;
    }
}