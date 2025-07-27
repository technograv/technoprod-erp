<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $numeroCommande = null;

    #[ORM\OneToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

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
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLivraisonPrevue = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateLivraisonReelle = null;

    #[ORM\Column(length: 30)]
    private ?string $statut = 'en_preparation';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTva = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalTtc = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesProduction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesLivraison = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeItem::class, orphanRemoval: true)]
    #[ORM\OrderBy(['ordreAffichage' => 'ASC'])]
    private Collection $commandeItems;


    public function __construct()
    {
        $this->commandeItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->dateCommande = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroCommande(): ?string
    {
        return $this->numeroCommande;
    }

    public function setNumeroCommande(string $numeroCommande): static
    {
        $this->numeroCommande = $numeroCommande;
        return $this;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(Devis $devis): static
    {
        $this->devis = $devis;
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

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTimeInterface $dateCommande): static
    {
        $this->dateCommande = $dateCommande;
        return $this;
    }

    public function getDateLivraisonPrevue(): ?\DateTimeInterface
    {
        return $this->dateLivraisonPrevue;
    }

    public function setDateLivraisonPrevue(?\DateTimeInterface $dateLivraisonPrevue): static
    {
        $this->dateLivraisonPrevue = $dateLivraisonPrevue;
        return $this;
    }

    public function getDateLivraisonReelle(): ?\DateTimeInterface
    {
        return $this->dateLivraisonReelle;
    }

    public function setDateLivraisonReelle(?\DateTimeInterface $dateLivraisonReelle): static
    {
        $this->dateLivraisonReelle = $dateLivraisonReelle;
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

    public function getNotesProduction(): ?string
    {
        return $this->notesProduction;
    }

    public function setNotesProduction(?string $notesProduction): static
    {
        $this->notesProduction = $notesProduction;
        return $this;
    }

    public function getNotesLivraison(): ?string
    {
        return $this->notesLivraison;
    }

    public function setNotesLivraison(?string $notesLivraison): static
    {
        $this->notesLivraison = $notesLivraison;
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
     * @return Collection<int, CommandeItem>
     */
    public function getCommandeItems(): Collection
    {
        return $this->commandeItems;
    }

    public function addCommandeItem(CommandeItem $commandeItem): static
    {
        if (!$this->commandeItems->contains($commandeItem)) {
            $this->commandeItems->add($commandeItem);
            $commandeItem->setCommande($this);
        }
        return $this;
    }

    public function removeCommandeItem(CommandeItem $commandeItem): static
    {
        if ($this->commandeItems->removeElement($commandeItem)) {
            if ($commandeItem->getCommande() === $this) {
                $commandeItem->setCommande(null);
            }
        }
        return $this;
    }


    public function generateNumeroCommande(): string
    {
        $year = date('Y');
        return $year . '-CMD-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function copyFromDevis(): void
    {
        if (!$this->devis) {
            return;
        }

        $this->client = $this->devis->getClient();
        $this->contact = $this->devis->getContact();
        $this->commercial = $this->devis->getCommercial();
        $this->totalHt = $this->devis->getTotalHt();
        $this->totalTva = $this->devis->getTotalTva();
        $this->totalTtc = $this->devis->getTotalTtc();

        // Copier les lignes
        foreach ($this->devis->getDevisItems() as $devisItem) {
            $commandeItem = new CommandeItem();
            $commandeItem->setCommande($this);
            $commandeItem->setDesignation($devisItem->getDesignation());
            $commandeItem->setDescription($devisItem->getDescription());
            $commandeItem->setQuantite($devisItem->getQuantite());
            $commandeItem->setPrixUnitaireHt($devisItem->getPrixUnitaireHt());
            $commandeItem->setRemisePercent($devisItem->getRemisePercent());
            $commandeItem->setTvaPercent($devisItem->getTvaPercent());
            $commandeItem->setTotalLigneHt($devisItem->getTotalLigneHt());
            $commandeItem->setOrdreAffichage($devisItem->getOrdreAffichage());

            $this->addCommandeItem($commandeItem);
        }
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'en_preparation' => 'En préparation',
            'confirmee' => 'Confirmée',
            'en_production' => 'En production',
            'expediee' => 'Expédiée',
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
            default => 'Inconnu'
        };
    }
}
