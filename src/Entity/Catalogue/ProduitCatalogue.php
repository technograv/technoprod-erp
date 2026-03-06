<?php

namespace App\Entity\Catalogue;

use App\Entity\Produit;
use App\Entity\Production\Nomenclature;
use App\Entity\Production\Gamme;
use App\Repository\Catalogue\ProduitCatalogueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Produit Catalogue (produit complexe fabriqué)
 *
 * Extension de l'entité Produit pour les produits configurables
 * nécessitant une nomenclature et une gamme de fabrication.
 *
 * Différence avec Produit simple :
 * - Produit simple : Acheté/revendu directement, prix fixe
 * - Produit catalogue : Fabriqué sur mesure, prix calculé selon configuration
 *
 * Exemples :
 * - Enseigne LED personnalisée (taille, couleur LEDs, finition)
 * - Panneau imprimé découpé forme (dimensions, matière, épaisseur)
 * - Lettre découpée lumineuse (matériau, éclairage, taille)
 */
#[ORM\Entity(repositoryClass: ProduitCatalogueRepository::class)]
#[ORM\Table(name: 'produit_catalogue')]
#[ORM\HasLifecycleCallbacks]
class ProduitCatalogue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Lien vers le produit de base
     * Le ProduitCatalogue étend les fonctionnalités d'un Produit
     */
    #[ORM\OneToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    /**
     * Nomenclature (BOM) associée
     * Définit les composants nécessaires
     */
    #[ORM\ManyToOne(targetEntity: Nomenclature::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Nomenclature $nomenclature = null;

    /**
     * Gamme de fabrication associée
     * Définit les étapes de production
     */
    #[ORM\ManyToOne(targetEntity: Gamme::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gamme $gamme = null;

    /**
     * @var Collection<int, OptionProduit>
     */
    #[ORM\OneToMany(targetEntity: OptionProduit::class, mappedBy: 'produitCatalogue', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $options;

    /**
     * @var Collection<int, RegleCompatibilite>
     */
    #[ORM\OneToMany(targetEntity: RegleCompatibilite::class, mappedBy: 'produitCatalogue', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reglesCompatibilite;

    /**
     * Paramètres par défaut (JSON)
     * Configuration par défaut proposée au client
     *
     * Exemple :
     * {
     *   "largeur": 1200,
     *   "hauteur": 600,
     *   "couleur_led": "BLANC_CHAUD",
     *   "finition": "mat"
     * }
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametresDefaut = null;

    /**
     * Variables calculées automatiquement (JSON)
     * Formules de calcul pour variables dérivées
     *
     * Permet de définir des variables calculées à partir des paramètres :
     * {
     *   "surface": "largeur * hauteur / 1000000",
     *   "perimetre": "(largeur + hauteur) * 2 / 1000",
     *   "nb_leds": "surface * 50"
     * }
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $variablesCalculees = null;

    /**
     * Permet la personnalisation complète
     * Si false, seules quelques options prédéfinies sont disponibles
     */
    #[ORM\Column]
    private bool $personnalisable = true;

    /**
     * Afficher sur devis
     * Si false, produit catalogue accessible uniquement en interne
     */
    #[ORM\Column]
    private bool $afficherSurDevis = true;

    /**
     * Marge par défaut (%)
     * Utilisée pour calculer le prix de vente si pas de prix fixe
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $margeDefaut = null;

    /**
     * Instructions spécifiques pour configuration
     * Aide affichée aux utilisateurs lors de la configuration
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructionsConfiguration = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->options = new ArrayCollection();
        $this->reglesCompatibilite = new ArrayCollection();
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
        return $this->produit ? $this->produit->getReference() . ' - ' . $this->produit->getDesignation() : '';
    }

    /**
     * Retourne une configuration par défaut complète
     * Fusionne parametresDefaut avec valeurs par défaut des options
     */
    public function getConfigurationDefaut(): array
    {
        $config = $this->parametresDefaut ?? [];

        foreach ($this->options as $option) {
            // Si l'option n'a pas de valeur dans parametresDefaut, prendre la première valeur
            if (!isset($config[$option->getCode()])) {
                $premiereValeur = $option->getValeurs()->first();
                if ($premiereValeur) {
                    $config[$option->getCode()] = $premiereValeur->getCode();
                }
            }
        }

        return $config;
    }

    /**
     * Calcule les variables dérivées à partir d'une configuration
     */
    public function calculerVariables(array $configuration): array
    {
        $variables = $configuration; // Inclut les paramètres de base

        if ($this->variablesCalculees) {
            foreach ($this->variablesCalculees as $variable => $formule) {
                // La formule sera évaluée par le service MoteurFormules
                $variables[$variable] = $formule;
            }
        }

        return $variables;
    }

    /**
     * Vérifie si le produit a des options configurables
     */
    public function hasOptions(): bool
    {
        return !$this->options->isEmpty();
    }

    /**
     * Vérifie si le produit a des règles de compatibilité
     */
    public function hasRegles(): bool
    {
        return !$this->reglesCompatibilite->isEmpty();
    }

    /**
     * Compte le nombre d'options obligatoires
     */
    public function compterOptionsObligatoires(): int
    {
        $count = 0;
        foreach ($this->options as $option) {
            if ($option->isObligatoire()) {
                $count++;
            }
        }
        return $count;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(Produit $produit): static
    {
        $this->produit = $produit;
        return $this;
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

    public function getGamme(): ?Gamme
    {
        return $this->gamme;
    }

    public function setGamme(?Gamme $gamme): static
    {
        $this->gamme = $gamme;
        return $this;
    }

    /**
     * @return Collection<int, OptionProduit>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(OptionProduit $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setProduitCatalogue($this);
        }

        return $this;
    }

    public function removeOption(OptionProduit $option): static
    {
        if ($this->options->removeElement($option)) {
            if ($option->getProduitCatalogue() === $this) {
                $option->setProduitCatalogue(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RegleCompatibilite>
     */
    public function getReglesCompatibilite(): Collection
    {
        return $this->reglesCompatibilite;
    }

    public function addRegleCompatibilite(RegleCompatibilite $regle): static
    {
        if (!$this->reglesCompatibilite->contains($regle)) {
            $this->reglesCompatibilite->add($regle);
            $regle->setProduitCatalogue($this);
        }

        return $this;
    }

    public function removeRegleCompatibilite(RegleCompatibilite $regle): static
    {
        if ($this->reglesCompatibilite->removeElement($regle)) {
            if ($regle->getProduitCatalogue() === $this) {
                $regle->setProduitCatalogue(null);
            }
        }

        return $this;
    }

    public function getParametresDefaut(): ?array
    {
        return $this->parametresDefaut;
    }

    public function setParametresDefaut(?array $parametresDefaut): static
    {
        $this->parametresDefaut = $parametresDefaut;
        return $this;
    }

    public function getVariablesCalculees(): ?array
    {
        return $this->variablesCalculees;
    }

    public function setVariablesCalculees(?array $variablesCalculees): static
    {
        $this->variablesCalculees = $variablesCalculees;
        return $this;
    }

    public function isPersonnalisable(): ?bool
    {
        return $this->personnalisable;
    }

    public function setPersonnalisable(bool $personnalisable): static
    {
        $this->personnalisable = $personnalisable;
        return $this;
    }

    public function isAfficherSurDevis(): ?bool
    {
        return $this->afficherSurDevis;
    }

    public function setAfficherSurDevis(bool $afficherSurDevis): static
    {
        $this->afficherSurDevis = $afficherSurDevis;
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

    public function getInstructionsConfiguration(): ?string
    {
        return $this->instructionsConfiguration;
    }

    public function setInstructionsConfiguration(?string $instructionsConfiguration): static
    {
        $this->instructionsConfiguration = $instructionsConfiguration;
        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
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
