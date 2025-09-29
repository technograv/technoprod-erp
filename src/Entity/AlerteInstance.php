<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'alerte_instance')]
#[ORM\Index(name: 'idx_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_societe', columns: ['societe_id'])]
#[ORM\Index(name: 'idx_resolved', columns: ['resolved'])]
class AlerteInstance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AlerteType::class, inversedBy: 'instances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AlerteType $alerteType = null;

    #[ORM\Column(length: 255)]
    private ?string $entityType = null;

    #[ORM\Column]
    private ?int $entityId = null;

    #[ORM\ManyToOne(targetEntity: Societe::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Societe $societe = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDetection = null;

    #[ORM\Column]
    private bool $resolved = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateResolution = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $resolvedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    public function __construct()
    {
        $this->dateDetection = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlerteType(): ?AlerteType
    {
        return $this->alerteType;
    }

    public function setAlerteType(?AlerteType $alerteType): static
    {
        $this->alerteType = $alerteType;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;
        return $this;
    }

    public function getDateDetection(): ?\DateTimeImmutable
    {
        return $this->dateDetection;
    }

    public function setDateDetection(\DateTimeImmutable $dateDetection): static
    {
        $this->dateDetection = $dateDetection;
        return $this;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setResolved(bool $resolved): static
    {
        $this->resolved = $resolved;
        if ($resolved && !$this->dateResolution) {
            $this->dateResolution = new \DateTimeImmutable();
        } elseif (!$resolved) {
            $this->dateResolution = null;
            $this->resolvedBy = null;
        }
        return $this;
    }

    public function getDateResolution(): ?\DateTimeImmutable
    {
        return $this->dateResolution;
    }

    public function setDateResolution(?\DateTimeImmutable $dateResolution): static
    {
        $this->dateResolution = $dateResolution;
        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?User $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getEntityDisplayName(): string
    {
        $parts = explode('\\', $this->entityType);
        return end($parts) . ' #' . $this->entityId;
    }

    public function resolve(User $user, string $commentaire = null): static
    {
        $this->setResolved(true);
        $this->setResolvedBy($user);
        if ($commentaire) {
            $this->setCommentaire($commentaire);
        }
        return $this;
    }
}