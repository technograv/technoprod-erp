<?php

namespace App\Entity;

use App\Repository\TypeSecteurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeSecteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TypeSecteur
{
    public const TYPE_CODE_POSTAL = 'code_postal';
    public const TYPE_COMMUNE = 'commune';
    public const TYPE_CANTON = 'canton';
    public const TYPE_EPCI = 'epci';
    public const TYPE_DEPARTEMENT = 'departement';
    public const TYPE_REGION = 'region';

    public const TYPES_DISPONIBLES = [
        self::TYPE_CODE_POSTAL => 'Par code postal',
        self::TYPE_COMMUNE => 'Par commune',
        self::TYPE_CANTON => 'Par canton',
        self::TYPE_EPCI => 'Par intercommunalité (EPCI)',
        self::TYPE_DEPARTEMENT => 'Par département',
        self::TYPE_REGION => 'Par région'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 20)]
    private ?string $type = self::TYPE_CODE_POSTAL;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private int $ordre = 1;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Secteur::class, mappedBy: 'typeSecteur')]
    private Collection $secteurs;

    public function __construct()
    {
        $this->secteurs = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
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
     * @return Collection<int, Secteur>
     */
    public function getSecteurs(): Collection
    {
        return $this->secteurs;
    }

    public function addSecteur(Secteur $secteur): static
    {
        if (!$this->secteurs->contains($secteur)) {
            $this->secteurs->add($secteur);
            $secteur->setTypeSecteur($this);
        }

        return $this;
    }

    public function removeSecteur(Secteur $secteur): static
    {
        if ($this->secteurs->removeElement($secteur)) {
            if ($secteur->getTypeSecteur() === $this) {
                $secteur->setTypeSecteur(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?: '';
    }

    /**
     * Retourne le libellé du type
     */
    public function getTypeLibelle(): string
    {
        return self::TYPES_DISPONIBLES[$this->type] ?? 'Type inconnu';
    }

    /**
     * Retourne la description du champ de regroupement pour ce type
     */
    public function getChampRegroupement(): string
    {
        return match($this->type) {
            self::TYPE_CODE_POSTAL => 'code_postal',
            self::TYPE_COMMUNE => 'code_insee_commune',
            self::TYPE_CANTON => 'code_canton',
            self::TYPE_EPCI => 'code_epci',
            self::TYPE_DEPARTEMENT => 'code_departement',
            self::TYPE_REGION => 'code_region',
            default => 'code_postal'
        };
    }

    /**
     * Retourne le nom du champ pour l'affichage
     */
    public function getChampNom(): string
    {
        return match($this->type) {
            self::TYPE_CODE_POSTAL => 'code_postal',
            self::TYPE_COMMUNE => 'nom_commune',
            self::TYPE_CANTON => 'nom_canton',
            self::TYPE_EPCI => 'nom_epci',
            self::TYPE_DEPARTEMENT => 'nom_departement',
            self::TYPE_REGION => 'nom_region',
            default => 'nom_commune'
        };
    }

    /**
     * Retourne les critères de recherche pour ce type
     */
    public function getCritereRecherche(): array
    {
        return match($this->type) {
            self::TYPE_CODE_POSTAL => ['code_postal', 'nom_commune'],
            self::TYPE_COMMUNE => ['nom_commune', 'code_postal'],
            self::TYPE_CANTON => ['nom_canton', 'nom_commune'],
            self::TYPE_EPCI => ['nom_epci', 'type_epci'],
            self::TYPE_DEPARTEMENT => ['nom_departement', 'code_departement'],
            self::TYPE_REGION => ['nom_region', 'code_region'],
            default => ['nom_commune', 'code_postal']
        };
    }

    /**
     * Génère un exemple d'utilisation pour ce type
     */
    public function getExempleUtilisation(): string
    {
        return match($this->type) {
            self::TYPE_CODE_POSTAL => 'Ex: 77100, 77200, 77300 (codes postaux de Meaux et environs)',
            self::TYPE_COMMUNE => 'Ex: Meaux, Villenoy, Nanteuil-lès-Meaux',
            self::TYPE_CANTON => 'Ex: Canton de Meaux, Canton de Coulommiers',
            self::TYPE_EPCI => 'Ex: CC du Pays de Meaux, CA de Coulommiers',
            self::TYPE_DEPARTEMENT => 'Ex: Seine-et-Marne (77), Val-de-Marne (94)',
            self::TYPE_REGION => 'Ex: Île-de-France, Hauts-de-France',
            default => 'Exemple non disponible'
        };
    }

    /**
     * Vérifie si ce type permet la sélection multiple
     */
    public function permetSelectionMultiple(): bool
    {
        return in_array($this->type, [
            self::TYPE_CODE_POSTAL,
            self::TYPE_COMMUNE,
            self::TYPE_CANTON,
            self::TYPE_EPCI
        ]);
    }

    /**
     * Vérifie si ce type est hiérarchique (inclut automatiquement les sous-divisions)
     */
    public function estHierarchique(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPARTEMENT,
            self::TYPE_REGION,
            self::TYPE_EPCI
        ]);
    }
}