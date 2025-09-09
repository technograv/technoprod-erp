<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/produit')]
#[IsGranted('ROLE_USER')]
class ProduitController extends AbstractController
{
    #[Route('/search', name: 'app_produit_search', methods: ['GET'])]
    public function search(Request $request, ProduitRepository $produitRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        $field = $request->query->get('field', 'designation');
        $limit = (int) $request->query->get('limit', 10);
        
        if (strlen($query) < 2) {
            return $this->json(['products' => []]);
        }
        
        $products = $produitRepository->searchByField($query, $field, $limit);
        
        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'id' => $product->getId(),
                'reference' => $product->getReference(),
                'designation' => $product->getDesignation(),
                'description' => $product->getDescription(),
                'prixVenteHt' => $product->getPrixVenteHt(),
                'tauxTva' => $product->getTvaPercent(),
                'type' => $product->getType()
            ];
        }
        
        return $this->json(['products' => $results]);
    }
}