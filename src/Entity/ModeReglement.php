<?php

namespace App\Entity;

use App\Repository\ModeReglementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeReglementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ModeReglement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 6)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombreJours = null;

    #[ORM\Column(length: 50)]
    private ?string $typeReglement = null; // 'comptant', 'fin_de_mois', 'fin_de_mois_plus_jours', 'fin_de_mois_le_jour'

    #[ORM\Column(nullable: true)]
    private ?int $jourReglement = null; // de 1 à 31

    #[ORM\ManyToOne(targetEntity: ModePaiement::class, inversedBy: 'modesReglement')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ModePaiement $modePaiement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private bool $modeParDefaut = false;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getNombreJours(): ?int
    {
        return $this->nombreJours;
    }

    public function setNombreJours(?int $nombreJours): static
    {
        $this->nombreJours = $nombreJours;
        return $this;
    }

    public function getTypeReglement(): ?string
    {
        return $this->typeReglement;
    }

    public function setTypeReglement(string $typeReglement): static
    {
        $this->typeReglement = $typeReglement;
        return $this;
    }

    public function getJourReglement(): ?int
    {
        return $this->jourReglement;
    }

    public function setJourReglement(?int $jourReglement): static
    {
        $this->jourReglement = $jourReglement;
        return $this;
    }

    public function getModePaiement(): ?ModePaiement
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?ModePaiement $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function isModeParDefaut(): bool
    {
        return $this->modeParDefaut;
    }

    public function setModeParDefaut(bool $modeParDefaut): static
    {
        $this->modeParDefaut = $modeParDefaut;
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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

    /**
     * Retourne une description textuelle du type de règlement
     */
    public function getTypeReglementLibelle(): string
    {
        return match($this->typeReglement) {
            'comptant' => 'Comptant',
            'fin_de_mois' => 'Fin de mois',
            'fin_de_mois_plus_jours' => 'Fin de mois + ' . $this->nombreJours . ' jours',
            'fin_de_mois_le_jour' => 'Fin de mois le ' . $this->jourReglement,
            default => $this->typeReglement ?? ''
        };
    }

    /**
     * Retourne une description complète du mode de règlement
     */
    public function getDescriptionComplete(): string
    {
        $description = $this->nom;
        
        if ($this->typeReglement && $this->typeReglement !== 'comptant') {
            $description .= ' - ' . $this->getTypeReglementLibelle();
        }
        
        if ($this->modePaiement) {
            $description .= ' (' . $this->modePaiement->getNom() . ')';
        }
        
        return $description;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}