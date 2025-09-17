<?php

namespace App\Controller;

use App\Entity\CommuneFrancaise;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/public')]
class PublicController extends AbstractController
{
    #[Route('/api/communes/search', name: 'public_api_communes_search', methods: ['GET'])]
    public function searchCommunes(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $postal = $request->query->get('postal', '');
        $ville = $request->query->get('ville', '');
        
        // Si on a un paramètre postal spécifique
        if ($postal) {
            if (strlen($postal) < 2) {
                return $this->json(['communes' => []]);
            }
            
            $communes = $entityManager->getRepository(CommuneFrancaise::class)
                ->findBy(['codePostal' => $postal], null, 10);
            
            $results = [];
            foreach ($communes as $commune) {
                $results[] = [
                    'id' => $commune->getId(),
                    'nom' => $commune->getNomCommune(),
                    'codePostal' => $commune->getCodePostal(),
                    'nomCommune' => $commune->getNomCommune(),
                    'nomDepartement' => $commune->getNomDepartement()
                ];
            }
            
            return $this->json(['communes' => $results]);
        }
        
        // Si on a un paramètre ville spécifique
        if ($ville) {
            if (strlen($ville) < 3) {
                return $this->json(['communes' => []]);
            }
            
            $communes = $entityManager->getRepository(CommuneFrancaise::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nomCommune) LIKE LOWER(:ville)')
                ->setParameter('ville', '%' . $ville . '%')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
            
            $results = [];
            foreach ($communes as $commune) {
                $results[] = [
                    'id' => $commune->getId(),
                    'nom' => $commune->getNomCommune(),
                    'codePostal' => $commune->getCodePostal(),
                    'nomCommune' => $commune->getNomCommune(),
                    'nomDepartement' => $commune->getNomDepartement()
                ];
            }
            
            return $this->json(['communes' => $results]);
        }
        
        return $this->json(['communes' => []]);
    }
}