<?php

namespace App\Entity;

use App\Repository\DevisVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisVersionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DevisVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    #[ORM\Column]
    private ?int $versionNumber = null;

    #[ORM\Column(type: Types::JSON)]
    private array $snapshotData = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $modificationReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $modifiedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $versionLabel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTtcAtTime = '0.00';

    #[ORM\Column(length: 20)]
    private ?string $statutAtTime = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
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

    public function getVersionNumber(): ?int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): static
    {
        $this->versionNumber = $versionNumber;
        return $this;
    }

    public function getSnapshotData(): array
    {
        return $this->snapshotData;
    }

    public function setSnapshotData(array $snapshotData): static
    {
        $this->snapshotData = $snapshotData;
        return $this;
    }

    public function getModificationReason(): ?string
    {
        return $this->modificationReason;
    }

    public function setModificationReason(?string $modificationReason): static
    {
        $this->modificationReason = $modificationReason;
        return $this;
    }

    public function getModifiedBy(): ?User
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?User $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;
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

    public function getVersionLabel(): ?string
    {
        return $this->versionLabel;
    }

    public function setVersionLabel(?string $versionLabel): static
    {
        $this->versionLabel = $versionLabel;
        return $this;
    }

    public function getTotalTtcAtTime(): ?string
    {
        return $this->totalTtcAtTime;
    }

    public function setTotalTtcAtTime(string $totalTtcAtTime): static
    {
        $this->totalTtcAtTime = $totalTtcAtTime;
        return $this;
    }

    public function getStatutAtTime(): ?string
    {
        return $this->statutAtTime;
    }

    public function setStatutAtTime(string $statutAtTime): static
    {
        $this->statutAtTime = $statutAtTime;
        return $this;
    }

    /**
     * Récupère les données du devis à partir du snapshot
     */
    public function getDevisData(): array
    {
        return $this->snapshotData['devis'] ?? [];
    }

    /**
     * Récupère les items du devis à partir du snapshot  
     */
    public function getDevisItems(): array
    {
        return $this->snapshotData['items'] ?? [];
    }

    /**
     * Génère un label automatique pour la version
     */
    public function generateAutoLabel(): string
    {
        if ($this->versionLabel) {
            return $this->versionLabel;
        }

        return "Version {$this->versionNumber}";
    }

    /**
     * Vérifie si c'est la version initiale (lors de l'envoi)
     */
    public function isInitialVersion(): bool
    {
        return $this->versionNumber === 1 || $this->versionLabel === 'Version initiale';
    }
}