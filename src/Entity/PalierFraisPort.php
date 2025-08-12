<?php

namespace App\Entity;

use App\Repository\PalierFraisPortRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PalierFraisPortRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PalierFraisPort
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FraisPort::class, inversedBy: 'paliers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FraisPort $fraisPort = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    private ?string $limiteJusqua = null; // Limite "jusqu'à" (montant, quantité, poids, etc.)

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $valeur = null; // Valeur des frais pour ce palier

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null; // Description du palier

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getFraisPort(): ?FraisPort
    {
        return $this->fraisPort;
    }

    public function setFraisPort(?FraisPort $fraisPort): static
    {
        $this->fraisPort = $fraisPort;
        return $this;
    }

    public function getLimiteJusqua(): ?string
    {
        return $this->limiteJusqua;
    }

    public function setLimiteJusqua(string $limiteJusqua): static
    {
        $this->limiteJusqua = $limiteJusqua;
        return $this;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function __toString(): string
    {
        return sprintf(
            'Jusqu\'à %s → %s€',
            $this->limiteJusqua,
            $this->valeur
        );
    }

    /**
     * Retourne l'unité de mesure selon le mode de calcul du frais de port
     */
    public function getUniteAffichage(): string
    {
        if (!$this->fraisPort) {
            return '';
        }

        return match($this->fraisPort->getModeCalcul()) {
            FraisPort::MODE_PALIER_MONTANT_HT => '€',
            FraisPort::MODE_PALIER_QUANTITE => 'unités',
            FraisPort::MODE_PALIER_POIDS => 'kg',
            FraisPort::MODE_PALIER_COLIS => 'colis',
            FraisPort::MODE_PALIER_VOLUME => 'm³',
            default => ''
        };
    }

    /**
     * Retourne l'affichage formaté du palier
     */
    public function getAffichageComplet(): string
    {
        $unite = $this->getUniteAffichage();
        return sprintf(
            'Jusqu\'à %s %s → %s€',
            $this->limiteJusqua,
            $unite,
            $this->valeur
        );
    }
}
