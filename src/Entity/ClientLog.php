<?php

namespace App\Entity;

use App\Repository\ClientLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ClientLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 100)]
    private ?string $action = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    /**
     * Retourne le nom complet de l'utilisateur ou "Système" si pas d'utilisateur
     */
    public function getUserName(): string
    {
        if (!$this->user) {
            return 'Système';
        }
        
        return $this->user->getPrenom() . ' ' . $this->user->getNom();
    }

    /**
     * Retourne une description formatée de l'action
     */
    public function getFormattedAction(): string
    {
        $actionLabels = [
            'created' => 'Client créé',
            'updated' => 'Client modifié',
            'contact_added' => 'Contact ajouté',
            'contact_updated' => 'Contact modifié',
            'contact_deleted' => 'Contact supprimé',
            'contact_default_changed' => 'Contact par défaut modifié',
            'address_added' => 'Adresse ajoutée',
            'address_updated' => 'Adresse modifiée',
            'address_deleted' => 'Adresse supprimée',
            'address_assigned' => 'Adresse assignée à un contact',
            'converted_to_client' => 'Prospect converti en client',
            'archived' => 'Client archivé',
            'unarchived' => 'Client désarchivé',
            'status_changed' => 'Statut modifié'
        ];

        return $actionLabels[$this->action] ?? $this->action;
    }
}