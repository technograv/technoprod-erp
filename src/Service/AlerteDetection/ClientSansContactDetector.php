<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\Client;

class ClientSansContactDetector extends AbstractAlerteDetector
{
    public function detect(AlerteType $alerteType): array
    {
        // Détecter les clients qui n'ont AUCUN contact actif
        // LEFT JOIN pour trouver ceux sans contact actif
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->leftJoin('c.contacts', 'contact', 'WITH', 'contact.actif = true')
            ->groupBy('c.id')
            ->having('COUNT(contact.id) = 0');

        $clients = $qb->getQuery()->getResult();

        $instances = [];
        $currentEntityIds = [];

        foreach ($clients as $client) {
            $currentEntityIds[] = $client->getId();

            if (!$this->instanceExists($alerteType, $client->getId())) {
                // Récupérer le nom : priorité nomEntreprise, sinon "Client #ID"
                $clientNom = $client->getNomEntreprise() ?: 'Client #' . $client->getId();

                $instances[] = $this->createInstance($alerteType, $client->getId(), [
                    'client_nom' => $clientNom,
                    'client_code' => $client->getCode(),
                    'client_statut' => $client->getStatut(),
                ]);
            }
        }

        // Résoudre automatiquement les alertes obsolètes
        $this->resolveObsoleteInstances($alerteType, $currentEntityIds);

        return $instances;
    }

    public function getEntityType(): string
    {
        return Client::class;
    }

    protected function generateMessage(int $entityId, array $metadata): string
    {
        $clientNom = $metadata['client_nom'] ?? 'Client inconnu';
        return 'Client "' . $clientNom . '" n\'a aucun contact actif';
    }

    public function getName(): string
    {
        return 'Client sans contact';
    }

    public function getDescription(): string
    {
        return 'Détecte les clients et prospects qui n\'ont aucun contact actif';
    }
}