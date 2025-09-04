<?php

namespace App\Entity;

use App\Repository\AlerteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Alerte - Système d'alertes paramétrables pour les utilisateurs
 * 
 * Permet aux administrateurs de créer des alertes ciblées par rôle
 * avec gestion de l'expiration et fermeture individuelle par utilisateur.
 * 
 * @author TechnoProd System
 * @version 2.2
 */
#[ORM\Entity(repositoryClass: AlerteRepository::class)]
class Alerte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // Types: info (bleu), warning (orange), danger (rouge), success (vert)

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateExpiration = null;

    #[ORM\Column]
    private ?int $ordre = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cibles = null; // Rôles ciblés: ROLE_ADMIN, ROLE_COMMERCIAL, etc.

    #[ORM\Column]
    private ?bool $dismissible = true; // Si false, alerte permanente non-fermable

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isActive = true;
        $this->dismissible = true;
        $this->ordre = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDateExpiration(): ?\DateTimeInterface
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?\DateTimeInterface $dateExpiration): static
    {
        $this->dateExpiration = $dateExpiration;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCibles(): ?array
    {
        return $this->cibles;
    }

    public function setCibles(?array $cibles): static
    {
        $this->cibles = $cibles;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isDismissible(): ?bool
    {
        return $this->dismissible;
    }

    public function setDismissible(bool $dismissible): static
    {
        $this->dismissible = $dismissible;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isExpired(): bool
    {
        if (!$this->dateExpiration) {
            return false;
        }
        return $this->dateExpiration < new \DateTime();
    }

    public function getTypeBootstrap(): string
    {
        return match($this->type) {
            'danger' => 'danger',
            'warning' => 'warning', 
            'success' => 'success',
            'info' => 'info',
            default => 'primary'
        };
    }

    public function getTypeIcon(): string
    {
        return match($this->type) {
            'danger' => 'fas fa-exclamation-triangle',
            'warning' => 'fas fa-exclamation-circle',
            'success' => 'fas fa-check-circle',
            'info' => 'fas fa-info-circle',
            default => 'fas fa-bell'
        };
    }
}
