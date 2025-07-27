<?php

namespace App\Controller;

use App\Entity\CommuneFrancaise;
use App\Repository\ZoneRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/zones', name: 'api_zones', methods: ['GET'])]
    public function getZones(ZoneRepository $zoneRepository): JsonResponse
    {
        $zones = $zoneRepository->findBy([], ['codePostal' => 'ASC']);
        
        $data = array_map(function($zone) {
            return [
                'id' => $zone->getId(),
                'codePostal' => $zone->getCodePostal(),
                'ville' => $zone->getVille(),
                'departement' => $zone->getDepartement(),
                'region' => $zone->getRegion()
            ];
        }, $zones);
        
        return $this->json($data);
    }

    #[Route('/produits', name: 'api_produits', methods: ['GET'])]
    public function getProduits(ProduitRepository $produitRepository): JsonResponse
    {
        $produits = $produitRepository->findBy(['actif' => true], ['designation' => 'ASC']);
        
        $data = array_map(function($produit) {
            return [
                'id' => $produit->getId(),
                'designation' => $produit->getDesignation(),
                'description' => $produit->getDescription(),
                'reference' => $produit->getReference(),
                'prixUnitaireHt' => $produit->getPrixVenteHt(),
                'tvaPercent' => $produit->getTvaPercent(),
                'unite' => $produit->getUnite(),
                'categorie' => $produit->getCategorie()
            ];
        }, $produits);
        
        return $this->json($data);
    }

    #[Route('/test-auth', name: 'api_test_auth', methods: ['GET'])]
    public function testAuth(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'authenticated' => $user !== null,
            'user' => $user ? $user->getEmail() : null,
            'timestamp' => new \DateTime()
        ]);
    }

    #[Route('/communes/search', name: 'api_communes_search', methods: ['GET'])]
    public function searchCommunes(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        
        $communes = $entityManager->getRepository(CommuneFrancaise::class)
            ->searchForAutocomplete($query, 20);
        
        $results = [];
        foreach ($communes as $commune) {
            $results[] = [
                'id' => $commune->getId(),
                'text' => $commune->getCodePostal() . ' ' . $commune->getNomCommune(),
                'codePostal' => $commune->getCodePostal(),
                'nomCommune' => $commune->getNomCommune(),
                'nomDepartement' => $commune->getNomDepartement()
            ];
        }
        
        return $this->json($results);
    }
}