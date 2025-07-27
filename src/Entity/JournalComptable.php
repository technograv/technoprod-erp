<?php

namespace App\Entity;

use App\Repository\JournalComptableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalComptableRepository::class)]
#[ORM\Table(name: 'journal_comptable')]
class JournalComptable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3, unique: true)]
    private string $code; // VTE, ACH, BAN, OD

    #[ORM\Column(length: 100)]
    private string $libelle; // "Journal des ventes", "Journal des achats"

    #[ORM\Column(length: 50)]
    private string $type; // VENTE, ACHAT, BANQUE, CAISSE, OD_GENERALES

    #[ORM\Column]
    private bool $isActif = true;

    #[ORM\Column]
    private bool $isObligatoire = false; // Journaux obligatoires (VTE, ACH, etc.)

    #[ORM\Column]
    private bool $isControleNumeroEcriture = true; // Contrôle séquence numérotation

    #[ORM\Column]
    private int $dernierNumeroEcriture = 0; // Dernier numéro d'écriture utilisé

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $formatNumeroEcriture = null; // Format: VTE{YYYY}{0000}

    // Comptes de contrepartie par défaut
    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(name: 'compte_contrepartie_defaut', referencedColumnName: 'numero_compte', nullable: true)]
    private ?ComptePCG $compteContrepartieDefaut = null;

    // Paramètres du journal
    #[ORM\Column(type: Types::JSON)]
    private array $parametres = [];

    // Relations
    #[ORM\OneToMany(mappedBy: 'journal', targetEntity: EcritureComptable::class)]
    private Collection $ecritures;

    // Métadonnées
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
        $this->ecritures = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function isIsActif(): bool
    {
        return $this->isActif;
    }

    public function setIsActif(bool $isActif): static
    {
        $this->isActif = $isActif;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function isIsObligatoire(): bool
    {
        return $this->isObligatoire;
    }

    public function setIsObligatoire(bool $isObligatoire): static
    {
        $this->isObligatoire = $isObligatoire;

        return $this;
    }

    public function isIsControleNumeroEcriture(): bool
    {
        return $this->isControleNumeroEcriture;
    }

    public function setIsControleNumeroEcriture(bool $isControleNumeroEcriture): static
    {
        $this->isControleNumeroEcriture = $isControleNumeroEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDernierNumeroEcriture(): int
    {
        return $this->dernierNumeroEcriture;
    }

    public function setDernierNumeroEcriture(int $dernierNumeroEcriture): static
    {
        $this->dernierNumeroEcriture = $dernierNumeroEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getFormatNumeroEcriture(): ?string
    {
        return $this->formatNumeroEcriture;
    }

    public function setFormatNumeroEcriture(?string $formatNumeroEcriture): static
    {
        $this->formatNumeroEcriture = $formatNumeroEcriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCompteContrepartieDefaut(): ?ComptePCG
    {
        return $this->compteContrepartieDefaut;
    }

    public function setCompteContrepartieDefaut(?ComptePCG $compteContrepartieDefaut): static
    {
        $this->compteContrepartieDefaut = $compteContrepartieDefaut;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getParametres(): array
    {
        return $this->parametres;
    }

    public function setParametres(array $parametres): static
    {
        $this->parametres = $parametres;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * @return Collection<int, EcritureComptable>
     */
    public function getEcritures(): Collection
    {
        return $this->ecritures;
    }

    public function addEcriture(EcritureComptable $ecriture): static
    {
        if (!$this->ecritures->contains($ecriture)) {
            $this->ecritures->add($ecriture);
            $ecriture->setJournal($this);
        }

        return $this;
    }

    public function removeEcriture(EcritureComptable $ecriture): static
    {
        if ($this->ecritures->removeElement($ecriture)) {
            // set the owning side to null (unless already changed)
            if ($ecriture->getJournal() === $this) {
                $ecriture->setJournal(null);
            }
        }

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
     * Génère le prochain numéro d'écriture pour ce journal
     */
    public function generateNextNumeroEcriture(): string
    {
        $this->dernierNumeroEcriture++;
        
        if ($this->formatNumeroEcriture) {
            // Remplace les variables dans le format
            $numero = str_replace(
                ['{YYYY}', '{CODE}'],
                [date('Y'), $this->code],
                $this->formatNumeroEcriture
            );
            
            // Remplace les séquences de 0 par le numéro paddé
            $numero = preg_replace_callback('/\{(0+)\}/', function($matches) {
                $length = strlen($matches[1]);
                return str_pad($this->dernierNumeroEcriture, $length, '0', STR_PAD_LEFT);
            }, $numero);
            
            return $numero;
        }
        
        // Format par défaut : CODE + ANNÉE + NUMÉRO (ex: VTE20250001)
        return $this->code . date('Y') . str_pad($this->dernierNumeroEcriture, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Retourne les statistiques du journal
     */
    public function getStatistiques(): array
    {
        $totalEcritures = $this->ecritures->count();
        $totalDebit = '0.00';
        $totalCredit = '0.00';
        
        foreach ($this->ecritures as $ecriture) {
            foreach ($ecriture->getLignesEcriture() as $ligne) {
                $totalDebit = bcadd($totalDebit, $ligne->getMontantDebit(), 2);
                $totalCredit = bcadd($totalCredit, $ligne->getMontantCredit(), 2);
            }
        }
        
        return [
            'total_ecritures' => $totalEcritures,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'equilibre' => bccomp($totalDebit, $totalCredit, 2) === 0
        ];
    }

    public function __toString(): string
    {
        return $this->code . ' - ' . $this->libelle;
    }
}