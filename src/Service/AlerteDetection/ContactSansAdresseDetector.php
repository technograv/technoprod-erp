<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\Contact;

class ContactSansAdresseDetector extends AbstractAlerteDetector
{
    public function detect(AlerteType $alerteType): array
    {
        $contactsQuery = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Contact::class, 'c')
            ->leftJoin('c.adresses', 'adresse')
            ->leftJoin('c.client', 'client')
            ->where('adresse.id IS NULL')
            ->andWhere('client.actif = true')
            ->getQuery();

        $contacts = $contactsQuery->getResult();
        $instances = [];

        foreach ($contacts as $contact) {
            if (!$this->instanceExists($alerteType, $contact->getId())) {
                $instances[] = $this->createInstance($alerteType, $contact->getId(), [
                    'contact_nom' => $contact->getNom(),
                    'contact_prenom' => $contact->getPrenom(),
                    'client_nom' => $contact->getClient()?->getNom(),
                ]);
            }
        }

        return $instances;
    }

    public function getEntityType(): string
    {
        return Contact::class;
    }

    public function getName(): string
    {
        return 'Contact sans adresse';
    }

    public function getDescription(): string
    {
        return 'Détecte les contacts qui n\'ont aucune adresse associée';
    }
}