<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\Client;

class ClientSansContactDetector extends AbstractAlerteDetector
{
    public function detect(AlerteType $alerteType): array
    {
        $clientsQuery = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->leftJoin('c.contacts', 'contact')
            ->where('contact.id IS NULL')
            ->andWhere('c.actif = true')
            ->getQuery();

        $clients = $clientsQuery->getResult();
        $instances = [];

        foreach ($clients as $client) {
            if (!$this->instanceExists($alerteType, $client->getId())) {
                $instances[] = $this->createInstance($alerteType, $client->getId(), [
                    'client_nom' => $client->getNom(),
                    'client_type' => $client->getType(),
                ]);
            }
        }

        return $instances;
    }

    public function getEntityType(): string
    {
        return Client::class;
    }

    public function getName(): string
    {
        return 'Client sans contact';
    }

    public function getDescription(): string
    {
        return 'Détecte les clients actifs qui n\'ont aucun contact associé';
    }
}