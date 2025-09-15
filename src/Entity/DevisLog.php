<?php

namespace App\Entity;

use App\Repository\DevisLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DevisLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

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

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;
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
            'created' => 'Devis créé',
            'updated' => 'Devis modifié',
            'sent' => 'Devis envoyé',
            'resent' => 'Devis renvoyé',
            'signed' => 'Devis signé',
            'version_created' => 'Version créée',
            'status_changed' => 'Statut modifié',
            'converted_to_order' => 'Transféré en commande',
            'converted_to_invoice' => 'Transféré en facture',
            'payment_received' => 'Paiement reçu',
            'cancelled' => 'Devis annulé'
        ];

        return $actionLabels[$this->action] ?? $this->action;
    }
}
