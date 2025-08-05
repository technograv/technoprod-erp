<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Produit;
use App\Entity\Tag;
use App\Entity\Devis;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service d'assignation automatique des tags aux clients
 * basé sur les produits facturés/devisés
 */
class TagAssignmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Assigne automatiquement les tags d'un produit à un client
     * Appelé lors de la facturation ou validation d'un devis
     */
    public function assignTagsFromProduct(Client $client, Produit $produit): void
    {
        $tagsAssigned = [];
        
        // Récupérer tous les tags du produit avec assignation automatique
        $productTags = $produit->getTags()->filter(
            fn(Tag $tag) => $tag->isAssignationAutomatique() && $tag->isActif()
        );

        foreach ($productTags as $tag) {
            if (!$client->hasTag($tag)) {
                $client->addTag($tag);
                $tagsAssigned[] = $tag->getNom();
                
                $this->logger->info('Tag automatiquement assigné', [
                    'client_id' => $client->getId(),
                    'client_nom' => $client->getNom(),
                    'tag_nom' => $tag->getNom(),
                    'produit_reference' => $produit->getReference(),
                    'produit_designation' => $produit->getDesignation()
                ]);
            }
        }

        if (!empty($tagsAssigned)) {
            $this->entityManager->flush();
            
            $this->logger->info('Tags assignés automatiquement au client', [
                'client_id' => $client->getId(),
                'client_nom' => $client->getNom(),
                'tags_assigned' => $tagsAssigned,
                'total_tags' => count($tagsAssigned)
            ]);
        }
    }

    /**
     * Assigne les tags basés sur tous les produits d'un devis
     * Appelé lors de la signature ou validation du devis
     */
    public function assignTagsFromDevis(Devis $devis): void
    {
        $client = $devis->getClient();
        if (!$client) {
            return;
        }

        $this->logger->info('Début assignation tags depuis devis', [
            'devis_id' => $devis->getId(),
            'client_id' => $client->getId(),
            'client_nom' => $client->getNom()
        ]);

        foreach ($devis->getItems() as $devisItem) {
            $produit = $devisItem->getProduit();
            if ($produit) {
                $this->assignTagsFromProduct($client, $produit);
            }
        }
    }

    /**
     * Recalcule tous les tags d'un client basés sur son historique de commandes
     * Utile pour la migration ou la réassignation massive
     */
    public function recalculateClientTags(Client $client): void
    {
        $this->logger->info('Recalcul des tags client', [
            'client_id' => $client->getId(),
            'client_nom' => $client->getNom()
        ]);

        // Effacer les tags avec assignation automatique existants
        $tagsToRemove = $client->getTags()->filter(
            fn(Tag $tag) => $tag->isAssignationAutomatique()
        );
        
        foreach ($tagsToRemove as $tag) {
            $client->removeTag($tag);
        }

        // Recalculer basé sur tous les devis validés/signés
        foreach ($client->getDevis() as $devis) {
            if (in_array($devis->getStatut(), ['signe', 'termine', 'facture'])) {
                $this->assignTagsFromDevis($devis);
            }
        }
    }

    /**
     * Assigne manuellement un tag à un client (pour tags non-automatiques)
     */
    public function assignManualTag(Client $client, Tag $tag): bool
    {
        if ($client->hasTag($tag)) {
            return false; // Tag déjà assigné
        }

        $client->addTag($tag);
        $this->entityManager->flush();

        $this->logger->info('Tag assigné manuellement', [
            'client_id' => $client->getId(),
            'client_nom' => $client->getNom(),
            'tag_nom' => $tag->getNom()
        ]);

        return true;
    }

    /**
     * Supprime un tag d'un client
     */
    public function removeTagFromClient(Client $client, Tag $tag): bool
    {
        if (!$client->hasTag($tag)) {
            return false; // Tag non assigné
        }

        $client->removeTag($tag);
        $this->entityManager->flush();

        $this->logger->info('Tag supprimé du client', [
            'client_id' => $client->getId(),
            'client_nom' => $client->getNom(),
            'tag_nom' => $tag->getNom()
        ]);

        return true;
    }

    /**
     * Récupère les statistiques d'utilisation des tags
     */
    public function getTagStatistics(): array
    {
        $tags = $this->tagRepository->findAllOrdered();
        $statistics = [];

        foreach ($tags as $tag) {
            $clientCount = $tag->getClients()->count();
            $productCount = $tag->getProduits()->count();
            
            $statistics[] = [
                'tag' => $tag,
                'clients_count' => $clientCount,
                'products_count' => $productCount,
                'is_auto' => $tag->isAssignationAutomatique(),
                'usage_rate' => $productCount > 0 ? round(($clientCount / $productCount) * 100, 2) : 0
            ];
        }

        return $statistics;
    }

    /**
     * Trouve les clients suggérés pour un tag basé sur leurs achats
     */
    public function getSuggestedClientsForTag(Tag $tag): array
    {
        // Récupérer les produits associés à ce tag
        $produits = $tag->getProduits();
        
        if ($produits->isEmpty()) {
            return [];
        }

        $suggestions = [];
        
        // Pour chaque produit, trouver les clients qui l'ont acheté
        foreach ($produits as $produit) {
            foreach ($produit->getDevisItems() as $devisItem) {
                $devis = $devisItem->getDevis();
                $client = $devis->getClient();
                
                if ($client && !$client->hasTag($tag) && in_array($devis->getStatut(), ['signe', 'termine', 'facture'])) {
                    if (!isset($suggestions[$client->getId()])) {
                        $suggestions[$client->getId()] = [
                            'client' => $client,
                            'products_count' => 0,
                            'total_amount' => 0
                        ];
                    }
                    
                    $suggestions[$client->getId()]['products_count']++;
                    $suggestions[$client->getId()]['total_amount'] += $devisItem->getPrixUnitaire() * $devisItem->getQuantite();
                }
            }
        }

        // Trier par nombre de produits puis par montant
        uasort($suggestions, function($a, $b) {
            if ($a['products_count'] === $b['products_count']) {
                return $b['total_amount'] <=> $a['total_amount'];
            }
            return $b['products_count'] <=> $a['products_count'];
        });

        return array_values($suggestions);
    }
}