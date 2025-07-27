<?php

namespace App\Entity;

use App\Repository\FactureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroFacture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contact = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $commercial = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateFacture = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = 'brouillon';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTtc = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantPaye = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantRestant = '0.00';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesFacturation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesComptabilite = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: FactureItem::class, orphanRemoval: true)]
    #[ORM\OrderBy(['ordreAffichage' => 'ASC'])]
    private Collection $factureItems;

    public function __construct()
    {
        $this->factureItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->dateFacture = new \DateTime();
        $this->dateEcheance = new \DateTime('+30 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroFacture(): ?string
    {
        return $this->numeroFacture;
    }

    public function setNumeroFacture(string $numeroFacture): static
    {
        $this->numeroFacture = $numeroFacture;
        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
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

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;
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

    public function getDateFacture(): ?\DateTimeInterface
    {
        return $this->dateFacture;
    }

    public function setDateFacture(\DateTimeInterface $dateFacture): static
    {
        $this->dateFacture = $dateFacture;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeInterface $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): static
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
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

    public function getMontantPaye(): ?string
    {
        return $this->montantPaye;
    }

    public function setMontantPaye(string $montantPaye): static
    {
        $this->montantPaye = $montantPaye;
        $this->calculateMontantRestant();
        return $this;
    }

    public function getMontantRestant(): ?string
    {
        return $this->montantRestant;
    }

    public function setMontantRestant(string $montantRestant): static
    {
        $this->montantRestant = $montantRestant;
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

    public function getNotesFacturation(): ?string
    {
        return $this->notesFacturation;
    }

    public function setNotesFacturation(?string $notesFacturation): static
    {
        $this->notesFacturation = $notesFacturation;
        return $this;
    }

    public function getNotesComptabilite(): ?string
    {
        return $this->notesComptabilite;
    }

    public function setNotesComptabilite(?string $notesComptabilite): static
    {
        $this->notesComptabilite = $notesComptabilite;
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
     * @return Collection<int, FactureItem>
     */
    public function getFactureItems(): Collection
    {
        return $this->factureItems;
    }

    public function addFactureItem(FactureItem $factureItem): static
    {
        if (!$this->factureItems->contains($factureItem)) {
            $this->factureItems->add($factureItem);
            $factureItem->setFacture($this);
        }
        return $this;
    }

    public function removeFactureItem(FactureItem $factureItem): static
    {
        if ($this->factureItems->removeElement($factureItem)) {
            if ($factureItem->getFacture() === $this) {
                $factureItem->setFacture(null);
            }
        }
        return $this;
    }

    public function generateNumeroFacture(): string
    {
        $year = date('Y');
        return $year . '-FACT-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function copyFromCommande(): void
    {
        if (!$this->commande) {
            return;
        }

        $this->client = $this->commande->getClient();
        $this->contact = $this->commande->getContact();
        $this->commercial = $this->commande->getCommercial();
        $this->totalHt = $this->commande->getTotalHt();
        $this->totalTva = $this->commande->getTotalTva();
        $this->totalTtc = $this->commande->getTotalTtc();
        $this->montantRestant = $this->totalTtc;

        // Copier les lignes
        foreach ($this->commande->getCommandeItems() as $commandeItem) {
            $factureItem = new FactureItem();
            $factureItem->setFacture($this);
            $factureItem->setDesignation($commandeItem->getDesignation());
            $factureItem->setDescription($commandeItem->getDescription());
            $factureItem->setQuantite($commandeItem->getQuantite());
            $factureItem->setPrixUnitaireHt($commandeItem->getPrixUnitaireHt());
            $factureItem->setRemisePercent($commandeItem->getRemisePercent());
            $factureItem->setTvaPercent($commandeItem->getTvaPercent());
            $factureItem->setTotalLigneHt($commandeItem->getTotalLigneHt());
            $factureItem->setOrdreAffichage($commandeItem->getOrdreAffichage());

            $this->addFactureItem($factureItem);
        }
    }

    private function calculateMontantRestant(): void
    {
        $totalTtc = floatval($this->totalTtc);
        $montantPaye = floatval($this->montantPaye);
        $this->montantRestant = number_format(max(0, $totalTtc - $montantPaye), 2, '.', '');
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'brouillon' => 'Brouillon',
            'envoyee' => 'Envoyée',
            'en_relance' => 'En relance',
            'payee' => 'Payée',
            'en_litige' => 'En litige',
            'annulee' => 'Annulée',
            'archivee' => 'Archivée',
            default => 'Inconnu'
        };
    }

    public function isPayee(): bool
    {
        return $this->statut === 'payee' || floatval($this->montantRestant) <= 0;
    }

    public function getJoursRetard(): int
    {
        if (!$this->dateEcheance || $this->isPayee()) {
            return 0;
        }

        $today = new \DateTime();
        $diff = $today->diff($this->dateEcheance);
        
        return $today > $this->dateEcheance ? $diff->days : 0;
    }
}
