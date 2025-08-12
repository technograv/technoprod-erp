<?php

namespace App\Entity;

use App\Repository\ExerciceComptableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciceComptableRepository::class)]
#[ORM\Table(name: 'exercice_comptable')]
class ExerciceComptable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $anneeExercice; // 2025

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateDebut; // 01/01/2025

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateFin; // 31/12/2025

    #[ORM\Column(length: 20)]
    private string $statut = 'ouvert'; // ouvert, cloture, archive

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCloture = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'exerciceComptable', targetEntity: EcritureComptable::class)]
    private Collection $ecrituresComptables;

    // Totaux de contrôle
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalDebit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalCredit = '0.00';

    #[ORM\Column]
    private int $nombreEcritures = 0;

    #[ORM\Column]
    private int $nombreLignesEcriture = 0;

    // Utilisateurs de gestion
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $cloturePar = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $validePar = null;

    // Métadonnées
    #[ORM\Column(type: Types::JSON)]
    private array $metadonnees = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->ecrituresComptables = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnneeExercice(): int
    {
        return $this->anneeExercice;
    }

    public function setAnneeExercice(int $anneeExercice): static
    {
        $this->anneeExercice = $anneeExercice;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateFin(): \DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateCloture(): ?\DateTimeInterface
    {
        return $this->dateCloture;
    }

    public function setDateCloture(?\DateTimeInterface $dateCloture): static
    {
        $this->dateCloture = $dateCloture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateValidation(): ?\DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeInterface $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * @return Collection<int, EcritureComptable>
     */
    public function getEcrituresComptables(): Collection
    {
        return $this->ecrituresComptables;
    }

    public function addEcrituresComptable(EcritureComptable $ecrituresComptable): static
    {
        if (!$this->ecrituresComptables->contains($ecrituresComptable)) {
            $this->ecrituresComptables->add($ecrituresComptable);
            $ecrituresComptable->setExerciceComptable($this);
        }

        return $this;
    }

    public function removeEcrituresComptable(EcritureComptable $ecrituresComptable): static
    {
        if ($this->ecrituresComptables->removeElement($ecrituresComptable)) {
            // set the owning side to null (unless already changed)
            if ($ecrituresComptable->getExerciceComptable() === $this) {
                $ecrituresComptable->setExerciceComptable(null);
            }
        }

        return $this;
    }

    public function getTotalDebit(): string
    {
        return $this->totalDebit;
    }

    public function setTotalDebit(string $totalDebit): static
    {
        $this->totalDebit = $totalDebit;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getTotalCredit(): string
    {
        return $this->totalCredit;
    }

    public function setTotalCredit(string $totalCredit): static
    {
        $this->totalCredit = $totalCredit;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getNombreEcritures(): int
    {
        return $this->nombreEcritures;
    }

    public function setNombreEcritures(int $nombreEcritures): static
    {
        $this->nombreEcritures = $nombreEcritures;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getNombreLignesEcriture(): int
    {
        return $this->nombreLignesEcriture;
    }

    public function setNombreLignesEcriture(int $nombreLignesEcriture): static
    {
        $this->nombreLignesEcriture = $nombreLignesEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCloturePar(): ?User
    {
        return $this->cloturePar;
    }

    public function setCloturePar(?User $cloturePar): static
    {
        $this->cloturePar = $cloturePar;

        return $this;
    }

    public function getValidePar(): ?User
    {
        return $this->validePar;
    }

    public function setValidePar(?User $validePar): static
    {
        $this->validePar = $validePar;

        return $this;
    }

    public function getMetadonnees(): array
    {
        return $this->metadonnees;
    }

    public function setMetadonnees(array $metadonnees): static
    {
        $this->metadonnees = $metadonnees;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Vérifie si l'exercice est ouvert
     */
    public function isOuvert(): bool
    {
        return $this->statut === 'ouvert';
    }

    /**
     * Vérifie si l'exercice est clos
     */
    public function isClos(): bool
    {
        return $this->statut === 'cloture';
    }

    /**
     * Vérifie si l'exercice est archivé
     */
    public function isArchive(): bool
    {
        return $this->statut === 'archive';
    }

    /**
     * Vérifie si une date appartient à cet exercice
     */
    public function containsDate(\DateTimeInterface $date): bool
    {
        return $date >= $this->dateDebut && $date <= $this->dateFin;
    }

    /**
     * Clôture l'exercice comptable
     */
    public function cloturer(User $user): static
    {
        if ($this->statut === 'ouvert') {
            $this->statut = 'cloture';
            $this->dateCloture = new \DateTime();
            $this->cloturePar = $user;
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    /**
     * Archive l'exercice comptable
     */
    public function archiver(): static
    {
        if ($this->statut === 'cloture') {
            $this->statut = 'archive';
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    /**
     * Met à jour les totaux de contrôle
     */
    public function updateTotaux(): static
    {
        $totalDebit = '0.00';
        $totalCredit = '0.00';
        $nombreEcritures = 0;
        $nombreLignes = 0;

        foreach ($this->ecrituresComptables as $ecriture) {
            $nombreEcritures++;
            foreach ($ecriture->getLignesEcriture() as $ligne) {
                $nombreLignes++;
                $totalDebit = bcadd($totalDebit, $ligne->getMontantDebit(), 2);
                $totalCredit = bcadd($totalCredit, $ligne->getMontantCredit(), 2);
            }
        }

        $this->totalDebit = $totalDebit;
        $this->totalCredit = $totalCredit;
        $this->nombreEcritures = $nombreEcritures;
        $this->nombreLignesEcriture = $nombreLignes;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Vérifie l'équilibre de l'exercice
     */
    public function isEquilibre(): bool
    {
        return bccomp($this->totalDebit, $this->totalCredit, 2) === 0;
    }

    /**
     * Retourne la période comptable au format texte
     */
    public function getPeriodeTexte(): string
    {
        return $this->dateDebut->format('d/m/Y') . ' - ' . $this->dateFin->format('d/m/Y');
    }

    public function __toString(): string
    {
        return 'Exercice ' . $this->anneeExercice . ' (' . $this->getPeriodeTexte() . ')';
    }
}