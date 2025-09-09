<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\DevisElement;
use App\Entity\Produit;
use App\Repository\DevisElementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/devis/{devisId}/element', name: 'app_devis_element_')]
class DevisElementController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        int $devisId,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $devis = $em->getRepository(Devis::class)->find($devisId);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['type'])) {
                return $this->json(['success' => false, 'message' => 'Type d\'élément requis'], 400);
            }

            $element = new DevisElement();
            $element->setDevis($devis);
            $element->setType($data['type']);
            
            // Position : insérer à la position demandée ou à la fin
            $position = $data['position'] ?? $em->getRepository(DevisElement::class)->getNextPositionForDevis($devis);
            
            if (isset($data['position']) && $data['position'] < $position) {
                // Faire de la place à la position demandée
                $em->getRepository(DevisElement::class)->makeSpaceAtPosition($devis, $data['position']);
            }
            
            $element->setPosition($position);

            // Remplir les champs selon le type
            if ($data['type'] === 'product') {
                $this->fillProductData($element, $data, $em);
            } else {
                $this->fillLayoutData($element, $data);
            }

            $em->persist($element);
            $em->flush();

            return $this->json([
                'success' => true,
                'element' => $element->toArray(),
                'message' => 'Élément créé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{elementId}', name: 'update', methods: ['PUT'])]
    public function update(
        int $devisId,
        int $elementId,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $element = $em->getRepository(DevisElement::class)->find($elementId);
        
        if (!$element || $element->getDevis()->getId() !== $devisId) {
            return $this->json(['success' => false, 'message' => 'Élément introuvable'], 404);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
            }

            // Mise à jour selon le type
            if ($element->isProductElement()) {
                $this->fillProductData($element, $data, $em);
                $element->calculateTotal();
            } else {
                $this->fillLayoutData($element, $data);
            }

            $em->flush();

            return $this->json([
                'success' => true,
                'element' => $element->toArray(),
                'message' => 'Élément mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{elementId}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        int $devisId,
        int $elementId,
        EntityManagerInterface $em
    ): JsonResponse {
        $element = $em->getRepository(DevisElement::class)->find($elementId);
        
        if (!$element || $element->getDevis()->getId() !== $devisId) {
            return $this->json(['success' => false, 'message' => 'Élément introuvable'], 404);
        }

        try {
            $em->getRepository(DevisElement::class)->removeAndCompact($element);

            return $this->json([
                'success' => true,
                'message' => 'Élément supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(
        int $devisId,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $devis = $em->getRepository(Devis::class)->find($devisId);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['elementIds']) || !is_array($data['elementIds'])) {
                return $this->json(['success' => false, 'message' => 'Liste des IDs requise'], 400);
            }

            $updated = $em->getRepository(DevisElement::class)->updateElementsOrder($devis, $data['elementIds']);

            return $this->json([
                'success' => true,
                'message' => "$updated éléments réorganisés avec succès"
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la réorganisation: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        int $devisId,
        EntityManagerInterface $em
    ): JsonResponse {
        $devis = $em->getRepository(Devis::class)->find($devisId);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        $elements = $em->getRepository(DevisElement::class)->findByDevisOrdered($devis);
        
        return $this->json([
            'success' => true,
            'elements' => array_map(fn(DevisElement $element) => $element->toArray(), $elements),
            'count' => count($elements)
        ]);
    }

    #[Route('/subtotal/{position}', name: 'calculate_subtotal', methods: ['GET'])]
    public function calculateSubtotal(
        int $devisId,
        int $position,
        EntityManagerInterface $em
    ): JsonResponse {
        $devis = $em->getRepository(Devis::class)->find($devisId);
        
        if (!$devis) {
            return $this->json(['success' => false, 'message' => 'Devis introuvable'], 404);
        }

        $subtotal = $em->getRepository(DevisElement::class)->calculateSubtotalUpTo($devis, $position);

        return $this->json([
            'success' => true,
            'subtotal' => $subtotal,
            'subtotal_formatted' => number_format($subtotal, 2, ',', ' ') . ' €'
        ]);
    }

    private function fillProductData(DevisElement $element, array $data, EntityManagerInterface $em): void
    {
        if (isset($data['produit_id']) && $data['produit_id']) {
            $produit = $em->getRepository(Produit::class)->find($data['produit_id']);
            $element->setProduit($produit);
        }

        $element->setDesignation($data['designation'] ?? null);
        $element->setDescription($data['description'] ?? null);
        $element->setQuantite($data['quantite'] ?? '1');
        $element->setPrixUnitaireHt($data['prix_unitaire_ht'] ?? '0.00');
        $element->setRemisePercent($data['remise_percent'] ?? '0.00');
        $element->setTvaPercent($data['tva_percent'] ?? '20.00');

        $element->calculateTotal();
    }

    private function fillLayoutData(DevisElement $element, array $data): void
    {
        $element->setTitre($data['titre'] ?? null);
        $element->setContenu($data['contenu'] ?? null);
        $element->setParametres($data['parametres'] ?? []);

        // Titre par défaut selon le type
        if (!$element->getTitre()) {
            $element->setTitre(match($element->getType()) {
                'section_title' => 'Nouveau titre',
                'subtotal' => 'Sous-total',
                default => null
            });
        }
    }
}