<?php

namespace App\Entity\Catalogue;

use App\Repository\Catalogue\OptionProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Option de configuration d'un produit catalogue
 *
 * Représente une option configurable par le client :
 * - Dimensions (largeur, hauteur)
 * - Choix matériau (PVC, alu, inox)
 * - Choix couleur (RAL, LED)
 * - Choix finition (mat, brillant, satiné)
 * - Choix système de fixation
 * - etc.
 *
 * Types de champs :
 * - DIMENSIONS : Saisie largeur × hauteur (2 champs numériques)
 * - SELECT : Liste déroulante (1 choix parmi plusieurs)
 * - MULTISELECT : Liste à choix multiples
 * - NUMERIC : Champ numérique simple (quantité, épaisseur)
 * - TEXT : Champ texte libre (gravure personnalisée)
 * - BOOLEAN : Case à cocher oui/non
 */
#[ORM\Entity(repositoryClass: OptionProduitRepository::class)]
#[ORM\Table(name: 'option_produit')]
#[ORM\HasLifecycleCallbacks]
class OptionProduit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProduitCatalogue::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProduitCatalogue $produitCatalogue = null;

    /**
     * Code de l'option (identifiant interne)
     * Utilisé dans les formules et conditions
     * Exemples : "TAILLE", "COULEUR_LED", "FINITION", "MATERIAU"
     */
    #[ORM\Column(length: 50)]
    private string $code;

    /**
     * Libellé affiché à l'utilisateur
     * Exemples : "Dimensions", "Couleur d'éclairage", "Finition de surface"
     */
    #[ORM\Column(length: 255)]
    private string $libelle;

    /**
     * Description / aide pour l'utilisateur
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Type de champ de saisie
     */
    #[ORM\Column(length: 30)]
    private string $typeChamp = self::TYPE_SELECT;

    /**
     * Constantes pour les types de champs
     */
    public const TYPE_DIMENSIONS = 'DIMENSIONS';     // Largeur × Hauteur
    public const TYPE_SELECT = 'SELECT';             // Liste déroulante
    public const TYPE_MULTISELECT = 'MULTISELECT';   // Choix multiples
    public const TYPE_NUMERIC = 'NUMERIC';           // Nombre
    public const TYPE_TEXT = 'TEXT';                 // Texte libre
    public const TYPE_BOOLEAN = 'BOOLEAN';           // Oui/Non

    /**
     * Option obligatoire
     * Si true, le client doit obligatoirement choisir une valeur
     */
    #[ORM\Column]
    private bool $obligatoire = true;

    /**
     * Ordre d'affichage
     */
    #[ORM\Column]
    private int $ordre = 0;

    /**
     * @var Collection<int, ValeurOption>
     */
    #[ORM\OneToMany(targetEntity: ValeurOption::class, mappedBy: 'option', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $valeurs;

    /**
     * Paramètres supplémentaires (JSON)
     * Selon le type de champ, permet de définir :
     * - min/max pour NUMERIC
     * - pattern pour TEXT
     * - unité pour DIMENSIONS
     *
     * Exemples :
     * {"min": 100, "max": 5000, "step": 10, "unite": "mm"}
     * {"pattern": "^[A-Z0-9-]+$", "placeholder": "CODE-XXX"}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametres = null;

    /**
     * Condition d'affichage de l'option
     * Permet de n'afficher l'option que si certaines conditions sont remplies
     *
     * Exemples :
     * - "option_lumineux == true" → affiché seulement si lumineux
     * - "materiau == 'PVC'" → affiché seulement si PVC choisi
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionAffichage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->valeurs = new ArrayCollection();
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
        return $this->libelle;
    }

    /**
     * Retourne le libellé du type de champ
     */
    public function getTypeChampLibelle(): string
    {
        return match($this->typeChamp) {
            self::TYPE_DIMENSIONS => 'Dimensions (L×H)',
            self::TYPE_SELECT => 'Liste déroulante',
            self::TYPE_MULTISELECT => 'Choix multiples',
            self::TYPE_NUMERIC => 'Nombre',
            self::TYPE_TEXT => 'Texte libre',
            self::TYPE_BOOLEAN => 'Oui/Non',
            default => 'Inconnu'
        };
    }

    /**
     * Vérifie si l'option a des valeurs prédéfinies
     */
    public function hasValeurs(): bool
    {
        return !$this->valeurs->isEmpty();
    }

    /**
     * Vérifie si l'option a une condition d'affichage
     */
    public function hasCondition(): bool
    {
        return !empty($this->conditionAffichage);
    }

    /**
     * Récupère un paramètre
     */
    public function getParametre(string $key): mixed
    {
        return $this->parametres[$key] ?? null;
    }

    /**
     * Définit un paramètre
     */
    public function setParametre(string $key, mixed $value): static
    {
        $params = $this->parametres ?? [];
        $params[$key] = $value;
        $this->parametres = $params;
        return $this;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduitCatalogue(): ?ProduitCatalogue
    {
        return $this->produitCatalogue;
    }

    public function setProduitCatalogue(?ProduitCatalogue $produitCatalogue): static
    {
        $this->produitCatalogue = $produitCatalogue;
        return $this;
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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
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

    public function getTypeChamp(): ?string
    {
        return $this->typeChamp;
    }

    public function setTypeChamp(string $typeChamp): static
    {
        $this->typeChamp = $typeChamp;
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    /**
     * @return Collection<int, ValeurOption>
     */
    public function getValeurs(): Collection
    {
        return $this->valeurs;
    }

    public function addValeur(ValeurOption $valeur): static
    {
        if (!$this->valeurs->contains($valeur)) {
            $this->valeurs->add($valeur);
            $valeur->setOption($this);
        }

        return $this;
    }

    public function removeValeur(ValeurOption $valeur): static
    {
        if ($this->valeurs->removeElement($valeur)) {
            if ($valeur->getOption() === $this) {
                $valeur->setOption(null);
            }
        }

        return $this;
    }

    public function getParametres(): ?array
    {
        return $this->parametres;
    }

    public function setParametres(?array $parametres): static
    {
        $this->parametres = $parametres;
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
