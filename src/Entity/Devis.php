<?php

namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisRepository::class)]
class Devis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroDevis = null;

    #[ORM\ManyToOne(inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;


    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contactFacturation = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contactLivraison = null;

    #[ORM\ManyToOne(targetEntity: Adresse::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Adresse $adresseFacturation = null;

    #[ORM\ManyToOne(targetEntity: Adresse::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Adresse $adresseLivraison = null;

    #[ORM\ManyToOne(inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $commercial = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateValidite = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = 'brouillon';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTtc = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $remiseGlobalePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $remiseGlobaleMontant = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesInternes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesClient = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $acomptePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $acompteMontant = null;


    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateSignature = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signatureNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signatureEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signatureData = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datePaiementAcompte = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $urlAccesClient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $delaiLivraison = null;

    // Champs Tiers éditables pour ce devis spécifiquement
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $tiersCivilite = null;
    
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $tiersNom = null;
    
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tiersPrenom = null;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tiersAdresse = null;
    
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $tiersCodePostal = null;
    
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tiersVille = null;
    
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tiersModeReglement = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'devis', targetEntity: DevisItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordreAffichage' => 'ASC'])]
    private Collection $devisItems;

    public function __construct()
    {
        $this->devisItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->dateCreation = new \DateTime();
        $this->dateValidite = new \DateTime('+30 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroDevis(): ?string
    {
        return $this->numeroDevis;
    }

    public function setNumeroDevis(string $numeroDevis): static
    {
        $this->numeroDevis = $numeroDevis;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }


    public function getContactFacturation(): ?Contact
    {
        return $this->contactFacturation;
    }

    public function setContactFacturation(?Contact $contactFacturation): static
    {
        $this->contactFacturation = $contactFacturation;
        return $this;
    }

    public function getContactLivraison(): ?Contact
    {
        return $this->contactLivraison;
    }

    public function setContactLivraison(?Contact $contactLivraison): static
    {
        $this->contactLivraison = $contactLivraison;
        return $this;
    }

    public function getAdresseFacturation(): ?Adresse
    {
        return $this->adresseFacturation;
    }

    public function setAdresseFacturation(?Adresse $adresseFacturation): static
    {
        $this->adresseFacturation = $adresseFacturation;
        return $this;
    }

    public function getAdresseLivraison(): ?Adresse
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?Adresse $adresseLivraison): static
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getCommercial(): ?User
    {
        return $this->commercial;
    }

    public function setCommercial(?User $commercial): static
    {
        $this->commercial = $commercial;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateValidite(): ?\DateTimeInterface
    {
        return $this->dateValidite;
    }

    public function setDateValidite(\DateTimeInterface $dateValidite): static
    {
        $this->dateValidite = $dateValidite;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $oldStatut = $this->statut;
        $this->statut = $statut;
        
        // Si passage en statut "envoye", enregistrer la date d'envoi
        if ($oldStatut !== 'envoye' && $statut === 'envoye') {
            $this->dateEnvoi = new \DateTime();
        }
        
        return $this;
    }

    public function getTotalHt(): ?string
    {
        return $this->totalHt;
    }

    public function setTotalHt(string $totalHt): static
    {
        $this->totalHt = $totalHt;
        return $this;
    }

    public function getTotalTva(): ?string
    {
        return $this->totalTva;
    }

    public function setTotalTva(string $totalTva): static
    {
        $this->totalTva = $totalTva;
        return $this;
    }

    public function getTotalTtc(): ?string
    {
        return $this->totalTtc;
    }

    public function setTotalTtc(string $totalTtc): static
    {
        $this->totalTtc = $totalTtc;
        return $this;
    }

    public function getRemiseGlobalePercent(): ?string
    {
        return $this->remiseGlobalePercent;
    }

    public function setRemiseGlobalePercent(?string $remiseGlobalePercent): static
    {
        $this->remiseGlobalePercent = $remiseGlobalePercent;
        return $this;
    }

    public function getRemiseGlobaleMontant(): ?string
    {
        return $this->remiseGlobaleMontant;
    }

    public function setRemiseGlobaleMontant(?string $remiseGlobaleMontant): static
    {
        $this->remiseGlobaleMontant = $remiseGlobaleMontant;
        return $this;
    }

    public function getNotesInternes(): ?string
    {
        return $this->notesInternes;
    }

    public function setNotesInternes(?string $notesInternes): static
    {
        $this->notesInternes = $notesInternes;
        return $this;
    }

    public function getNotesClient(): ?string
    {
        return $this->notesClient;
    }

    public function setNotesClient(?string $notesClient): static
    {
        $this->notesClient = $notesClient;
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
     * @return Collection<int, DevisItem>
     */
    public function getDevisItems(): Collection
    {
        return $this->devisItems;
    }

    public function addDevisItem(DevisItem $devisItem): static
    {
        if (!$this->devisItems->contains($devisItem)) {
            $this->devisItems->add($devisItem);
            $devisItem->setDevis($this);
        }
        return $this;
    }

    public function removeDevisItem(DevisItem $devisItem): static
    {
        if ($this->devisItems->removeElement($devisItem)) {
            if ($devisItem->getDevis() === $this) {
                $devisItem->setDevis(null);
            }
        }
        return $this;
    }

    public function calculateTotals(): void
    {
        $totalHt = 0;
        $totalTva = 0;

        foreach ($this->devisItems as $item) {
            $totalHt += floatval($item->getTotalLigneHt());
            $tvaLigne = floatval($item->getTotalLigneHt()) * floatval($item->getTvaPercent()) / 100;
            $totalTva += $tvaLigne;
        }

        // Appliquer la remise globale si elle existe
        if ($this->remiseGlobalePercent) {
            $totalHt = $totalHt * (1 - floatval($this->remiseGlobalePercent) / 100);
            $totalTva = $totalTva * (1 - floatval($this->remiseGlobalePercent) / 100);
        }

        if ($this->remiseGlobaleMontant) {
            $totalHt -= floatval($this->remiseGlobaleMontant);
        }

        $this->totalHt = number_format($totalHt, 2, '.', '');
        $this->totalTva = number_format($totalTva, 2, '.', '');
        $this->totalTtc = number_format($totalHt + $totalTva, 2, '.', '');
    }

    public function generateNumeroDevis(): string
    {
        $year = date('Y');
        return $year . '-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    // Getters/Setters pour les nouvelles fonctionnalités

    public function getAcomptePercent(): ?string
    {
        return $this->acomptePercent;
    }

    public function setAcomptePercent(?string $acomptePercent): static
    {
        $this->acomptePercent = $acomptePercent;
        return $this;
    }

    public function getAcompteMontant(): ?string
    {
        return $this->acompteMontant;
    }

    public function setAcompteMontant(?string $acompteMontant): static
    {
        $this->acompteMontant = $acompteMontant;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getDateSignature(): ?\DateTimeInterface
    {
        return $this->dateSignature;
    }

    public function setDateSignature(?\DateTimeInterface $dateSignature): static
    {
        $this->dateSignature = $dateSignature;
        return $this;
    }

    public function getSignatureNom(): ?string
    {
        return $this->signatureNom;
    }

    public function setSignatureNom(?string $signatureNom): static
    {
        $this->signatureNom = $signatureNom;
        return $this;
    }

    public function getSignatureEmail(): ?string
    {
        return $this->signatureEmail;
    }

    public function setSignatureEmail(?string $signatureEmail): static
    {
        $this->signatureEmail = $signatureEmail;
        return $this;
    }

    public function getSignatureData(): ?string
    {
        return $this->signatureData;
    }

    public function setSignatureData(?string $signatureData): static
    {
        $this->signatureData = $signatureData;
        return $this;
    }

    public function getDatePaiementAcompte(): ?\DateTimeInterface
    {
        return $this->datePaiementAcompte;
    }

    public function setDatePaiementAcompte(?\DateTimeInterface $datePaiementAcompte): static
    {
        $this->datePaiementAcompte = $datePaiementAcompte;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getUrlAccesClient(): ?string
    {
        return $this->urlAccesClient;
    }

    public function setUrlAccesClient(?string $urlAccesClient): static
    {
        $this->urlAccesClient = $urlAccesClient;
        return $this;
    }

    public function getDelaiLivraison(): ?string
    {
        return $this->delaiLivraison;
    }

    public function setDelaiLivraison(?string $delaiLivraison): static
    {
        $this->delaiLivraison = $delaiLivraison;
        return $this;
    }

    // Méthodes utilitaires

    public function isSigne(): bool
    {
        return $this->dateSignature !== null;
    }

    public function isEnvoye(): bool
    {
        return $this->dateEnvoi !== null;
    }

    public function isAcompteRegle(): bool
    {
        return $this->datePaiementAcompte !== null;
    }

    public function getStatutLibelle(): string
    {
        return match($this->statut) {
            'brouillon' => 'Brouillon',
            'envoye' => 'Envoyé',
            'relance' => 'Relancé',
            'signe' => 'Signé',
            'acompte_regle' => 'Acompte réglé',
            'accepte' => 'Accepté',
            'refuse' => 'Refusé',
            'expire' => 'Expiré',
            default => 'Inconnu'
        };
    }

    public function getStatutCouleur(): string
    {
        return match($this->statut) {
            'brouillon' => 'secondary',
            'envoye' => 'info',
            'relance' => 'warning',
            'signe' => 'primary',
            'acompte_regle' => 'success',
            'accepte' => 'success',
            'refuse' => 'danger',
            'expire' => 'dark',
            default => 'secondary'
        };
    }

    public function calculateAcompte(): ?string
    {
        if (!$this->acomptePercent && !$this->acompteMontant) {
            return null;
        }

        if ($this->acompteMontant) {
            return $this->acompteMontant;
        }

        if ($this->acomptePercent) {
            $montant = floatval($this->totalTtc) * floatval($this->acomptePercent) / 100;
            return number_format($montant, 2, '.', '');
        }

        return null;
    }

    // Getters/Setters pour les champs Tiers éditables

    public function getTiersCivilite(): ?string
    {
        return $this->tiersCivilite;
    }

    public function setTiersCivilite(?string $tiersCivilite): static
    {
        $this->tiersCivilite = $tiersCivilite;
        return $this;
    }

    public function getTiersNom(): ?string
    {
        return $this->tiersNom;
    }

    public function setTiersNom(?string $tiersNom): static
    {
        $this->tiersNom = $tiersNom;
        return $this;
    }

    public function getTiersPrenom(): ?string
    {
        return $this->tiersPrenom;
    }

    public function setTiersPrenom(?string $tiersPrenom): static
    {
        $this->tiersPrenom = $tiersPrenom;
        return $this;
    }

    public function getTiersAdresse(): ?string
    {
        return $this->tiersAdresse;
    }

    public function setTiersAdresse(?string $tiersAdresse): static
    {
        $this->tiersAdresse = $tiersAdresse;
        return $this;
    }

    public function getTiersCodePostal(): ?string
    {
        return $this->tiersCodePostal;
    }

    public function setTiersCodePostal(?string $tiersCodePostal): static
    {
        $this->tiersCodePostal = $tiersCodePostal;
        return $this;
    }

    public function getTiersVille(): ?string
    {
        return $this->tiersVille;
    }

    public function setTiersVille(?string $tiersVille): static
    {
        $this->tiersVille = $tiersVille;
        return $this;
    }

    public function getTiersModeReglement(): ?string
    {
        return $this->tiersModeReglement;
    }

    public function setTiersModeReglement(?string $tiersModeReglement): static
    {
        $this->tiersModeReglement = $tiersModeReglement;
        return $this;
    }

    #[ORM\OneToMany(mappedBy: 'devis', targetEntity: DevisVersion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['versionNumber' => 'DESC'])]
    private Collection $versions;

    /**
     * @return Collection<int, DevisVersion>
     */
    public function getVersions(): Collection
    {
        if (!isset($this->versions)) {
            $this->versions = new ArrayCollection();
        }
        return $this->versions;
    }

    public function addVersion(DevisVersion $version): static
    {
        if (!$this->getVersions()->contains($version)) {
            $this->versions->add($version);
            $version->setDevis($this);
        }

        return $this;
    }

    public function removeVersion(DevisVersion $version): static
    {
        if ($this->getVersions()->removeElement($version)) {
            // set the owning side to null (unless already changed)
            if ($version->getDevis() === $this) {
                $version->setDevis(null);
            }
        }

        return $this;
    }

    /**
     * Récupère le nombre de versions de ce devis
     */
    public function getVersionCount(): int
    {
        return $this->getVersions()->count();
    }

    /**
     * Récupère la dernière version du devis
     */
    public function getLatestVersion(): ?DevisVersion
    {
        $versions = $this->getVersions()->toArray();
        if (empty($versions)) {
            return null;
        }
        
        // Trier par numéro de version décroissant
        usort($versions, fn($a, $b) => $b->getVersionNumber() <=> $a->getVersionNumber());
        return $versions[0];
    }

    /**
     * Vérifie si ce devis a des versions (historique)
     */
    public function hasVersions(): bool
    {
        return $this->getVersionCount() > 0;
    }

    /**
     * Vérifie si le devis peut être modifié (avec création de version)
     */
    public function canCreateVersion(): bool
    {
        return in_array($this->statut, ['envoye', 'signe']);
    }

    /**
     * Récupère le prochain numéro de version
     */
    public function getNextVersionNumber(): int
    {
        $latestVersion = $this->getLatestVersion();
        return $latestVersion ? $latestVersion->getVersionNumber() + 1 : 1;
    }
}