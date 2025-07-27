<?php

namespace App\Entity;

use App\Repository\ComptePCGRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ComptePCGRepository::class)]
#[ORM\Table(name: 'compte_pcg')]
class ComptePCG
{
    #[ORM\Id]
    #[ORM\Column(length: 10)]
    private string $numeroCompte; // 411000, 701000, etc.

    #[ORM\Column(length: 255)]
    private string $libelle; // "Clients", "Ventes de produits finis"

    #[ORM\Column(length: 1)]
    private string $classe; // 1-8 (classes du PCG)

    #[ORM\Column(length: 20)]
    private string $nature; // ACTIF, PASSIF, CHARGE, PRODUIT

    #[ORM\Column(length: 20)]
    private string $type; // GENERAL, TIERS, ANALYTIQUE

    #[ORM\Column]
    private bool $isActif = true;

    #[ORM\Column]
    private bool $isPourLettrage = false; // Comptes clients/fournisseurs

    #[ORM\Column]
    private bool $isPourAnalytique = false;

    // Comptes de regroupement
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sousComptes')]
    #[ORM\JoinColumn(name: 'compte_parent_numero', referencedColumnName: 'numero_compte')]
    private ?ComptePCG $compteParent = null;

    #[ORM\OneToMany(mappedBy: 'compteParent', targetEntity: self::class)]
    private Collection $sousComptes;

    // Soldes
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $soldeDebiteur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $soldeCrediteur = '0.00';

    // Métadonnées
    #[ORM\Column(type: Types::JSON)]
    private array $parametresComptables = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->sousComptes = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getNumeroCompte(): string
    {
        return $this->numeroCompte;
    }

    /**
     * Méthode getId() pour compatibilité avec AuditService
     * Retourne le numéro de compte qui sert d'identifiant
     */
    public function getId(): string
    {
        return $this->numeroCompte;
    }

    public function setNumeroCompte(string $numeroCompte): static
    {
        $this->numeroCompte = $numeroCompte;
        // Auto-détection de la classe basée sur le premier chiffre
        $this->classe = substr($numeroCompte, 0, 1);
        
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

    public function getClasse(): string
    {
        return $this->classe;
    }

    public function setClasse(string $classe): static
    {
        $this->classe = $classe;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getNature(): string
    {
        return $this->nature;
    }

    public function setNature(string $nature): static
    {
        $this->nature = $nature;
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

    public function isIsPourLettrage(): bool
    {
        return $this->isPourLettrage;
    }

    public function setIsPourLettrage(bool $isPourLettrage): static
    {
        $this->isPourLettrage = $isPourLettrage;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function isIsPourAnalytique(): bool
    {
        return $this->isPourAnalytique;
    }

    public function setIsPourAnalytique(bool $isPourAnalytique): static
    {
        $this->isPourAnalytique = $isPourAnalytique;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCompteParent(): ?self
    {
        return $this->compteParent;
    }

    public function setCompteParent(?self $compteParent): static
    {
        $this->compteParent = $compteParent;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * @return Collection<int, ComptePCG>
     */
    public function getSousComptes(): Collection
    {
        return $this->sousComptes;
    }

    public function addSousCompte(ComptePCG $sousCompte): static
    {
        if (!$this->sousComptes->contains($sousCompte)) {
            $this->sousComptes->add($sousCompte);
            $sousCompte->setCompteParent($this);
        }

        return $this;
    }

    public function removeSousCompte(ComptePCG $sousCompte): static
    {
        if ($this->sousComptes->removeElement($sousCompte)) {
            // set the owning side to null (unless already changed)
            if ($sousCompte->getCompteParent() === $this) {
                $sousCompte->setCompteParent(null);
            }
        }

        return $this;
    }

    public function getSoldeDebiteur(): string
    {
        return $this->soldeDebiteur;
    }

    public function setSoldeDebiteur(string $soldeDebiteur): static
    {
        $this->soldeDebiteur = $soldeDebiteur;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getSoldeCrediteur(): string
    {
        return $this->soldeCrediteur;
    }

    public function setSoldeCrediteur(string $soldeCrediteur): static
    {
        $this->soldeCrediteur = $soldeCrediteur;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getParametresComptables(): array
    {
        return $this->parametresComptables;
    }

    public function setParametresComptables(array $parametresComptables): static
    {
        $this->parametresComptables = $parametresComptables;
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

    /**
     * Calcule le solde du compte (débiteur - créditeur)
     */
    public function getSolde(): string
    {
        return bcadd($this->soldeDebiteur, '-' . $this->soldeCrediteur, 2);
    }

    /**
     * Détermine si le compte est débiteur ou créditeur selon sa nature
     */
    public function isCompteDebiteurParNature(): bool
    {
        return in_array($this->nature, ['ACTIF', 'CHARGE']);
    }

    /**
     * Retourne le libellé complet avec numéro de compte
     */
    public function getLibelleComplet(): string
    {
        return $this->numeroCompte . ' - ' . $this->libelle;
    }

    /**
     * Vérifie si le compte peut être utilisé pour du lettrage
     */
    public function canBeLettred(): bool
    {
        return $this->isPourLettrage && $this->isActif;
    }

    public function __toString(): string
    {
        return $this->getLibelleComplet();
    }
}