<?php

namespace App\Entity;

use App\Repository\ConsentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Consent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $purpose = null;

    #[ORM\Column]
    private ?bool $granted = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $grantedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $withdrawnAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $legalBasis = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->grantedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): static
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function isGranted(): ?bool
    {
        return $this->granted;
    }

    public function setGranted(bool $granted): static
    {
        $this->granted = $granted;
        if (!$granted && $this->withdrawnAt === null) {
            $this->withdrawnAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getGrantedAt(): ?\DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function setGrantedAt(\DateTimeImmutable $grantedAt): static
    {
        $this->grantedAt = $grantedAt;
        return $this;
    }

    public function getWithdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function setWithdrawnAt(?\DateTimeImmutable $withdrawnAt): static
    {
        $this->withdrawnAt = $withdrawnAt;
        return $this;
    }

    public function getLegalBasis(): ?string
    {
        return $this->legalBasis;
    }

    public function setLegalBasis(?string $legalBasis): static
    {
        $this->legalBasis = $legalBasis;
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

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->granted && $this->withdrawnAt === null;
    }

    public function __toString(): string
    {
        return sprintf('%s: %s (%s)', 
            $this->user?->getFullName() ?? 'Unknown',
            $this->purpose,
            $this->isActive() ? 'Accordé' : 'Retiré'
        );
    }
}