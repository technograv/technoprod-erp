<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientLog;
use App\Entity\User;
use App\Entity\Contact;
use App\Entity\Adresse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ClientLoggerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log une action sur un client
     */
    public function log(Client $client, string $action, ?string $details = null, ?User $user = null): ClientLog
    {
        $log = new ClientLog();
        $log->setClient($client);
        $log->setAction($action);
        $log->setDetails($details);
        
        // Utiliser l'utilisateur fourni ou l'utilisateur actuel
        $logUser = $user ?? $this->security->getUser();
        if ($logUser instanceof User) {
            $log->setUser($logUser);
        }
        
        // Capturer l'adresse IP si disponible
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }
        
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        
        return $log;
    }

    /**
     * Log la création d'un client
     */
    public function logCreated(Client $client, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Client %s créé - %s (%s)',
            $client->getCode(),
            $client->getNomComplet(),
            $client->getStatut()
        );
        
        return $this->log($client, 'created', $details, $user);
    }

    /**
     * Log la modification d'un client
     */
    public function logUpdated(Client $client, ?array $changes = null, ?User $user = null): ClientLog
    {
        $details = null;
        
        if ($changes) {
            $changeDetails = [];
            foreach ($changes as $field => $change) {
                if (isset($change['old']) && isset($change['new'])) {
                    $changeDetails[] = sprintf(
                        '%s: %s → %s',
                        $field,
                        $change['old'],
                        $change['new']
                    );
                }
            }
            if (!empty($changeDetails)) {
                $details = implode(', ', $changeDetails);
            }
        }
        
        if (!$details) {
            $details = 'Informations du client modifiées';
        }
        
        return $this->log($client, 'updated', $details, $user);
    }

    /**
     * Log l'ajout d'un contact
     */
    public function logContactAdded(Client $client, Contact $contact, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Contact ajouté %s',
            $contact->getNomComplet()
        );
        
        return $this->log($client, 'Contact modifié', $details, $user);
    }

    /**
     * Log la modification d'un contact
     */
    public function logContactUpdated(Client $client, Contact $contact, ?array $changes = null, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Contact modifié %s',
            $contact->getNomComplet()
        );
        
        return $this->log($client, 'Contact modifié', $details, $user);
    }

    /**
     * Log la suppression d'un contact
     */
    public function logContactDeleted(Client $client, string $contactName, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Contact supprimé %s',
            $contactName
        );

        return $this->log($client, 'Contact modifié', $details, $user);
    }

    /**
     * Log l'archivage d'un contact
     */
    public function logContactArchived(Client $client, string $contactName, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Contact archivé %s',
            $contactName
        );

        return $this->log($client, 'Contact archivé', $details, $user);
    }

    /**
     * Log le changement de contact par défaut
     */
    public function logContactDefaultChanged(Client $client, Contact $contact, string $type, ?User $user = null): ClientLog
    {
        $typeLabel = $type === 'facturation' ? 'facturation' : 'livraison';
        $details = sprintf(
            'Contact par défaut %s défini: %s',
            $typeLabel,
            $contact->getNomComplet()
        );
        
        return $this->log($client, 'contact_default_changed', $details, $user);
    }

    /**
     * Log l'ajout d'une adresse
     */
    public function logAddressAdded(Client $client, Adresse $adresse, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Adresse ajoutée %s',
            $adresse->getNom()
        );
        
        return $this->log($client, 'Adresse modifiée', $details, $user);
    }

    /**
     * Log la modification d'une adresse
     */
    public function logAddressUpdated(Client $client, Adresse $adresse, ?array $changes = null, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Adresse modifiée %s',
            $adresse->getNom()
        );
        
        return $this->log($client, 'Adresse modifiée', $details, $user);
    }

    /**
     * Log la suppression d'une adresse
     */
    public function logAddressDeleted(Client $client, string $addressName, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Adresse supprimée %s',
            $addressName
        );
        
        return $this->log($client, 'Adresse modifiée', $details, $user);
    }

    /**
     * Log l'assignation d'une adresse à un contact
     */
    public function logAddressAssigned(Client $client, Contact $contact, Adresse $adresse, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Adresse %s assignée au contact %s',
            $adresse->getNom(),
            $contact->getNomComplet()
        );
        
        return $this->log($client, 'Adresse modifiée', $details, $user);
    }

    /**
     * Log la conversion prospect vers client
     */
    public function logConvertedToClient(Client $client, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Prospect %s converti en client',
            $client->getCode()
        );
        
        return $this->log($client, 'converted_to_client', $details, $user);
    }

    /**
     * Log l'archivage d'un client
     */
    public function logArchived(Client $client, ?string $reason = null, ?User $user = null): ClientLog
    {
        $details = 'Client archivé';
        if ($reason) {
            $details .= ' - Raison: ' . $reason;
        }
        
        return $this->log($client, 'archived', $details, $user);
    }

    /**
     * Log le désarchivage d'un client
     */
    public function logUnarchived(Client $client, ?User $user = null): ClientLog
    {
        $details = 'Client réactivé';
        
        return $this->log($client, 'unarchived', $details, $user);
    }

    /**
     * Log le changement de statut
     */
    public function logStatusChanged(Client $client, string $oldStatus, string $newStatus, ?User $user = null): ClientLog
    {
        $statusLabels = [
            'prospect' => 'Prospect',
            'client' => 'Client'
        ];
        
        $details = sprintf(
            'Statut modifié: %s → %s',
            $statusLabels[$oldStatus] ?? $oldStatus,
            $statusLabels[$newStatus] ?? $newStatus
        );
        
        return $this->log($client, 'status_changed', $details, $user);
    }

    /**
     * Log la modification de la forme juridique
     */
    public function logFormeJuridiqueChanged(Client $client, ?string $oldValue, ?string $newValue, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Forme juridique modifiée %s → %s',
            $oldValue ?: 'Aucune',
            $newValue ?: 'Aucune'
        );
        
        return $this->log($client, 'Informations générales modifiées', $details, $user);
    }

    /**
     * Log la modification de la dénomination
     */
    public function logDenominationChanged(Client $client, ?string $oldValue, ?string $newValue, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Dénomination modifiée "%s" → "%s"',
            $oldValue ?: '',
            $newValue ?: ''
        );
        
        return $this->log($client, 'Informations générales modifiées', $details, $user);
    }

    /**
     * Log la modification du mode de règlement
     */
    public function logModeReglementChanged(Client $client, ?string $oldValue, ?string $newValue, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Mode de règlement modifié %s → %s',
            $oldValue ?: 'Non défini',
            $newValue ?: 'Non défini'
        );

        return $this->log($client, 'Informations générales modifiées', $details, $user);
    }

    /**
     * Log la modification des conditions tarifaires
     */
    public function logConditionsTarifsChanged(Client $client, ?string $oldValue, ?string $newValue, ?User $user = null): ClientLog
    {
        $details = sprintf(
            'Conditions tarifaires modifiées %s → %s',
            $oldValue ?: 'Non définies',
            $newValue ?: 'Non définies'
        );
        
        return $this->log($client, 'Informations générales modifiées', $details, $user);
    }

    /**
     * Log la modification des notes
     */
    public function logNotesChanged(Client $client, ?string $oldValue, ?string $newValue, ?User $user = null): ClientLog
    {
        $details = 'Notes modifiées';
        
        return $this->log($client, 'Notes modifiées', $details, $user);
    }

    /**
     * Log la modification du contact de facturation par défaut
     */
    public function logContactFacturationDefaultChanged(Client $client, ?Contact $oldContact, ?Contact $newContact, ?User $user = null): ClientLog
    {
        $oldName = $oldContact ? $oldContact->getNomComplet() : 'Aucun';
        $newName = $newContact ? $newContact->getNomComplet() : 'Aucun';
        
        $details = sprintf(
            'Contact facturation par défaut modifié %s → %s',
            $oldName,
            $newName
        );
        
        return $this->log($client, 'Contact modifié', $details, $user);
    }

    /**
     * Log la modification du contact de livraison par défaut
     */
    public function logContactLivraisonDefaultChanged(Client $client, ?Contact $oldContact, ?Contact $newContact, ?User $user = null): ClientLog
    {
        $oldName = $oldContact ? $oldContact->getNomComplet() : 'Aucun';
        $newName = $newContact ? $newContact->getNomComplet() : 'Aucun';
        
        $details = sprintf(
            'Contact livraison par défaut modifié %s → %s',
            $oldName,
            $newName
        );
        
        return $this->log($client, 'Contact modifié', $details, $user);
    }

    /**
     * Récupère tous les logs d'un client
     */
    public function getClientLogs(Client $client): array
    {
        return $this->entityManager->getRepository(ClientLog::class)
            ->findByClient($client);
    }

    /**
     * Compte le nombre total de logs pour un client
     */
    public function countClientLogs(Client $client): int
    {
        return $this->entityManager->getRepository(ClientLog::class)
            ->countByClient($client);
    }
}