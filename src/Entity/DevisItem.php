<?php

namespace App\Entity;

use App\Repository\DevisItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisItemRepository::class)]
class DevisItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'devisItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    #[ORM\ManyToOne(inversedBy: 'devisItems')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Produit $produit = null;

    #[ORM\Column(length: 255)]
    private ?string $designation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantite = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixUnitaireHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $remisePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $remiseMontant = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private string $totalLigneHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $tvaPercent = '20.00';

    #[ORM\Column]
    private ?int $ordreAffichage = 1;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->totalLigneHt = '0.00';
        $this->ordreAffichage = 1;
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

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;
        
        // Si un produit est sélectionné, pré-remplir les champs
        if ($produit) {
            $this->designation = $produit->getDesignation();
            $this->description = $produit->getDescription();
            $this->prixUnitaireHt = $produit->getPrixVenteHt();
            $this->tvaPercent = $produit->getTvaPercent();
            $this->calculateTotal();
        }
        
        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;
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

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(string $quantite): static
    {
        $this->quantite = $quantite;
        $this->calculateTotal();
        return $this;
    }

    public function getPrixUnitaireHt(): ?string
    {
        return $this->prixUnitaireHt;
    }

    public function setPrixUnitaireHt(string $prixUnitaireHt): static
    {
        $this->prixUnitaireHt = $prixUnitaireHt;
        $this->calculateTotal();
        return $this;
    }

    public function getRemisePercent(): ?string
    {
        return $this->remisePercent;
    }

    public function setRemisePercent(?string $remisePercent): static
    {
        $this->remisePercent = $remisePercent;
        $this->calculateTotal();
        return $this;
    }

    public function getRemiseMontant(): ?string
    {
        return $this->remiseMontant;
    }

    public function setRemiseMontant(?string $remiseMontant): static
    {
        $this->remiseMontant = $remiseMontant;
        $this->calculateTotal();
        return $this;
    }

    public function getTotalLigneHt(): string
    {
        return $this->totalLigneHt ?? '0.00';
    }

    public function setTotalLigneHt(?string $totalLigneHt): static
    {
        $this->totalLigneHt = $totalLigneHt ?? '0.00';
        return $this;
    }

    public function recalculateTotal(): static
    {
        $this->calculateTotal();
        return $this;
    }

    public function getTvaPercent(): ?string
    {
        return $this->tvaPercent;
    }

    public function setTvaPercent(string $tvaPercent): static
    {
        $this->tvaPercent = $tvaPercent;
        return $this;
    }

    public function getOrdreAffichage(): ?int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(?int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage ?? 1;
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

    public function calculateTotal(): void
    {
        $total = floatval($this->quantite) * floatval($this->prixUnitaireHt);
        
        // Appliquer la remise en pourcentage
        if ($this->remisePercent) {
            $total = $total * (1 - floatval($this->remisePercent) / 100);
        }
        
        // Appliquer la remise en montant
        if ($this->remiseMontant) {
            $total -= floatval($this->remiseMontant);
        }
        
        $this->totalLigneHt = number_format(max(0, $total), 2, '.', '');
    }

    public function getTotalLigneTtc(): string
    {
        $ht = floatval($this->totalLigneHt);
        $tva = $ht * floatval($this->tvaPercent) / 100;
        return number_format($ht + $tva, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->designation . ' (Qté: ' . $this->quantite . ', Total: ' . $this->totalLigneHt . '€)';
    }
}