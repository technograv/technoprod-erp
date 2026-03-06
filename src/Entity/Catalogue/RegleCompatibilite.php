<?php

namespace App\Entity\Catalogue;

use App\Repository\Catalogue\RegleCompatibiliteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Règle de compatibilité entre options
 *
 * Définit les contraintes et dépendances entre les options d'un produit catalogue.
 * Permet de gérer la logique métier complexe de configuration.
 *
 * Types de règles :
 * - REQUIRE : "Si A alors B obligatoire"
 * - EXCLUDE : "A et B incompatibles"
 * - IF_THEN : "Si condition alors action"
 * - FORMULA : "Contrainte par formule"
 *
 * Exemples :
 * - "IF option_led == 'RGB' THEN require option_controleur == 'RGB'"
 * - "EXCLUDE (taille == 'XL' AND fixation == 'murale')"
 * - "IF materiau == 'inox' THEN finition != 'peinture'"
 */
#[ORM\Entity(repositoryClass: RegleCompatibiliteRepository::class)]
#[ORM\Table(name: 'regle_compatibilite')]
#[ORM\HasLifecycleCallbacks]
class RegleCompatibilite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProduitCatalogue::class, inversedBy: 'reglesCompatibilite')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProduitCatalogue $produitCatalogue = null;

    /**
     * Code de la règle (identifiant interne)
     * Exemple : "REGLE_LED_RGB_CONTROLEUR"
     */
    #[ORM\Column(length: 100)]
    private string $code;

    /**
     * Nom de la règle
     * Exemple : "LED RGB nécessite contrôleur RGB"
     */
    #[ORM\Column(length: 255)]
    private string $nom;

    /**
     * Type de règle
     */
    #[ORM\Column(length: 20)]
    private string $typeRegle = self::TYPE_IF_THEN;

    /**
     * Constantes pour les types de règles
     */
    public const TYPE_REQUIRE = 'REQUIRE';   // A nécessite B
    public const TYPE_EXCLUDE = 'EXCLUDE';   // A exclut B
    public const TYPE_IF_THEN = 'IF_THEN';   // Si condition alors action
    public const TYPE_FORMULA = 'FORMULA';   // Contrainte par formule

    /**
     * Expression de la règle
     *
     * Syntaxe selon le type :
     *
     * TYPE_REQUIRE :
     * - "IF option_led == 'RGB' THEN require option_controleur == 'RGB'"
     * - "IF option_lumineux == true THEN require option_transformateur IS NOT NULL"
     *
     * TYPE_EXCLUDE :
     * - "EXCLUDE (taille == 'XL' AND fixation == 'murale')"
     * - "EXCLUDE (materiau == 'PVC' AND finition == 'anodisation')"
     *
     * TYPE_IF_THEN :
     * - "IF materiau == 'inox' THEN finition != 'peinture'"
     * - "IF largeur > 2000 THEN nb_potences >= 4"
     *
     * TYPE_FORMULA :
     * - "largeur * hauteur <= 6000000" (max 6m²)
     * - "nb_lettres * hauteur_lettre <= largeur_panneau"
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $expression;

    /**
     * Message d'erreur affiché si la règle n'est pas respectée
     *
     * Exemples :
     * - "L'éclairage RGB nécessite un contrôleur RGB."
     * - "Cette taille d'enseigne ne peut pas être fixée au mur, choisissez une fixation sur pied."
     * - "L'inox ne peut pas être peint, choisissez une autre finition."
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $messageErreur;

    /**
     * Priorité d'exécution
     * Les règles avec priorité haute sont évaluées en premier
     * Utile pour les règles qui en bloquent d'autres
     */
    #[ORM\Column]
    private int $priorite = 0;

    /**
     * Type de message (erreur bloquante ou avertissement)
     */
    #[ORM\Column(length: 20)]
    private string $severite = self::SEVERITE_ERREUR;

    /**
     * Constantes pour la sévérité
     */
    public const SEVERITE_ERREUR = 'ERREUR';         // Bloquant, config invalide
    public const SEVERITE_AVERTISSEMENT = 'AVERTISSEMENT';  // Non bloquant, simple info
    public const SEVERITE_INFO = 'INFO';             // Informatif uniquement

    /**
     * Actions automatiques si règle violée (JSON)
     *
     * Permet de corriger automatiquement la configuration
     *
     * Exemples :
     * {
     *   "set": {"option_controleur": "RGB"},
     *   "unset": ["option_fixation_murale"],
     *   "suggest": ["option_fixation_pied"]
     * }
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $actionsAuto = null;

    /**
     * Description technique de la règle
     * Pour la documentation interne
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $actif = true;

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
        return $this->nom;
    }

    /**
     * Retourne le libellé du type de règle
     */
    public function getTypeRegleLibelle(): string
    {
        return match($this->typeRegle) {
            self::TYPE_REQUIRE => 'Nécessite',
            self::TYPE_EXCLUDE => 'Exclut',
            self::TYPE_IF_THEN => 'Si alors',
            self::TYPE_FORMULA => 'Formule',
            default => 'Inconnu'
        };
    }

    /**
     * Retourne le libellé de la sévérité
     */
    public function getSeveriteLibelle(): string
    {
        return match($this->severite) {
            self::SEVERITE_ERREUR => 'Erreur bloquante',
            self::SEVERITE_AVERTISSEMENT => 'Avertissement',
            self::SEVERITE_INFO => 'Information',
            default => 'Inconnu'
        };
    }

    /**
     * Retourne la couleur Bootstrap selon la sévérité
     */
    public function getSeveriteCouleur(): string
    {
        return match($this->severite) {
            self::SEVERITE_ERREUR => 'danger',
            self::SEVERITE_AVERTISSEMENT => 'warning',
            self::SEVERITE_INFO => 'info',
            default => 'secondary'
        };
    }

    /**
     * Vérifie si la règle est bloquante
     */
    public function isBloquante(): bool
    {
        return $this->severite === self::SEVERITE_ERREUR;
    }

    /**
     * Vérifie si la règle a des actions automatiques
     */
    public function hasActionsAuto(): bool
    {
        return !empty($this->actionsAuto);
    }

    /**
     * Récupère une action automatique
     */
    public function getActionAuto(string $key): mixed
    {
        return $this->actionsAuto[$key] ?? null;
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getTypeRegle(): ?string
    {
        return $this->typeRegle;
    }

    public function setTypeRegle(string $typeRegle): static
    {
        $this->typeRegle = $typeRegle;
        return $this;
    }

    public function getExpression(): ?string
    {
        return $this->expression;
    }

    public function setExpression(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    public function getMessageErreur(): ?string
    {
        return $this->messageErreur;
    }

    public function setMessageErreur(string $messageErreur): static
    {
        $this->messageErreur = $messageErreur;
        return $this;
    }

    public function getPriorite(): ?int
    {
        return $this->priorite;
    }

    public function setPriorite(int $priorite): static
    {
        $this->priorite = $priorite;
        return $this;
    }

    public function getSeverite(): ?string
    {
        return $this->severite;
    }

    public function setSeverite(string $severite): static
    {
        $this->severite = $severite;
        return $this;
    }

    public function getActionsAuto(): ?array
    {
        return $this->actionsAuto;
    }

    public function setActionsAuto(?array $actionsAuto): static
    {
        $this->actionsAuto = $actionsAuto;
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
