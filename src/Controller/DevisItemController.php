<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Entity\DevisItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/devis/{devisId}/items')]
class DevisItemController extends AbstractController
{
    #[Route('/add', name: 'app_devis_item_add', methods: ['POST'])]
    public function addItem(int $devisId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Log pour debugging
            error_log("DevisItemController::addItem - Début pour devis $devisId");
            
            $devis = $entityManager->getRepository(Devis::class)->find($devisId);
            
            if (!$devis) {
                error_log("DevisItemController::addItem - Devis $devisId non trouvé");
                return $this->json(['error' => 'Devis non trouvé'], 404);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                error_log("DevisItemController::addItem - Données JSON invalides");
                return $this->json(['error' => 'Données JSON invalides'], 400);
            }
            
            error_log("DevisItemController::addItem - Données reçues: " . json_encode($data));

            $item = new DevisItem();
            $item->setDevis($devis);
            $item->setDesignation($data['designation'] ?? 'Nouvelle ligne');
            $item->setDescription($data['description'] ?? null);
            $item->setQuantite($data['quantite'] ?? '1.00');
            $item->setPrixUnitaireHt($data['prixUnitaireHt'] ?? '0.00');
            $item->setRemisePercent(!empty($data['remisePercent']) ? $data['remisePercent'] : null);
            $item->setTvaPercent($data['tvaPercent'] ?? '20.00');
            
            // Définir l'ordre d'affichage
            $maxOrder = $entityManager->createQuery(
                'SELECT MAX(di.ordreAffichage) FROM App\Entity\DevisItem di WHERE di.devis = :devis'
            )->setParameter('devis', $devis)->getSingleScalarResult();
            
            $item->setOrdreAffichage(($maxOrder ?? 0) + 1);

            $entityManager->persist($item);
            $entityManager->flush();
            
            error_log("DevisItemController::addItem - Item créé avec ID: " . $item->getId());

            // Recalculer les totaux du devis
            $devis->calculateTotals();
            $entityManager->flush();
            
            error_log("DevisItemController::addItem - Totaux recalculés");

            return $this->json([
                'success' => true,
                'id' => $item->getId(),
                'designation' => $item->getDesignation(),
                'description' => $item->getDescription(),
                'quantite' => $item->getQuantite(),
                'prixUnitaireHt' => $item->getPrixUnitaireHt(),
                'remisePercent' => $item->getRemisePercent(),
                'tvaPercent' => $item->getTvaPercent(),
                'totalLigneHt' => $item->getTotalLigneHt(),
                'totalLigneTtc' => $item->getTotalLigneTtc(),
                'ordreAffichage' => $item->getOrdreAffichage(),
                'devisTotals' => [
                    'totalHt' => $devis->getTotalHt(),
                    'totalTva' => $devis->getTotalTva(),
                    'totalTtc' => $devis->getTotalTtc()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'ajout: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{itemId}/update', name: 'app_devis_item_update', methods: ['PUT'])]
    public function updateItem(int $devisId, int $itemId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $item = $entityManager->getRepository(DevisItem::class)->find($itemId);
        
        if (!$item || $item->getDevis()->getId() !== $devisId) {
            return $this->json(['error' => 'Ligne non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['designation'])) $item->setDesignation($data['designation']);
        if (isset($data['description'])) $item->setDescription($data['description']);
        if (isset($data['quantite'])) $item->setQuantite($data['quantite']);
        if (isset($data['prixUnitaireHt'])) $item->setPrixUnitaireHt($data['prixUnitaireHt']);
        if (isset($data['remisePercent'])) $item->setRemisePercent($data['remisePercent']);
        if (isset($data['tvaPercent'])) $item->setTvaPercent($data['tvaPercent']);

        $item->setUpdatedAt(new \DateTimeImmutable());
        
        $entityManager->flush();

        // Recalculer les totaux du devis
        $item->getDevis()->calculateTotals();
        $entityManager->flush();

        return $this->json([
            'id' => $item->getId(),
            'designation' => $item->getDesignation(),
            'description' => $item->getDescription(),
            'quantite' => $item->getQuantite(),
            'prixUnitaireHt' => $item->getPrixUnitaireHt(),
            'remisePercent' => $item->getRemisePercent(),
            'tvaPercent' => $item->getTvaPercent(),
            'totalLigneHt' => $item->getTotalLigneHt(),
            'totalLigneTtc' => $item->getTotalLigneTtc(),
            'devisTotals' => [
                'totalHt' => $item->getDevis()->getTotalHt(),
                'totalTva' => $item->getDevis()->getTotalTva(),
                'totalTtc' => $item->getDevis()->getTotalTtc()
            ]
        ]);
    }

    #[Route('/{itemId}/delete', name: 'app_devis_item_delete', methods: ['DELETE'])]
    public function deleteItem(int $devisId, int $itemId, EntityManagerInterface $entityManager): JsonResponse
    {
        $item = $entityManager->getRepository(DevisItem::class)->find($itemId);
        
        if (!$item || $item->getDevis()->getId() !== $devisId) {
            return $this->json(['error' => 'Ligne non trouvée'], 404);
        }

        $devis = $item->getDevis();
        $entityManager->remove($item);
        $entityManager->flush();

        // Recalculer les totaux du devis
        $devis->calculateTotals();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'devisTotals' => [
                'totalHt' => $devis->getTotalHt(),
                'totalTva' => $devis->getTotalTva(),
                'totalTtc' => $devis->getTotalTtc()
            ]
        ]);
    }

    #[Route('/reorder', name: 'app_devis_item_reorder', methods: ['POST'])]
    public function reorderItems(int $devisId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $devis = $entityManager->getRepository(Devis::class)->find($devisId);
        
        if (!$devis) {
            return $this->json(['error' => 'Devis non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $itemIds = $data['itemIds'] ?? [];

        foreach ($itemIds as $index => $itemId) {
            $item = $entityManager->getRepository(DevisItem::class)->find($itemId);
            if ($item && $item->getDevis()->getId() === $devisId) {
                $item->setOrdreAffichage($index + 1);
            }
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}