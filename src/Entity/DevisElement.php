<?php

namespace App\Entity;

use App\Repository\DevisElementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisElementRepository::class)]
#[ORM\Table(name: 'devis_element')]
class DevisElement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'elements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $position = null;

    // Champs pour les produits (ex-DevisItem)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Produit $produit = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $designation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $quantite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $prixUnitaireHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $remisePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $remiseMontant = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $tvaPercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalLigneHt = null;

    // Champs pour la gestion des images produits
    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $imageVisible = false;

    #[ORM\ManyToOne(targetEntity: ProductImage::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductImage $productImage = null;

    // Champs pour les éléments de mise en page (ex-LayoutElement)
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenu = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametres = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->parametres = [];
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // Getters/Setters pour les champs produit
    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(?string $designation): static
    {
        $this->designation = $designation;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(?string $quantite): static
    {
        $this->quantite = $quantite;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPrixUnitaireHt(): ?string
    {
        return $this->prixUnitaireHt;
    }

    public function setPrixUnitaireHt(?string $prixUnitaireHt): static
    {
        $this->prixUnitaireHt = $prixUnitaireHt;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getRemisePercent(): ?string
    {
        return $this->remisePercent;
    }

    public function setRemisePercent(?string $remisePercent): static
    {
        $this->remisePercent = $remisePercent;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getRemiseMontant(): ?string
    {
        return $this->remiseMontant;
    }

    public function setRemiseMontant(?string $remiseMontant): static
    {
        $this->remiseMontant = $remiseMontant;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTvaPercent(): ?string
    {
        return $this->tvaPercent;
    }

    public function setTvaPercent(?string $tvaPercent): static
    {
        $this->tvaPercent = $tvaPercent;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTotalLigneHt(): ?string
    {
        return $this->totalLigneHt;
    }

    public function setTotalLigneHt(?string $totalLigneHt): static
    {
        $this->totalLigneHt = $totalLigneHt;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // Getters/Setters pour les champs de mise en page
    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getParametres(): ?array
    {
        return $this->parametres;
    }

    public function setParametres(?array $parametres): static
    {
        $this->parametres = $parametres ?? [];
        $this->updatedAt = new \DateTimeImmutable();
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

    // Méthodes utilitaires
    public function isProductElement(): bool
    {
        return $this->type === 'product';
    }

    public function isLayoutElement(): bool
    {
        return in_array($this->type, ['line_break', 'page_break', 'subtotal', 'section_title', 'separator']);
    }

    public function calculateTotal(): void
    {
        if (!$this->isProductElement()) {
            return;
        }

        $quantite = (float) ($this->quantite ?? 0);
        $prixUnitaire = (float) ($this->prixUnitaireHt ?? 0);
        $remisePercent = (float) ($this->remisePercent ?? 0);

        $sousTotal = $quantite * $prixUnitaire;
        $remiseMontant = $sousTotal * ($remisePercent / 100);
        $totalHt = $sousTotal - $remiseMontant;

        $this->remiseMontant = number_format($remiseMontant, 2, '.', '');
        $this->totalLigneHt = number_format($totalHt, 2, '.', '');
    }

    public function getDisplayLabel(): string
    {
        return match($this->type) {
            'product' => $this->designation ?? 'Produit',
            'line_break' => 'Saut de ligne',
            'page_break' => 'Saut de page', 
            'subtotal' => 'Sous-total',
            'section_title' => $this->titre ?? 'Titre de section',
            'separator' => 'Séparateur',
            default => 'Élément inconnu'
        };
    }

    public function getIcon(): string
    {
        return match($this->type) {
            'product' => 'fas fa-cube',
            'line_break' => 'fas fa-minus',
            'page_break' => 'fas fa-file-alt',
            'subtotal' => 'fas fa-calculator',
            'section_title' => 'fas fa-heading',
            'separator' => 'fas fa-ellipsis-h',
            default => 'fas fa-question'
        };
    }

    public function getCssClass(): string
    {
        $baseClass = $this->isProductElement() ? 'devis-item' : 'layout-element';
        $typeClass = 'element-' . str_replace('_', '-', $this->type);
        
        return trim($baseClass . ' ' . $typeClass);
    }

    public function getImageVisible(): ?bool
    {
        return $this->imageVisible;
    }

    public function setImageVisible(bool $imageVisible): static
    {
        $this->imageVisible = $imageVisible;
        return $this;
    }

    public function getProductImage(): ?ProductImage
    {
        return $this->productImage;
    }

    public function setProductImage(?ProductImage $productImage): static
    {
        $this->productImage = $productImage;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'position' => $this->position,
            'designation' => $this->designation,
            'description' => $this->description,
            'quantite' => $this->quantite,
            'prix_unitaire_ht' => $this->prixUnitaireHt,
            'remise_percent' => $this->remisePercent,
            'remise_montant' => $this->remiseMontant,
            'tva_percent' => $this->tvaPercent,
            'total_ligne_ht' => $this->totalLigneHt,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'parametres' => $this->parametres,
            'display_label' => $this->getDisplayLabel(),
            'icon' => $this->getIcon(),
            'css_class' => $this->getCssClass(),
            'is_product' => $this->isProductElement(),
            'is_layout' => $this->isLayoutElement(),
            'produit_id' => $this->produit?->getId(),
            'produit_reference' => $this->produit?->getReference(),
            'image_visible' => $this->imageVisible,
            'product_image_id' => $this->productImage?->getId(),
            'product_image_path' => $this->productImage?->getImagePath()
        ];
    }
}