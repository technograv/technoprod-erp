<?php

namespace App\Entity\Production;

use App\Entity\Produit;
use App\Entity\Unite;
use App\Repository\Production\NomenclatureLigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne de nomenclature (composant)
 *
 * Représente un composant ou sous-ensemble utilisé dans une nomenclature.
 * Peut être :
 * - Une matière première (lien vers Produit simple)
 * - Un sous-ensemble (lien vers Nomenclature enfant)
 * - Une fourniture (visserie, consommables, etc.)
 *
 * La quantité peut être :
 * - Fixe : quantiteBase
 * - Dynamique : calculée via formuleQuantite avec variables du configurateur
 *
 * Exemples formules :
 * - "largeur * hauteur / 10000" → surface en m²
 * - "perimetre * 1.1" → bordure avec 10% de chute
 * - "nb_lettres * 2" → 2 entretoises par lettre
 */
#[ORM\Entity(repositoryClass: NomenclatureLigneRepository::class)]
#[ORM\Table(name: 'nomenclature_ligne')]
#[ORM\HasLifecycleCallbacks]
class NomenclatureLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Nomenclature::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Nomenclature $nomenclature = null;

    #[ORM\Column]
    private int $ordre = 0;

    /**
     * Type de ligne
     */
    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_MATIERE_PREMIERE;

    /**
     * Constantes pour les types
     */
    public const TYPE_MATIERE_PREMIERE = 'MATIERE_PREMIERE'; // Produit acheté
    public const TYPE_SOUS_ENSEMBLE = 'SOUS_ENSEMBLE';        // Nomenclature enfant
    public const TYPE_FOURNITURE = 'FOURNITURE';              // Consommable (vis, fil, etc.)
    public const TYPE_MAIN_OEUVRE = 'MAIN_OEUVRE';            // Temps MO spécifique

    /**
     * Produit simple lié (si MATIERE_PREMIERE ou FOURNITURE)
     */
    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Produit $produitSimple = null;

    /**
     * Nomenclature enfant (si SOUS_ENSEMBLE)
     */
    #[ORM\ManyToOne(targetEntity: Nomenclature::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Nomenclature $nomenclatureEnfant = null;

    /**
     * Désignation libre (si pas de produit ou nomenclature lié)
     */
    #[ORM\Column(length: 255)]
    private string $designation;

    /**
     * Quantité de base (fixe)
     * Utilisée si pas de formule, ou comme multiplicateur de la formule
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $quantiteBase = '1.0000';

    /**
     * Unité de quantité
     */
    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Unite $uniteQuantite = null;

    /**
     * Formule de calcul dynamique de la quantité
     *
     * Syntaxe simple type Excel avec variables du configurateur :
     * - Opérateurs : +, -, *, /, (, )
     * - Variables : largeur, hauteur, surface, perimetre, nb_lettres, etc.
     *
     * Exemples :
     * - "largeur * hauteur / 10000" → surface en m² (si largeur/hauteur en mm)
     * - "(largeur + hauteur) * 2 / 1000" → périmètre en m
     * - "nb_lettres * 2.5" → 2.5 entretoises par lettre
     * - "surface * 1.15" → +15% de chute
     *
     * Si formule vide → utilise quantiteBase
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $formuleQuantite = null;

    /**
     * Taux de chute/perte en pourcentage
     * S'ajoute à la quantité calculée
     * Exemple : 10.0 = +10% de chute
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $tauxChute = '0.00';

    /**
     * Marge par défaut sur ce composant (%)
     * Permet de définir une marge spécifique pour chaque composant
     * Exemple : 35.00 = +35% de marge sur le prix d'achat
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $margeDefaut = null;

    /**
     * Composant obligatoire (toujours inclus)
     * Si false, peut être optionnel selon configuration
     */
    #[ORM\Column]
    private bool $obligatoire = true;

    /**
     * Condition d'affichage (expression)
     *
     * Permet de conditionner l'utilisation du composant selon options choisies.
     *
     * Syntaxe simple :
     * - "option_eclairage == 'LED'" → affiché si éclairage LED choisi
     * - "option_lumineux == true" → affiché si option lumineuse cochée
     * - "taille == 'XL'" → affiché si taille XL
     *
     * Si vide → toujours affiché
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionAffichage = null;

    /**
     * Notes techniques sur le composant
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Valoriser les chutes
     * Si true, les chutes sont considérées comme réutilisables (stock)
     */
    #[ORM\Column]
    private bool $valoriserChutes = false;

    /**
     * Référence fournisseur spécifique pour ce composant
     * Utile si produit peut venir de plusieurs fournisseurs
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenceFournisseur = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->designation;
    }

    /**
     * Retourne la désignation complète avec quantité
     */
    public function getDesignationComplete(): string
    {
        $qty = $this->quantiteBase;
        $unit = $this->uniteQuantite ? $this->uniteQuantite->getSymbole() : '';
        return "{$this->designation} ({$qty} {$unit})";
    }

    /**
     * Vérifie si la ligne a une formule de calcul
     */
    public function hasFormule(): bool
    {
        return !empty($this->formuleQuantite);
    }

    /**
     * Vérifie si la ligne a une condition d'affichage
     */
    public function hasCondition(): bool
    {
        return !empty($this->conditionAffichage);
    }

    /**
     * Retourne le libellé du type
     */
    public function getTypeLibelle(): string
    {
        return match($this->type) {
            self::TYPE_MATIERE_PREMIERE => 'Matière première',
            self::TYPE_SOUS_ENSEMBLE => 'Sous-ensemble',
            self::TYPE_FOURNITURE => 'Fourniture',
            self::TYPE_MAIN_OEUVRE => 'Main d\'œuvre',
            default => 'Inconnu'
        };
    }

    /**
     * Vérifie si le composant est un produit simple
     */
    public function isProduitSimple(): bool
    {
        return $this->produitSimple !== null;
    }

    /**
     * Vérifie si le composant est un sous-ensemble
     */
    public function isSousEnsemble(): bool
    {
        return $this->nomenclatureEnfant !== null;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomenclature(): ?Nomenclature
    {
        return $this->nomenclature;
    }

    public function setNomenclature(?Nomenclature $nomenclature): static
    {
        $this->nomenclature = $nomenclature;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getProduitSimple(): ?Produit
    {
        return $this->produitSimple;
    }

    public function setProduitSimple(?Produit $produitSimple): static
    {
        $this->produitSimple = $produitSimple;
        return $this;
    }

    public function getNomenclatureEnfant(): ?Nomenclature
    {
        return $this->nomenclatureEnfant;
    }

    public function setNomenclatureEnfant(?Nomenclature $nomenclatureEnfant): static
    {
        $this->nomenclatureEnfant = $nomenclatureEnfant;
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

    public function getQuantiteBase(): ?string
    {
        return $this->quantiteBase;
    }

    public function setQuantiteBase(string $quantiteBase): static
    {
        $this->quantiteBase = $quantiteBase;
        return $this;
    }

    public function getUniteQuantite(): ?Unite
    {
        return $this->uniteQuantite;
    }

    public function setUniteQuantite(?Unite $uniteQuantite): static
    {
        $this->uniteQuantite = $uniteQuantite;
        return $this;
    }

    public function getFormuleQuantite(): ?string
    {
        return $this->formuleQuantite;
    }

    public function setFormuleQuantite(?string $formuleQuantite): static
    {
        $this->formuleQuantite = $formuleQuantite;
        return $this;
    }

    public function getTauxChute(): ?string
    {
        return $this->tauxChute;
    }

    public function setTauxChute(string $tauxChute): static
    {
        $this->tauxChute = $tauxChute;
        return $this;
    }

    public function getMargeDefaut(): ?string
    {
        return $this->margeDefaut;
    }

    public function setMargeDefaut(?string $margeDefaut): static
    {
        $this->margeDefaut = $margeDefaut;
        return $this;
    }

    public function isObligatoire(): ?bool
    {
        return $this->obligatoire;
    }

    public function setObligatoire(bool $obligatoire): static
    {
        $this->obligatoire = $obligatoire;
        return $this;
    }

    public function getConditionAffichage(): ?string
    {
        return $this->conditionAffichage;
    }

    public function setConditionAffichage(?string $conditionAffichage): static
    {
        $this->conditionAffichage = $conditionAffichage;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function isValoriserChutes(): ?bool
    {
        return $this->valoriserChutes;
    }

    public function setValoriserChutes(bool $valoriserChutes): static
    {
        $this->valoriserChutes = $valoriserChutes;
        return $this;
    }

    public function getReferenceFournisseur(): ?string
    {
        return $this->referenceFournisseur;
    }

    public function setReferenceFournisseur(?string $referenceFournisseur): static
    {
        $this->referenceFournisseur = $referenceFournisseur;
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
}
