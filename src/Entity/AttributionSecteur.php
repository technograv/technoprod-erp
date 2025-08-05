<?php

namespace App\Entity;

use App\Repository\AttributionSecteurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributionSecteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(columns: ['secteur_id', 'division_administrative_id'], name: 'unique_secteur_division')]
class AttributionSecteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Secteur $secteur = null;

    #[ORM\ManyToOne(inversedBy: 'attributionsSecteur')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DivisionAdministrative $divisionAdministrative = null;

    // Valeur du critère d'attribution (code postal, code commune, etc.)
    #[ORM\Column(length: 50)]
    private ?string $valeurCritere = null;

    // Type de critère (repris du TypeSecteur pour optimisation)
    #[ORM\Column(length: 20)]
    private ?string $typeCritere = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getSecteur(): ?Secteur
    {
        return $this->secteur;
    }

    public function setSecteur(?Secteur $secteur): static
    {
        $this->secteur = $secteur;
        return $this;
    }

    public function getDivisionAdministrative(): ?DivisionAdministrative
    {
        return $this->divisionAdministrative;
    }

    public function setDivisionAdministrative(?DivisionAdministrative $divisionAdministrative): static
    {
        $this->divisionAdministrative = $divisionAdministrative;
        return $this;
    }

    public function getValeurCritere(): ?string
    {
        return $this->valeurCritere;
    }

    public function setValeurCritere(string $valeurCritere): static
    {
        $this->valeurCritere = $valeurCritere;
        return $this;
    }

    public function getTypeCritere(): ?string
    {
        return $this->typeCritere;
    }

    public function setTypeCritere(string $typeCritere): static
    {
        $this->typeCritere = $typeCritere;
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

    public function __toString(): string
    {
        $division = $this->divisionAdministrative;
        if (!$division) {
            return $this->valeurCritere ?: '';
        }

        return match($this->typeCritere) {
            TypeSecteur::TYPE_CODE_POSTAL => $division->getCodePostal() . ' ' . $division->getNomCommune(),
            TypeSecteur::TYPE_COMMUNE => $division->getNomCommune() . ' (' . $division->getCodePostal() . ')',
            TypeSecteur::TYPE_CANTON => $division->getNomCanton(),
            TypeSecteur::TYPE_EPCI => $division->getTypeEpci() . ' ' . $division->getNomEpci(),
            TypeSecteur::TYPE_DEPARTEMENT => $division->getNomDepartement() . ' (' . $division->getCodeDepartement() . ')',
            TypeSecteur::TYPE_REGION => $division->getNomRegion(),
            default => $this->valeurCritere
        };
    }

    /**
     * Retourne l'affichage avec le nom du commercial
     */
    public function getAffichageAvecCommercial(): string
    {
        $commercial = $this->secteur?->getCommercial();
        $division = $this->__toString();
        
        if ($commercial) {
            return sprintf('%s → %s', $division, $commercial->getFullName());
        }
        
        return $division;
    }

    /**
     * Vérifie si cette attribution couvre la division administrative donnée
     */
    public function couvre(DivisionAdministrative $division): bool
    {
        return match($this->typeCritere) {
            TypeSecteur::TYPE_CODE_POSTAL => $division->getCodePostal() === $this->valeurCritere,
            TypeSecteur::TYPE_COMMUNE => $division->getCodeInseeCommune() === $this->valeurCritere,
            TypeSecteur::TYPE_CANTON => $division->getCodeCanton() === $this->valeurCritere,
            TypeSecteur::TYPE_EPCI => $division->getCodeEpci() === $this->valeurCritere,
            TypeSecteur::TYPE_DEPARTEMENT => $division->getCodeDepartement() === $this->valeurCritere,
            TypeSecteur::TYPE_REGION => $division->getCodeRegion() === $this->valeurCritere,
            default => false
        };
    }
    
    /**
     * Crée une attribution automatiquement depuis une division administrative
     */
    public static function creerDepuisDivision(Secteur $secteur, DivisionAdministrative $division, string $typeCritere): self
    {
        $attribution = new self();
        $attribution->setSecteur($secteur);
        $attribution->setDivisionAdministrative($division);
        $attribution->setTypeCritere($typeCritere);
        
        // Définir la valeur du critère selon le type
        $valeur = match($typeCritere) {
            TypeSecteur::TYPE_CODE_POSTAL => $division->getCodePostal(),
            TypeSecteur::TYPE_COMMUNE => $division->getCodeInseeCommune(),
            TypeSecteur::TYPE_CANTON => $division->getCodeCanton(),
            TypeSecteur::TYPE_EPCI => $division->getCodeEpci(),
            TypeSecteur::TYPE_DEPARTEMENT => $division->getCodeDepartement(),
            TypeSecteur::TYPE_REGION => $division->getCodeRegion(),
            default => $division->getCodePostal()
        };
        
        $attribution->setValeurCritere($valeur);
        
        return $attribution;
    }
}