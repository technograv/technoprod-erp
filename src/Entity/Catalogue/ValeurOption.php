<?php

namespace App\Entity\Catalogue;

use App\Repository\Catalogue\ValeurOptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Valeur d'option de configuration
 *
 * Représente une valeur possible pour une option de produit catalogue.
 *
 * Exemples :
 * - Option "Couleur LED" → Valeurs : "Blanc chaud 3000K", "Blanc froid 6000K", "RGB"
 * - Option "Matériau" → Valeurs : "PVC 3mm", "PVC 5mm", "Aluminium", "Inox"
 * - Option "Finition" → Valeurs : "Mat", "Brillant", "Satiné"
 */
#[ORM\Entity(repositoryClass: ValeurOptionRepository::class)]
#[ORM\Table(name: 'valeur_option')]
#[ORM\HasLifecycleCallbacks]
class ValeurOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OptionProduit::class, inversedBy: 'valeurs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?OptionProduit $option = null;

    /**
     * Code de la valeur (identifiant interne)
     * Utilisé dans les formules et règles
     * Exemples : "BLANC_CHAUD", "PVC_3MM", "MAT"
     */
    #[ORM\Column(length: 50)]
    private string $code;

    /**
     * Libellé affiché à l'utilisateur
     * Exemples : "Blanc chaud 3000K", "PVC 3mm", "Finition mate"
     */
    #[ORM\Column(length: 255)]
    private string $libelle;

    /**
     * Description détaillée
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Supplément de prix (€)
     * Montant ajouté au prix de base si cette valeur est choisie
     * Peut être négatif (réduction)
     *
     * Exemples :
     * - LED RGB : +150.00 € (plus cher que blanc)
     * - PVC 3mm : 0.00 € (référence)
     * - PVC 10mm : +45.00 €
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $supplementPrix = '0.00';

    /**
     * Impact sur le coût (€)
     * Coût réel de l'option (peut différer du supplement prix)
     * Utilisé pour calculer la marge réelle
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $impactCout = null;

    /**
     * Image / Icône illustrant la valeur
     * Path relatif depuis /public/uploads/options/
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * Couleur hexa (pour affichage visuel)
     * Utilisé pour les options couleur (RAL, etc.)
     * Exemple : "#FF5733"
     */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $couleurHexa = null;

    /**
     * Ordre d'affichage dans la liste
     */
    #[ORM\Column]
    private int $ordre = 0;

    /**
     * Valeur par défaut
     * Si true, cette valeur est présélectionnée
     */
    #[ORM\Column]
    private bool $parDefaut = false;

    /**
     * Disponible
     * Permet de désactiver temporairement une valeur sans la supprimer
     */
    #[ORM\Column]
    private bool $disponible = true;

    /**
     * Stock disponible (optionnel)
     * Pour les options liées à un stock physique
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $stock = null;

    /**
     * Données supplémentaires (JSON)
     * Permet de stocker des informations spécifiques
     *
     * Exemples :
     * - {"ral": "9010", "brillance": 85}
     * - {"reference_fournisseur": "LED-RGB-12V-5M"}
     * - {"delai_livraison": 7}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $donnees = null;

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
        return $this->libelle;
    }

    /**
     * Retourne le libellé avec supplément prix si non nul
     */
    public function getLibelleAvecPrix(): string
    {
        $prix = (float)$this->supplementPrix;
        if ($prix == 0) {
            return $this->libelle;
        }

        $signe = $prix > 0 ? '+' : '';
        return sprintf('%s (%s%.2f €)', $this->libelle, $signe, $prix);
    }

    /**
     * Vérifie si en stock (si gestion stock activée)
     */
    public function isEnStock(): bool
    {
        if ($this->stock === null) {
            return true; // Pas de gestion stock
        }

        return $this->stock > 0;
    }

    /**
     * Récupère une donnée supplémentaire
     */
    public function getDonnee(string $key): mixed
    {
        return $this->donnees[$key] ?? null;
    }

    /**
     * Définit une donnée supplémentaire
     */
    public function setDonnee(string $key, mixed $value): static
    {
        $data = $this->donnees ?? [];
        $data[$key] = $value;
        $this->donnees = $data;
        return $this;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOption(): ?OptionProduit
    {
        return $this->option;
    }

    public function setOption(?OptionProduit $option): static
    {
        $this->option = $option;
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

    public function getSupplementPrix(): ?string
    {
        return $this->supplementPrix;
    }

    public function setSupplementPrix(string $supplementPrix): static
    {
        $this->supplementPrix = $supplementPrix;
        return $this;
    }

    public function getImpactCout(): ?string
    {
        return $this->impactCout;
    }

    public function setImpactCout(?string $impactCout): static
    {
        $this->impactCout = $impactCout;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getCouleurHexa(): ?string
    {
        return $this->couleurHexa;
    }

    public function setCouleurHexa(?string $couleurHexa): static
    {
        $this->couleurHexa = $couleurHexa;
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

    public function isParDefaut(): ?bool
    {
        return $this->parDefaut;
    }

    public function setParDefaut(bool $parDefaut): static
    {
        $this->parDefaut = $parDefaut;
        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;
        return $this;
    }

    public function getDonnees(): ?array
    {
        return $this->donnees;
    }

    public function setDonnees(?array $donnees): static
    {
        $this->donnees = $donnees;
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
