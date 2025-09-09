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
            
            // Log pour debug
            error_log('DevisElementController::create - Data reçue: ' . json_encode($data));
            
            if (!$data || !isset($data['type'])) {
                return $this->json(['success' => false, 'message' => 'Type d\'élément requis'], 400);
            }

            $element = new DevisElement();
            $element->setDevis($devis);
            $element->setType($data['type']);
            
            // Position : insérer à la position demandée ou à la fin
            if (isset($data['position'])) {
                // Position spécifique demandée - faire de la place et utiliser cette position
                $requestedPosition = (int)$data['position'];
                error_log("Position demandée: $requestedPosition - Faire de la place et assigner cette position");
                $em->getRepository(DevisElement::class)->makeSpaceAtPosition($devis, $requestedPosition);
                $element->setPosition($requestedPosition);
            } else {
                // Pas de position spécifiée - ajouter à la fin
                $nextPosition = $em->getRepository(DevisElement::class)->getNextPositionForDevis($devis);
                error_log("Pas de position spécifiée - Ajouter à la fin position: $nextPosition");
                $element->setPosition($nextPosition);
            }

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
            
            // Log pour debug
            error_log('🔄 DevisElementController::update - Element ID: ' . $elementId);
            error_log('📤 Données reçues: ' . json_encode($data));
            error_log('🏷️ Type élément: ' . $element->getType());
            
            if (!$data) {
                return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
            }

            // Mise à jour selon le type
            if ($element->isProductElement()) {
                $this->fillProductData($element, $data, $em);
                $element->calculateTotal();
            } else {
                error_log('📝 Mise à jour layout element avec fillLayoutData');
                $this->fillLayoutData($element, $data);
                error_log('✅ Après fillLayoutData - Titre: ' . $element->getTitre());
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

        // Ne mettre à jour que les champs fournis dans $data
        if (array_key_exists('designation', $data)) {
            $element->setDesignation($data['designation']);
        }
        if (array_key_exists('description', $data)) {
            $element->setDescription($data['description']);
        }
        if (array_key_exists('quantite', $data)) {
            $element->setQuantite($data['quantite'] ?: '1');
        }
        if (array_key_exists('prix_unitaire_ht', $data)) {
            $element->setPrixUnitaireHt($data['prix_unitaire_ht'] ?: '0.00');
        }
        if (array_key_exists('remise_percent', $data)) {
            $element->setRemisePercent($data['remise_percent'] ?: '0.00');
        }
        if (array_key_exists('tva_percent', $data)) {
            $element->setTvaPercent($data['tva_percent'] ?: '20.00');
        }

        $element->calculateTotal();
    }

    private function fillLayoutData(DevisElement $element, array $data): void
    {
        error_log('🔧 fillLayoutData - Avant modification:');
        error_log('   - Titre actuel: ' . ($element->getTitre() ?? 'null'));
        error_log('   - Données titre: ' . ($data['titre'] ?? 'null'));
        error_log('   - Type élément: ' . $element->getType());

        $element->setTitre($data['titre'] ?? null);
        $element->setContenu($data['contenu'] ?? null);
        $element->setParametres($data['parametres'] ?? []);

        error_log('🔧 fillLayoutData - Après setTitre: ' . ($element->getTitre() ?? 'null'));

        // Titre par défaut selon le type
        if (!$element->getTitre()) {
            $defaultTitle = match($element->getType()) {
                'section_title' => 'Nouveau titre',
                'subtotal' => 'Sous-total',
                default => null
            };
            $element->setTitre($defaultTitle);
            error_log('🔧 fillLayoutData - Titre par défaut appliqué: ' . ($defaultTitle ?? 'null'));
        }

        error_log('🔧 fillLayoutData - Titre final: ' . ($element->getTitre() ?? 'null'));
    }
}