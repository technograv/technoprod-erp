<?php

namespace App\Entity;

use App\Repository\ExclusionSecteurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExclusionSecteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(columns: ['attribution_secteur_id', 'division_administrative_id'], name: 'unique_exclusion')]
class ExclusionSecteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Attribution secteur parente (ex: EPCI) dont on exclut une zone
    #[ORM\ManyToOne(targetEntity: AttributionSecteur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?AttributionSecteur $attributionSecteur = null;

    // Division administrative exclue (ex: commune spécifique dans l'EPCI)
    #[ORM\ManyToOne(targetEntity: DivisionAdministrative::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?DivisionAdministrative $divisionAdministrative = null;

    // Type de la zone exclue (commune, code_postal, etc.)
    #[ORM\Column(length: 20)]
    private ?string $typeExclusion = null;

    // Valeur de la zone exclue (code INSEE, code postal, etc.)
    #[ORM\Column(length: 50)]
    private ?string $valeurExclusion = null;

    // Motif de l'exclusion
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttributionSecteur(): ?AttributionSecteur
    {
        return $this->attributionSecteur;
    }

    public function setAttributionSecteur(?AttributionSecteur $attributionSecteur): static
    {
        $this->attributionSecteur = $attributionSecteur;
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

    public function getTypeExclusion(): ?string
    {
        return $this->typeExclusion;
    }

    public function setTypeExclusion(string $typeExclusion): static
    {
        $this->typeExclusion = $typeExclusion;
        return $this;
    }

    public function getValeurExclusion(): ?string
    {
        return $this->valeurExclusion;
    }

    public function setValeurExclusion(string $valeurExclusion): static
    {
        $this->valeurExclusion = $valeurExclusion;
        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
