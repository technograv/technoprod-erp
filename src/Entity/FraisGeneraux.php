<?php

namespace App\Entity;

use App\Repository\FraisGenerauxRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FraisGenerauxRepository::class)]
#[ORM\Table(name: 'frais_generaux')]
class FraisGeneraux
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire')]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $montantMensuel = '0.00';

    #[ORM\Column(length: 30)]
    private string $typeRepartition = 'volume_devis'; // 'volume_devis', 'ligne_cachee', 'coefficient_global', 'par_heure_mo'

    // Pour type='volume_devis'
    #[ORM\Column(nullable: true)]
    private ?int $volumeDevisMensuelEstime = null;

    // Pour type='par_heure_mo'
    #[ORM\Column(nullable: true)]
    private ?int $heuresMOMensuelles = null;

    // Pour type='coefficient_global'
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 4, nullable: true)]
    private ?string $coefficientMajoration = null; // Ex: 1.15 pour +15%

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(length: 7)] // Format: YYYY-MM
    private ?string $periode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->periode = (new \DateTimeImmutable())->format('Y-m');
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMontantMensuel(): ?string
    {
        return $this->montantMensuel;
    }

    public function setMontantMensuel(string $montantMensuel): static
    {
        $this->montantMensuel = $montantMensuel;
        return $this;
    }

    public function getTypeRepartition(): string
    {
        return $this->typeRepartition;
    }

    public function setTypeRepartition(string $typeRepartition): static
    {
        $this->typeRepartition = $typeRepartition;
        return $this;
    }

    public function getVolumeDevisMensuelEstime(): ?int
    {
        return $this->volumeDevisMensuelEstime;
    }

    public function setVolumeDevisMensuelEstime(?int $volumeDevisMensuelEstime): static
    {
        $this->volumeDevisMensuelEstime = $volumeDevisMensuelEstime;
        return $this;
    }

    public function getHeuresMOMensuelles(): ?int
    {
        return $this->heuresMOMensuelles;
    }

    public function setHeuresMOMensuelles(?int $heuresMOMensuelles): static
    {
        $this->heuresMOMensuelles = $heuresMOMensuelles;
        return $this;
    }

    public function getCoefficientMajoration(): ?string
    {
        return $this->coefficientMajoration;
    }

    public function setCoefficientMajoration(?string $coefficientMajoration): static
    {
        $this->coefficientMajoration = $coefficientMajoration;
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

    public function getPeriode(): ?string
    {
        return $this->periode;
    }

    public function setPeriode(string $periode): static
    {
        $this->periode = $periode;
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
     * Calcule le montant par devis (si type = volume_devis)
     */
    public function getMontantParDevis(): ?float
    {
        if ($this->typeRepartition === 'volume_devis' && $this->volumeDevisMensuelEstime > 0) {
            return (float) $this->montantMensuel / $this->volumeDevisMensuelEstime;
        }
        return null;
    }

    /**
     * Calcule le coût par heure MO (si type = par_heure_mo)
     */
    public function getCoutParHeureMO(): ?float
    {
        if ($this->typeRepartition === 'par_heure_mo' && $this->heuresMOMensuelles > 0) {
            return (float) $this->montantMensuel / $this->heuresMOMensuelles;
        }
        return null;
    }

    public function getTypeRepartitionLibelle(): string
    {
        return match($this->typeRepartition) {
            'volume_devis' => 'Par volume de devis',
            'ligne_cachee' => 'Ligne cachée',
            'coefficient_global' => 'Coefficient global',
            'par_heure_mo' => 'Par heure main d\'œuvre',
            default => 'Inconnu'
        };
    }

    public function __toString(): string
    {
        return $this->libelle ?? 'Frais généraux #' . $this->id;
    }
}
