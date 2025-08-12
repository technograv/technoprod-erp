<?php

namespace App\Entity;

use App\Repository\LigneEcritureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneEcritureRepository::class)]
#[ORM\Table(name: 'ligne_ecriture')]
class LigneEcriture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // RELATION AVEC ÉCRITURE
    #[ORM\ManyToOne(targetEntity: EcritureComptable::class, inversedBy: 'lignesEcriture')]
    #[ORM\JoinColumn(nullable: false)]
    private EcritureComptable $ecriture;

    // COMPTE ET MONTANTS
    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(name: 'compte_pcg_numero', referencedColumnName: 'numero_compte', nullable: false)]
    private ComptePCG $comptePCG;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $montantDebit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $montantCredit = '0.00';

    // LIBELLÉ SPÉCIFIQUE À LA LIGNE
    #[ORM\Column(length: 255)]
    private string $libelleLigne;

    // TIERS (si applicable)
    #[ORM\Column(length: 17, nullable: true)]
    private ?string $compteAuxiliaire = null; // Code client/fournisseur

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $compteAuxiliaireLibelle = null;

    // ÉCHÉANCE (pour les comptes de tiers)
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEcheance = null;

    // LETTRAGE
    #[ORM\Column(length: 3, nullable: true)]
    private ?string $lettrage = null; // Pour rapprochement

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLettrage = null;

    // DEVISES (si multi-devises)
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $montantDevise = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $codeDevise = null; // EUR, USD, etc.

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    private ?string $tauxChange = null;

    // ANALYTIQUE (optionnel)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codeAnalytique = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $pourcentageAnalytique = null;

    // QUANTITÉ (optionnel pour certains comptes)
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 3, nullable: true)]
    private ?string $quantite = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $unite = null;

    // MÉTADONNÉES
    #[ORM\Column(type: Types::JSON)]
    private array $metadonnees = [];

    #[ORM\Column]
    private int $ordre = 1; // Ordre dans l'écriture

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEcriture(): EcritureComptable
    {
        return $this->ecriture;
    }

    public function setEcriture(EcritureComptable $ecriture): static
    {
        $this->ecriture = $ecriture;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getComptePCG(): ComptePCG
    {
        return $this->comptePCG;
    }

    public function setComptePCG(ComptePCG $comptePCG): static
    {
        $this->comptePCG = $comptePCG;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getMontantDebit(): string
    {
        return $this->montantDebit;
    }

    public function setMontantDebit(string $montantDebit): static
    {
        $this->montantDebit = $montantDebit;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getMontantCredit(): string
    {
        return $this->montantCredit;
    }

    public function setMontantCredit(string $montantCredit): static
    {
        $this->montantCredit = $montantCredit;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getLibelleLigne(): string
    {
        return $this->libelleLigne;
    }

    public function setLibelleLigne(string $libelleLigne): static
    {
        $this->libelleLigne = $libelleLigne;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCompteAuxiliaire(): ?string
    {
        return $this->compteAuxiliaire;
    }

    public function setCompteAuxiliaire(?string $compteAuxiliaire): static
    {
        $this->compteAuxiliaire = $compteAuxiliaire;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCompteAuxiliaireLibelle(): ?string
    {
        return $this->compteAuxiliaireLibelle;
    }

    public function setCompteAuxiliaireLibelle(?string $compteAuxiliaireLibelle): static
    {
        $this->compteAuxiliaireLibelle = $compteAuxiliaireLibelle;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeInterface $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;
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

    public function getMontantDevise(): ?string
    {
        return $this->montantDevise;
    }

    public function setMontantDevise(?string $montantDevise): static
    {
        $this->montantDevise = $montantDevise;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCodeDevise(): ?string
    {
        return $this->codeDevise;
    }

    public function setCodeDevise(?string $codeDevise): static
    {
        $this->codeDevise = $codeDevise;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getTauxChange(): ?string
    {
        return $this->tauxChange;
    }

    public function setTauxChange(?string $tauxChange): static
    {
        $this->tauxChange = $tauxChange;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getCodeAnalytique(): ?string
    {
        return $this->codeAnalytique;
    }

    public function setCodeAnalytique(?string $codeAnalytique): static
    {
        $this->codeAnalytique = $codeAnalytique;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getPourcentageAnalytique(): ?string
    {
        return $this->pourcentageAnalytique;
    }

    public function setPourcentageAnalytique(?string $pourcentageAnalytique): static
    {
        $this->pourcentageAnalytique = $pourcentageAnalytique;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(?string $quantite): static
    {
        $this->quantite = $quantite;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;
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

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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
     * Retourne le montant net de la ligne (débit - crédit)
     */
    public function getMontantNet(): string
    {
        return bcsub($this->montantDebit, $this->montantCredit, 2);
    }

    /**
     * Vérifie si la ligne est au débit
     */
    public function isDebit(): bool
    {
        return bccomp($this->montantDebit, '0.00', 2) > 0;
    }

    /**
     * Vérifie si la ligne est au crédit
     */
    public function isCredit(): bool
    {
        return bccomp($this->montantCredit, '0.00', 2) > 0;
    }

    /**
     * Retourne le montant principal (débit ou crédit non nul)
     */
    public function getMontantPrincipal(): string
    {
        return $this->isDebit() ? $this->montantDebit : $this->montantCredit;
    }

    /**
     * Définit un montant au débit (remet le crédit à 0)
     */
    public function setMontantAuDebit(string $montant): static
    {
        $this->montantDebit = $montant;
        $this->montantCredit = '0.00';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Définit un montant au crédit (remet le débit à 0)
     */
    public function setMontantAuCredit(string $montant): static
    {
        $this->montantCredit = $montant;
        $this->montantDebit = '0.00';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Vérifie si la ligne concerne un compte de tiers
     */
    public function isTiers(): bool
    {
        return !empty($this->compteAuxiliaire);
    }

    /**
     * Vérifie si la ligne est lettrée
     */
    public function isLettree(): bool
    {
        return !empty($this->lettrage) && $this->dateLettrage !== null;
    }

    /**
     * Lettre la ligne d'écriture
     */
    public function lettrer(string $lettrage): static
    {
        $this->lettrage = $lettrage;
        $this->dateLettrage = new \DateTime();
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Délettre la ligne d'écriture
     */
    public function delettrer(): static
    {
        $this->lettrage = null;
        $this->dateLettrage = null;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Retourne le numéro du compte comptable
     */
    public function getNumeroCompte(): string
    {
        return $this->comptePCG->getNumeroCompte();
    }

    /**
     * Retourne le libellé du compte comptable
     */
    public function getLibelleCompte(): string
    {
        return $this->comptePCG->getLibelle();
    }

    /**
     * Vérifie si la ligne est en devise étrangère
     */
    public function isDeviseEtrangere(): bool
    {
        return !empty($this->codeDevise) && $this->codeDevise !== 'EUR';
    }

    /**
     * Calcule le montant en devise de base à partir du montant devise
     */
    public function calculateMontantDeBase(): ?string
    {
        if ($this->montantDevise && $this->tauxChange) {
            return bcmul($this->montantDevise, $this->tauxChange, 2);
        }

        return null;
    }

    /**
     * Formate la ligne pour export FEC
     */
    public function toFECFormat(): array
    {
        return [
            'compte_num' => $this->getNumeroCompte(),
            'compte_lib' => $this->getLibelleCompte(),
            'comp_aux_num' => $this->compteAuxiliaire ?? '',
            'comp_aux_lib' => $this->compteAuxiliaireLibelle ?? '',
            'debit' => $this->montantDebit,
            'credit' => $this->montantCredit,
            'ecriture_let' => $this->lettrage ?? '',
            'date_let' => $this->dateLettrage?->format('Ymd') ?? '',
            'montant_devise' => $this->montantDevise ?? '0',
            'code_devise' => $this->codeDevise ?? 'EUR'
        ];
    }

    public function __toString(): string
    {
        $sens = $this->isDebit() ? 'D' : 'C';
        $montant = $this->getMontantPrincipal();
        return "{$this->getNumeroCompte()} - {$this->libelleLigne} ({$sens}: {$montant})";
    }
}