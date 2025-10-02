<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\Contact;

class ContactSansAdresseDetector extends AbstractAlerteDetector
{
    public function detect(AlerteType $alerteType): array
    {
        // Détecter les contacts actifs qui n'ont pas d'adresse assignée
        $contactsQuery = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->where('c.actif = true')
            ->andWhere('c.adresse IS NULL')
            ->getQuery();

        $contacts = $contactsQuery->getResult();
        $instances = [];
        $currentEntityIds = [];

        foreach ($contacts as $contact) {
            $currentEntityIds[] = $contact->getId();

            if (!$this->instanceExists($alerteType, $contact->getId())) {
                $instances[] = $this->createInstance($alerteType, $contact->getId(), [
                    'contact_nom' => $contact->getNom(),
                    'contact_prenom' => $contact->getPrenom(),
                    'client_nom' => $contact->getClient()?->getNom(),
                    'client_id' => $contact->getClient()?->getId(),
                ]);
            }
        }

        // Résoudre automatiquement les alertes obsolètes
        $this->resolveObsoleteInstances($alerteType, $currentEntityIds);

        return $instances;
    }

    public function getEntityType(): string
    {
        return Contact::class;
    }

    protected function generateMessage(int $entityId, array $metadata): string
    {
        $contactName = $metadata['contact_prenom']
            ? $metadata['contact_prenom'] . ' ' . $metadata['contact_nom']
            : $metadata['contact_nom'];

        $clientNom = $metadata['client_nom'] ?? '';

        return 'Contact "' . $contactName . '"' .
            ($clientNom ? ' (Client: ' . $clientNom . ')' : '') .
            ' n\'a pas d\'adresse';
    }

    public function getName(): string
    {
        return 'Contact sans adresse';
    }

    public function getDescription(): string
    {
        return 'Détecte les contacts actifs qui n\'ont aucune adresse associée';
    }
}