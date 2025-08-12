<?php

namespace App\Entity;

use App\Repository\DivisionAdministrativeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DivisionAdministrativeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['code_postal'], name: 'idx_code_postal')]
#[ORM\Index(columns: ['code_insee_commune'], name: 'idx_insee_commune')]
#[ORM\Index(columns: ['code_departement'], name: 'idx_departement')]
class DivisionAdministrative
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Codes postaux (peut y en avoir plusieurs pour une commune)
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $codePostal = null;

    // Commune
    #[ORM\Column(length: 5)]
    private ?string $codeInseeCommune = null;

    #[ORM\Column(length: 150)]
    private ?string $nomCommune = null;

    // Canton
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeCanton = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nomCanton = null;

    // Intercommunalité (EPCI)
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeEpci = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $nomEpci = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeEpci = null; // CC, CA, CU, ME

    // Département
    #[ORM\Column(length: 3)]
    private ?string $codeDepartement = null;

    #[ORM\Column(length: 100)]
    private ?string $nomDepartement = null;

    // Région
    #[ORM\Column(length: 5)]
    private ?string $codeRegion = null;

    #[ORM\Column(length: 100)]
    private ?string $nomRegion = null;

    // Coordonnées géographiques
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    // Population (données INSEE)
    #[ORM\Column(nullable: true)]
    private ?int $population = null;

    // Métadonnées
    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations avec secteurs (via attribution)
    #[ORM\OneToMany(targetEntity: AttributionSecteur::class, mappedBy: 'divisionAdministrative')]
    private Collection $attributionsSecteur;

    public function __construct()
    {
        $this->attributionsSecteur = new ArrayCollection();
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

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getCodeInseeCommune(): ?string
    {
        return $this->codeInseeCommune;
    }

    public function setCodeInseeCommune(string $codeInseeCommune): static
    {
        $this->codeInseeCommune = $codeInseeCommune;
        return $this;
    }

    public function getNomCommune(): ?string
    {
        return $this->nomCommune;
    }

    public function setNomCommune(string $nomCommune): static
    {
        $this->nomCommune = $nomCommune;
        return $this;
    }

    public function getCodeCanton(): ?string
    {
        return $this->codeCanton;
    }

    public function setCodeCanton(?string $codeCanton): static
    {
        $this->codeCanton = $codeCanton;
        return $this;
    }

    public function getNomCanton(): ?string
    {
        return $this->nomCanton;
    }

    public function setNomCanton(?string $nomCanton): static
    {
        $this->nomCanton = $nomCanton;
        return $this;
    }

    public function getCodeEpci(): ?string
    {
        return $this->codeEpci;
    }

    public function setCodeEpci(?string $codeEpci): static
    {
        $this->codeEpci = $codeEpci;
        return $this;
    }

    public function getNomEpci(): ?string
    {
        return $this->nomEpci;
    }

    public function setNomEpci(?string $nomEpci): static
    {
        $this->nomEpci = $nomEpci;
        return $this;
    }

    public function getTypeEpci(): ?string
    {
        return $this->typeEpci;
    }

    public function setTypeEpci(?string $typeEpci): static
    {
        $this->typeEpci = $typeEpci;
        return $this;
    }

    public function getCodeDepartement(): ?string
    {
        return $this->codeDepartement;
    }

    public function setCodeDepartement(string $codeDepartement): static
    {
        $this->codeDepartement = $codeDepartement;
        return $this;
    }

    public function getNomDepartement(): ?string
    {
        return $this->nomDepartement;
    }

    public function setNomDepartement(string $nomDepartement): static
    {
        $this->nomDepartement = $nomDepartement;
        return $this;
    }

    public function getCodeRegion(): ?string
    {
        return $this->codeRegion;
    }

    public function setCodeRegion(string $codeRegion): static
    {
        $this->codeRegion = $codeRegion;
        return $this;
    }

    public function getNomRegion(): ?string
    {
        return $this->nomRegion;
    }

    public function setNomRegion(string $nomRegion): static
    {
        $this->nomRegion = $nomRegion;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getPopulation(): ?int
    {
        return $this->population;
    }

    public function setPopulation(?int $population): static
    {
        $this->population = $population;
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
     * @return Collection<int, AttributionSecteur>
     */
    public function getAttributionsSecteur(): Collection
    {
        return $this->attributionsSecteur;
    }

    public function addAttributionSecteur(AttributionSecteur $attributionSecteur): static
    {
        if (!$this->attributionsSecteur->contains($attributionSecteur)) {
            $this->attributionsSecteur->add($attributionSecteur);
            $attributionSecteur->setDivisionAdministrative($this);
        }

        return $this;
    }

    public function removeAttributionSecteur(AttributionSecteur $attributionSecteur): static
    {
        if ($this->attributionsSecteur->removeElement($attributionSecteur)) {
            if ($attributionSecteur->getDivisionAdministrative() === $this) {
                $attributionSecteur->setDivisionAdministrative(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s (%s)', $this->codePostal, $this->nomCommune, $this->nomDepartement);
    }

    /**
     * Retourne l'affichage complet avec toutes les divisions
     */
    public function getAffichageComplet(): string
    {
        $parts = [
            $this->codePostal . ' ' . $this->nomCommune
        ];

        if ($this->nomEpci) {
            $parts[] = $this->typeEpci . ' ' . $this->nomEpci;
        }

        if ($this->nomCanton) {
            $parts[] = 'Canton: ' . $this->nomCanton;
        }

        $parts[] = $this->nomDepartement . ' (' . $this->codeDepartement . ')';
        $parts[] = $this->nomRegion;

        return implode(' - ', $parts);
    }

    /**
     * Retourne le type EPCI en forme longue
     */
    public function getTypeEpciComplet(): ?string
    {
        return match($this->typeEpci) {
            'CC' => 'Communauté de Communes',
            'CA' => 'Communauté d\'Agglomération',
            'CU' => 'Communauté Urbaine',
            'ME' => 'Métropole',
            default => $this->typeEpci
        };
    }

    /**
     * Vérifie si cette division appartient au département spécifié
     */
    public function estDansDepartement(string $codeDepartement): bool
    {
        return $this->codeDepartement === $codeDepartement;
    }

    /**
     * Vérifie si cette division appartient à la région spécifiée
     */
    public function estDansRegion(string $codeRegion): bool
    {
        return $this->codeRegion === $codeRegion;
    }

    /**
     * Vérifie si cette division appartient à l'EPCI spécifié
     */
    public function estDansEpci(string $codeEpci): bool
    {
        return $this->codeEpci === $codeEpci;
    }
}