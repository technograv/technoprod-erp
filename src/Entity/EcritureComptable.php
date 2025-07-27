<?php

namespace App\Entity;

use App\Repository\EcritureComptableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EcritureComptableRepository::class)]
#[ORM\Table(name: 'ecriture_comptable')]
class EcritureComptable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // IDENTIFICATION FEC
    #[ORM\ManyToOne(targetEntity: JournalComptable::class, inversedBy: 'ecritures')]
    #[ORM\JoinColumn(nullable: false)]
    private JournalComptable $journal;

    #[ORM\Column(length: 20)]
    private string $numeroEcriture; // VTE20250001

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateEcriture;

    // LIBELLÉ ET RÉFÉRENCES
    #[ORM\Column(length: 255)]
    private string $libelleEcriture;

    #[ORM\Column(length: 20)]
    private string $numeroPiece; // Numéro facture, etc.

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $datePiece;

    // VALIDATION
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $validePar = null;

    #[ORM\Column]
    private bool $isValidee = false;

    #[ORM\Column]
    private bool $isEquilibree = false;

    // LETTRAGE
    #[ORM\Column(length: 3, nullable: true)]
    private ?string $lettrage = null; // Pour rapprochement

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLettrage = null;

    // LIENS DOCUMENTS MÉTIER
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $documentType = null; // facture, devis, avoir

    #[ORM\Column(nullable: true)]
    private ?int $documentId = null;

    // EXERCICE COMPTABLE
    #[ORM\ManyToOne(targetEntity: ExerciceComptable::class, inversedBy: 'ecrituresComptables')]
    #[ORM\JoinColumn(nullable: false)]
    private ExerciceComptable $exerciceComptable;

    // INTÉGRITÉ (liens avec système sécurité)
    #[ORM\OneToOne(targetEntity: DocumentIntegrity::class)]
    private ?DocumentIntegrity $integrite = null;

    // LIGNES D'ÉCRITURE
    #[ORM\OneToMany(mappedBy: 'ecriture', targetEntity: LigneEcriture::class, cascade: ['persist', 'remove'])]
    private Collection $lignesEcriture;

    // TOTAUX CALCULÉS
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalDebit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalCredit = '0.00';

    // MÉTADONNÉES
    #[ORM\Column(type: Types::JSON)]
    private array $metadonnees = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->lignesEcriture = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJournal(): JournalComptable
    {
        return $this->journal;
    }

    public function setJournal(JournalComptable $journal): static
    {
        $this->journal = $journal;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getNumeroEcriture(): string
    {
        return $this->numeroEcriture;
    }

    public function setNumeroEcriture(string $numeroEcriture): static
    {
        $this->numeroEcriture = $numeroEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateEcriture(): \DateTimeInterface
    {
        return $this->dateEcriture;
    }

    public function setDateEcriture(\DateTimeInterface $dateEcriture): static
    {
        $this->dateEcriture = $dateEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLibelleEcriture(): string
    {
        return $this->libelleEcriture;
    }

    public function setLibelleEcriture(string $libelleEcriture): static
    {
        $this->libelleEcriture = $libelleEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getNumeroPiece(): string
    {
        return $this->numeroPiece;
    }

    public function setNumeroPiece(string $numeroPiece): static
    {
        $this->numeroPiece = $numeroPiece;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDatePiece(): \DateTimeInterface
    {
        return $this->datePiece;
    }

    public function setDatePiece(\DateTimeInterface $datePiece): static
    {
        $this->datePiece = $datePiece;
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

    public function getValidePar(): ?User
    {
        return $this->validePar;
    }

    public function setValidePar(?User $validePar): static
    {
        $this->validePar = $validePar;

        return $this;
    }

    public function isIsValidee(): bool
    {
        return $this->isValidee;
    }

    public function setIsValidee(bool $isValidee): static
    {
        $this->isValidee = $isValidee;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function isIsEquilibree(): bool
    {
        return $this->isEquilibree;
    }

    public function setIsEquilibree(bool $isEquilibree): static
    {
        $this->isEquilibree = $isEquilibree;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLettrage(): ?string
    {
        return $this->lettrage;
    }

    public function setLettrage(?string $lettrage): static
    {
        $this->lettrage = $lettrage;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateLettrage(): ?\DateTimeInterface
    {
        return $this->dateLettrage;
    }

    public function setDateLettrage(?\DateTimeInterface $dateLettrage): static
    {
        $this->dateLettrage = $dateLettrage;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(?string $documentType): static
    {
        $this->documentType = $documentType;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDocumentId(): ?int
    {
        return $this->documentId;
    }

    public function setDocumentId(?int $documentId): static
    {
        $this->documentId = $documentId;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getExerciceComptable(): ExerciceComptable
    {
        return $this->exerciceComptable;
    }

    public function setExerciceComptable(ExerciceComptable $exerciceComptable): static
    {
        $this->exerciceComptable = $exerciceComptable;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getIntegrite(): ?DocumentIntegrity
    {
        return $this->integrite;
    }

    public function setIntegrite(?DocumentIntegrity $integrite): static
    {
        $this->integrite = $integrite;

        return $this;
    }

    /**
     * @return Collection<int, LigneEcriture>
     */
    public function getLignesEcriture(): Collection
    {
        return $this->lignesEcriture;
    }

    public function addLignesEcriture(LigneEcriture $lignesEcriture): static
    {
        if (!$this->lignesEcriture->contains($lignesEcriture)) {
            $this->lignesEcriture->add($lignesEcriture);
            $lignesEcriture->setEcriture($this);
        }

        return $this;
    }

    public function removeLignesEcriture(LigneEcriture $lignesEcriture): static
    {
        if ($this->lignesEcriture->removeElement($lignesEcriture)) {
            // set the owning side to null (unless already changed)
            if ($lignesEcriture->getEcriture() === $this) {
                $lignesEcriture->setEcriture(null);
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

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    /**
     * Calcule et met à jour les totaux de l'écriture
     */
    public function calculateTotaux(): static
    {
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($this->lignesEcriture as $ligne) {
            $totalDebit = bcadd($totalDebit, $ligne->getMontantDebit(), 2);
            $totalCredit = bcadd($totalCredit, $ligne->getMontantCredit(), 2);
        }

        $this->totalDebit = $totalDebit;
        $this->totalCredit = $totalCredit;
        $this->isEquilibree = bccomp($totalDebit, $totalCredit, 2) === 0;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Vérifie si l'écriture est équilibrée
     */
    public function checkEquilibre(): bool
    {
        $this->calculateTotaux();
        return $this->isEquilibree;
    }

    /**
     * Valide l'écriture comptable
     */
    public function valider(User $user): static
    {
        if ($this->checkEquilibre()) {
            $this->isValidee = true;
            $this->dateValidation = new \DateTime();
            $this->validePar = $user;
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    /**
     * Annule la validation de l'écriture
     */
    public function annulerValidation(): static
    {
        $this->isValidee = false;
        $this->dateValidation = null;
        $this->validePar = null;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Génère automatiquement le libellé de l'écriture basé sur le document lié
     */
    public function generateLibelleFromDocument(): string
    {
        if ($this->documentType && $this->documentId) {
            switch ($this->documentType) {
                case 'facture':
                    return "Facture n° {$this->numeroPiece}";
                case 'avoir':
                    return "Avoir n° {$this->numeroPiece}";
                case 'paiement':
                    return "Paiement n° {$this->numeroPiece}";
                default:
                    return "Document {$this->documentType} n° {$this->numeroPiece}";
            }
        }

        return $this->libelleEcriture;
    }

    /**
     * Retourne le code journal de l'écriture
     */
    public function getJournalCode(): string
    {
        return $this->journal->getCode();
    }

    /**
     * Retourne le libellé du journal
     */
    public function getJournalLibelle(): string
    {
        return $this->journal->getLibelle();
    }

    /**
     * Vérifie si l'écriture peut être modifiée
     */
    public function canBeModified(): bool
    {
        return !$this->isValidee && $this->exerciceComptable->isOuvert();
    }

    /**
     * Vérifie si l'écriture peut être supprimée
     */
    public function canBeDeleted(): bool
    {
        return !$this->isValidee && $this->exerciceComptable->isOuvert();
    }

    public function __toString(): string
    {
        return $this->numeroEcriture . ' - ' . $this->libelleEcriture;
    }
}