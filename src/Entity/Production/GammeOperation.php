<?php

namespace App\Entity\Production;

use App\Repository\Production\GammeOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Opération de gamme (étape de fabrication)
 *
 * Représente une étape dans le processus de fabrication :
 * - Poste de travail utilisé
 * - Temps de réalisation (fixe ou calculé dynamiquement)
 * - Instructions pour l'opérateur
 * - Possibilité d'exécution parallèle
 *
 * Le temps peut être :
 * - FIXE : temps constant (ex: 30 minutes)
 * - FORMULE : calculé dynamiquement selon configuration
 *   Exemples formules :
 *   - "surface * 0.5 + 30" → 30min setup + 0.5min/m²
 *   - "nb_lettres * 15 + nb_leds * 2" → 15min/lettre + 2min/LED
 *   - "(largeur + hauteur) * 0.1" → périmètre × 0.1min
 */
#[ORM\Entity(repositoryClass: GammeOperationRepository::class)]
#[ORM\Table(name: 'gamme_operation')]
#[ORM\HasLifecycleCallbacks]
class GammeOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gamme::class, inversedBy: 'operations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Gamme $gamme = null;

    /**
     * Ordre d'exécution
     * Incréments de 10 pour faciliter l'insertion : 10, 20, 30, 40...
     */
    #[ORM\Column]
    private int $ordre = 10;

    /**
     * Code de l'opération
     * Exemples : "OP010", "OP020", "IMPRES", "DECOUP"
     */
    #[ORM\Column(length: 50)]
    private string $code;

    /**
     * Libellé court de l'opération
     * Exemples : "Impression face avant", "Découpe au plotter", "Montage LED"
     */
    #[ORM\Column(length: 255)]
    private string $libelle;

    /**
     * Poste de travail / Machine utilisé
     */
    #[ORM\ManyToOne(targetEntity: PosteTravail::class, inversedBy: 'operations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PosteTravail $posteTravail = null;

    /**
     * Type de calcul du temps
     */
    #[ORM\Column(length: 30)]
    private string $typeTemps = self::TYPE_TEMPS_FIXE;

    /**
     * Constantes pour les types de temps
     */
    public const TYPE_TEMPS_FIXE = 'FIXE';           // Temps constant
    public const TYPE_TEMPS_FORMULE = 'FORMULE';     // Formule de calcul

    /**
     * Temps fixe (minutes)
     * Utilisé si typeTemps = FIXE
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $tempsFixe = 0;

    /**
     * Formule de calcul du temps (minutes)
     * Utilisée si typeTemps = FORMULE
     *
     * Variables disponibles (selon configuration produit) :
     * - largeur, hauteur : dimensions en mm
     * - surface : surface en m²
     * - perimetre : périmètre en m
     * - quantite : quantité à produire
     * - nb_lettres, nb_leds, etc. : paramètres spécifiques
     *
     * Opérateurs : +, -, *, /, (, )
     *
     * Exemples :
     * - "surface * 0.5 + 30" → 30min setup + 0.5min par m²
     * - "nb_lettres * 15" → 15 minutes par lettre
     * - "(largeur + hauteur) * 2 / 1000 * 0.1" → périmètre en m × 0.1min/m
     * - "surface * 2 + 45" → impression avec setup 45min + 2min/m²
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $formuleTemps = null;

    /**
     * Opération parallèle
     * Si true, peut être exécutée en même temps que la suivante
     * Exemple : pendant l'impression, on peut préparer le montage
     */
    #[ORM\Column]
    private bool $tempsParallele = false;

    /**
     * Condition d'exécution (expression)
     *
     * Permet de conditionner l'opération selon options choisies.
     *
     * Syntaxe simple :
     * - "option_eclairage != 'aucun'" → seulement si éclairage
     * - "option_lumineux == true" → seulement si lumineux
     * - "finition == 'vernis'" → seulement si vernis choisi
     *
     * Si vide → toujours exécutée
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionExecution = null;

    /**
     * Instructions pour l'opérateur
     * Consignes détaillées de réalisation
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    /**
     * Paramètres machine (JSON)
     * Configuration spécifique selon machine
     *
     * Exemples :
     * - {"vitesse": "80%", "pression": 4, "temperature": 160}
     * - {"resolution": "720dpi", "passes": 2}
     * - {"profondeur_coupe": "19mm", "vitesse_avance": "3000mm/min"}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametresMachine = null;

    /**
     * Contrôle qualité requis après cette opération
     */
    #[ORM\Column]
    private bool $controleQualite = false;

    /**
     * Description du contrôle qualité
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionControle = null;

    /**
     * Notes techniques
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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
        return $this->code . ' - ' . $this->libelle;
    }

    /**
     * Retourne le libellé complet avec code
     */
    public function getLibelleComplet(): string
    {
        return sprintf('[%s] %s', $this->code, $this->libelle);
    }

    /**
     * Retourne le libellé du type de temps
     */
    public function getTypeTempsLibelle(): string
    {
        return match($this->typeTemps) {
            self::TYPE_TEMPS_FIXE => 'Temps fixe',
            self::TYPE_TEMPS_FORMULE => 'Formule de calcul',
            default => 'Inconnu'
        };
    }

    /**
     * Vérifie si l'opération a une formule
     */
    public function hasFormule(): bool
    {
        return $this->typeTemps === self::TYPE_TEMPS_FORMULE && !empty($this->formuleTemps);
    }

    /**
     * Vérifie si l'opération a une condition d'exécution
     */
    public function hasCondition(): bool
    {
        return !empty($this->conditionExecution);
    }

    /**
     * Vérifie si l'opération a des paramètres machine
     */
    public function hasParametresMachine(): bool
    {
        return !empty($this->parametresMachine);
    }

    /**
     * Récupère un paramètre machine
     */
    public function getParametreMachine(string $key): mixed
    {
        return $this->parametresMachine[$key] ?? null;
    }

    /**
     * Définit un paramètre machine
     */
    public function setParametreMachine(string $key, mixed $value): static
    {
        $params = $this->parametresMachine ?? [];
        $params[$key] = $value;
        $this->parametresMachine = $params;
        return $this;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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

    public function getPosteTravail(): ?PosteTravail
    {
        return $this->posteTravail;
    }

    public function setPosteTravail(?PosteTravail $posteTravail): static
    {
        $this->posteTravail = $posteTravail;
        return $this;
    }

    public function getTypeTemps(): ?string
    {
        return $this->typeTemps;
    }

    public function setTypeTemps(string $typeTemps): static
    {
        $this->typeTemps = $typeTemps;
        return $this;
    }

    public function getTempsFixe(): ?int
    {
        return $this->tempsFixe;
    }

    public function setTempsFixe(int $tempsFixe): static
    {
        $this->tempsFixe = $tempsFixe;
        return $this;
    }

    public function getFormuleTemps(): ?string
    {
        return $this->formuleTemps;
    }

    public function setFormuleTemps(?string $formuleTemps): static
    {
        $this->formuleTemps = $formuleTemps;
        return $this;
    }

    public function isTempsParallele(): ?bool
    {
        return $this->tempsParallele;
    }

    public function setTempsParallele(bool $tempsParallele): static
    {
        $this->tempsParallele = $tempsParallele;
        return $this;
    }

    public function getConditionExecution(): ?string
    {
        return $this->conditionExecution;
    }

    public function setConditionExecution(?string $conditionExecution): static
    {
        $this->conditionExecution = $conditionExecution;
        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): static
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function getParametresMachine(): ?array
    {
        return $this->parametresMachine;
    }

    public function setParametresMachine(?array $parametresMachine): static
    {
        $this->parametresMachine = $parametresMachine;
        return $this;
    }

    public function isControleQualite(): ?bool
    {
        return $this->controleQualite;
    }

    public function setControleQualite(bool $controleQualite): static
    {
        $this->controleQualite = $controleQualite;
        return $this;
    }

    public function getDescriptionControle(): ?string
    {
        return $this->descriptionControle;
    }

    public function setDescriptionControle(?string $descriptionControle): static
    {
        $this->descriptionControle = $descriptionControle;
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
