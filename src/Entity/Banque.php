<?php

namespace App\Entity;

use App\Repository\BanqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BanqueRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Banque
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    // Partie Adresse
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $pays = null;

    // Partie Contact
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $fax = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWeb = null;

    // Comptabilité
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codeJournal = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $compteComptable = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codeJournalRemise = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $comptePaiementsEncaisser = null;

    // Coordonnées bancaires
    #[ORM\Column(length: 23, nullable: true)]
    private ?string $ribBban = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 11, nullable: true)]
    private ?string $bic = null;

    // CFONB
    #[ORM\Column(length: 6, nullable: true)]
    private ?string $numeroNationalEmetteur = null;

    // SEPA
    #[ORM\Column(length: 35, nullable: true)]
    private ?string $identifiantCreancierSepa = null;

    // Notes
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    // Système
    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations
    #[ORM\OneToMany(targetEntity: FraisBancaire::class, mappedBy: 'banque', cascade: ['persist', 'remove'])]
    private Collection $fraisBancaires;

    #[ORM\OneToMany(targetEntity: ModePaiement::class, mappedBy: 'banqueParDefaut')]
    private Collection $modesPaiement;

    public function __construct()
    {
        $this->fraisBancaires = new ArrayCollection();
        $this->modesPaiement = new ArrayCollection();
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): static
    {
        $this->fax = $fax;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): static
    {
        $this->siteWeb = $siteWeb;
        return $this;
    }

    public function getCodeJournal(): ?string
    {
        return $this->codeJournal;
    }

    public function setCodeJournal(?string $codeJournal): static
    {
        $this->codeJournal = $codeJournal;
        return $this;
    }

    public function getCompteComptable(): ?string
    {
        return $this->compteComptable;
    }

    public function setCompteComptable(?string $compteComptable): static
    {
        $this->compteComptable = $compteComptable;
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

    public function getComptePaiementsEncaisser(): ?string
    {
        return $this->comptePaiementsEncaisser;
    }

    public function setComptePaiementsEncaisser(?string $comptePaiementsEncaisser): static
    {
        $this->comptePaiementsEncaisser = $comptePaiementsEncaisser;
        return $this;
    }

    public function getRibBban(): ?string
    {
        return $this->ribBban;
    }

    public function setRibBban(?string $ribBban): static
    {
        $this->ribBban = $ribBban;
        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;
        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = $bic;
        return $this;
    }

    public function getNumeroNationalEmetteur(): ?string
    {
        return $this->numeroNationalEmetteur;
    }

    public function setNumeroNationalEmetteur(?string $numeroNationalEmetteur): static
    {
        $this->numeroNationalEmetteur = $numeroNationalEmetteur;
        return $this;
    }

    public function getIdentifiantCreancierSepa(): ?string
    {
        return $this->identifiantCreancierSepa;
    }

    public function setIdentifiantCreancierSepa(?string $identifiantCreancierSepa): static
    {
        $this->identifiantCreancierSepa = $identifiantCreancierSepa;
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
     * @return Collection<int, FraisBancaire>
     */
    public function getFraisBancaires(): Collection
    {
        return $this->fraisBancaires;
    }

    public function addFraisBancaire(FraisBancaire $fraisBancaire): static
    {
        if (!$this->fraisBancaires->contains($fraisBancaire)) {
            $this->fraisBancaires->add($fraisBancaire);
            $fraisBancaire->setBanque($this);
        }

        return $this;
    }

    public function removeFraisBancaire(FraisBancaire $fraisBancaire): static
    {
        if ($this->fraisBancaires->removeElement($fraisBancaire)) {
            if ($fraisBancaire->getBanque() === $this) {
                $fraisBancaire->setBanque(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ModePaiement>
     */
    public function getModesPaiement(): Collection
    {
        return $this->modesPaiement;
    }

    public function addModePaiement(ModePaiement $modePaiement): static
    {
        if (!$this->modesPaiement->contains($modePaiement)) {
            $this->modesPaiement->add($modePaiement);
            $modePaiement->setBanqueParDefaut($this);
        }

        return $this;
    }

    public function removeModePaiement(ModePaiement $modePaiement): static
    {
        if ($this->modesPaiement->removeElement($modePaiement)) {
            if ($modePaiement->getBanqueParDefaut() === $this) {
                $modePaiement->setBanqueParDefaut(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
