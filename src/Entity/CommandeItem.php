<?php

namespace App\Entity;

use App\Repository\CommandeItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeItemRepository::class)]
class CommandeItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandeItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\Column(length: 255)]
    private ?string $designation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantite = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prixUnitaireHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $remisePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalLigneHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $tvaPercent = '20.00';

    #[ORM\Column]
    private ?int $ordreAffichage = 1;

    #[ORM\Column(length: 30)]
    private ?string $statutProduction = 'en_attente';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateProductionPrevue = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateProductionReelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesProduction = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(string $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaireHt(): ?string
    {
        return $this->prixUnitaireHt;
    }

    public function setPrixUnitaireHt(string $prixUnitaireHt): static
    {
        $this->prixUnitaireHt = $prixUnitaireHt;
        return $this;
    }

    public function getRemisePercent(): ?string
    {
        return $this->remisePercent;
    }

    public function setRemisePercent(?string $remisePercent): static
    {
        $this->remisePercent = $remisePercent;
        return $this;
    }

    public function getTotalLigneHt(): ?string
    {
        return $this->totalLigneHt;
    }

    public function setTotalLigneHt(string $totalLigneHt): static
    {
        $this->totalLigneHt = $totalLigneHt;
        return $this;
    }

    public function getTvaPercent(): ?string
    {
        return $this->tvaPercent;
    }

    public function setTvaPercent(string $tvaPercent): static
    {
        $this->tvaPercent = $tvaPercent;
        return $this;
    }

    public function getOrdreAffichage(): ?int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage;
        return $this;
    }

    public function getStatutProduction(): ?string
    {
        return $this->statutProduction;
    }

    public function setStatutProduction(string $statutProduction): static
    {
        $this->statutProduction = $statutProduction;
        return $this;
    }

    public function getDateProductionPrevue(): ?\DateTimeInterface
    {
        return $this->dateProductionPrevue;
    }

    public function setDateProductionPrevue(?\DateTimeInterface $dateProductionPrevue): static
    {
        $this->dateProductionPrevue = $dateProductionPrevue;
        return $this;
    }

    public function getDateProductionReelle(): ?\DateTimeInterface
    {
        return $this->dateProductionReelle;
    }

    public function setDateProductionReelle(?\DateTimeInterface $dateProductionReelle): static
    {
        $this->dateProductionReelle = $dateProductionReelle;
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

    public function getTotalLigneTtc(): string
    {
        $ht = floatval($this->totalLigneHt);
        $tva = $ht * floatval($this->tvaPercent) / 100;
        return number_format($ht + $tva, 2, '.', '');
    }

    public function getStatutProductionLabel(): string
    {
        return match($this->statutProduction) {
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'terminee' => 'Terminée',
            'suspendue' => 'Suspendue',
            default => 'Inconnu'
        };
    }
}
