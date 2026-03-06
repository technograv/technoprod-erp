<?php

namespace App\Entity\Production;

use App\Repository\Production\NomenclatureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Nomenclature (Bill of Materials - BOM)
 *
 * Définit la composition d'un produit catalogue :
 * - Liste des matières premières nécessaires
 * - Sous-ensembles à fabriquer ou acheter
 * - Quantités et formules de calcul dynamiques
 *
 * Exemples :
 * - Enseigne drapeau 600x600 = caisson alu + PMMA + LEDs + potences
 * - Lettre découpée LED = plaque PVC + LEDs + transformateur + fils
 */
#[ORM\Entity(repositoryClass: NomenclatureRepository::class)]
#[ORM\Table(name: 'nomenclature')]
#[ORM\HasLifecycleCallbacks]
class Nomenclature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Version de la nomenclature
     * Permet de gérer l'évolution des produits
     */
    #[ORM\Column(length: 20)]
    private string $version = '1.0';

    /**
     * Nomenclature parent (si c'est un sous-ensemble)
     * Permet d'avoir des nomenclatures imbriquées
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sousNomenclatures')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /**
     * @var Collection<int, Nomenclature>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $sousNomenclatures;

    /**
     * @var Collection<int, NomenclatureLigne>
     */
    #[ORM\OneToMany(targetEntity: NomenclatureLigne::class, mappedBy: 'nomenclature', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $lignes;

    /**
     * Statut de la nomenclature
     */
    #[ORM\Column(length: 20)]
    private string $statut = 'BROUILLON';

    /**
     * Constantes pour les statuts
     */
    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_VALIDEE = 'VALIDEE';
    public const STATUT_OBSOLETE = 'OBSOLETE';

    /**
     * Date de validation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    /**
     * Utilisateur ayant validé
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $validePar = null;

    /**
     * Notes techniques internes
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->sousNomenclatures = new ArrayCollection();
        $this->lignes = new ArrayCollection();
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
     * Retourne le niveau dans la hiérarchie (0 = racine)
     */
    public function getNiveau(): int
    {
        $niveau = 0;
        $current = $this->parent;
        $maxDepth = 50; // Protection contre boucles

        while ($current !== null && $niveau < $maxDepth) {
            $niveau++;
            $current = $current->getParent();
        }

        return $niveau;
    }

    /**
     * Vérifie si la nomenclature est validée
     */
    public function isValidee(): bool
    {
        return $this->statut === self::STATUT_VALIDEE;
    }

    /**
     * Valide la nomenclature
     */
    public function valider(string $username): static
    {
        $this->statut = self::STATUT_VALIDEE;
        $this->dateValidation = new \DateTimeImmutable();
        $this->validePar = $username;
        return $this;
    }

    /**
     * Marque comme obsolète
     */
    public function rendreObsolete(): static
    {
        $this->statut = self::STATUT_OBSOLETE;
        $this->actif = false;
        return $this;
    }

    /**
     * Compte le nombre total de lignes (récursif si sous-nomenclatures)
     */
    public function compterLignes(bool $recursif = false): int
    {
        $count = $this->lignes->count();

        if ($recursif) {
            foreach ($this->lignes as $ligne) {
                if ($ligne->getNomenclatureEnfant()) {
                    $count += $ligne->getNomenclatureEnfant()->compterLignes(true);
                }
            }
        }

        return $count;
    }

    // Getters & Setters

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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, Nomenclature>
     */
    public function getSousNomenclatures(): Collection
    {
        return $this->sousNomenclatures;
    }

    public function addSousNomenclature(Nomenclature $sousNomenclature): static
    {
        if (!$this->sousNomenclatures->contains($sousNomenclature)) {
            $this->sousNomenclatures->add($sousNomenclature);
            $sousNomenclature->setParent($this);
        }

        return $this;
    }

    public function removeSousNomenclature(Nomenclature $sousNomenclature): static
    {
        if ($this->sousNomenclatures->removeElement($sousNomenclature)) {
            if ($sousNomenclature->getParent() === $this) {
                $sousNomenclature->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, NomenclatureLigne>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(NomenclatureLigne $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setNomenclature($this);
        }

        return $this;
    }

    public function removeLigne(NomenclatureLigne $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getNomenclature() === $this) {
                $ligne->setNomenclature(null);
            }
        }

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function getValidePar(): ?string
    {
        return $this->validePar;
    }

    public function setValidePar(?string $validePar): static
    {
        $this->validePar = $validePar;
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
