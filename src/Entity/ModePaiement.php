<?php

namespace App\Entity;

use App\Repository\ModePaiementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModePaiementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ModePaiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nature = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private bool $modePaiementParDefaut = false;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: Banque::class, inversedBy: 'modesPaiement')]
    private ?Banque $banqueParDefaut = null;

    #[ORM\Column]
    private bool $remettreEnBanque = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codeJournalRemise = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $compteRemise = null;

    #[ORM\OneToMany(targetEntity: ModeReglement::class, mappedBy: 'modePaiement')]
    private Collection $modesReglement;

    public function __construct()
    {
        $this->modesReglement = new ArrayCollection();
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

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(?string $nature): static
    {
        $this->nature = $nature;
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

    public function isModePaiementParDefaut(): bool
    {
        return $this->modePaiementParDefaut;
    }

    public function setModePaiementParDefaut(bool $modePaiementParDefaut): static
    {
        $this->modePaiementParDefaut = $modePaiementParDefaut;
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getBanqueParDefaut(): ?Banque
    {
        return $this->banqueParDefaut;
    }

    public function setBanqueParDefaut(?Banque $banqueParDefaut): static
    {
        $this->banqueParDefaut = $banqueParDefaut;
        return $this;
    }

    public function isRemettreEnBanque(): bool
    {
        return $this->remettreEnBanque;
    }

    public function setRemettreEnBanque(bool $remettreEnBanque): static
    {
        $this->remettreEnBanque = $remettreEnBanque;
        return $this;
    }

    public function getCodeJournalRemise(): ?string
    {
        return $this->codeJournalRemise;
    }

    public function setCodeJournalRemise(?string $codeJournalRemise): static
    {
        $this->codeJournalRemise = $codeJournalRemise;
        return $this;
    }

    public function getCompteRemise(): ?string
    {
        return $this->compteRemise;
    }

    public function setCompteRemise(?string $compteRemise): static
    {
        $this->compteRemise = $compteRemise;
        return $this;
    }

    /**
     * @return Collection<int, ModeReglement>
     */
    public function getModesReglement(): Collection
    {
        return $this->modesReglement;
    }

    public function addModeReglement(ModeReglement $modeReglement): static
    {
        if (!$this->modesReglement->contains($modeReglement)) {
            $this->modesReglement->add($modeReglement);
            $modeReglement->setModePaiement($this);
        }

        return $this;
    }

    public function removeModeReglement(ModeReglement $modeReglement): static
    {
        if ($this->modesReglement->removeElement($modeReglement)) {
            if ($modeReglement->getModePaiement() === $this) {
                $modeReglement->setModePaiement(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}