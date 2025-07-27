<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $designation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'La référence est obligatoire')]
    #[Assert\Length(max: 50, maxMessage: 'La référence ne peut pas dépasser {{ limit }} caractères')]
    private ?string $reference = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type est obligatoire')]
    private ?string $type = 'produit'; // 'produit', 'service', 'forfait'

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix d\'achat est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix d\'achat doit être positif')]
    private ?string $prixAchatHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix de vente est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix de vente doit être positif')]
    private ?string $prixVenteHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'La marge doit être positive')]
    private ?string $margePercent = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $tvaPercent = '20.00';

    #[ORM\Column(length: 50)]
    private ?string $unite = 'unité';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le stock doit être positif')]
    private ?int $stockQuantite = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le stock minimum doit être positif')]
    private ?int $stockMinimum = null;

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?bool $gestionStock = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesInternes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: DevisItem::class)]
    private Collection $devisItems;

    public function __construct()
    {
        $this->devisItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getPrixAchatHt(): ?string
    {
        return $this->prixAchatHt;
    }

    public function setPrixAchatHt(string $prixAchatHt): static
    {
        $this->prixAchatHt = $prixAchatHt;
        return $this;
    }

    public function getPrixVenteHt(): ?string
    {
        return $this->prixVenteHt;
    }

    public function setPrixVenteHt(string $prixVenteHt): static
    {
        $this->prixVenteHt = $prixVenteHt;
        return $this;
    }

    public function getMargePercent(): ?string
    {
        return $this->margePercent;
    }

    public function setMargePercent(?string $margePercent): static
    {
        $this->margePercent = $margePercent;
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

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        $this->unite = $unite;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getStockQuantite(): ?int
    {
        return $this->stockQuantite;
    }

    public function setStockQuantite(?int $stockQuantite): static
    {
        $this->stockQuantite = $stockQuantite;
        return $this;
    }

    public function getStockMinimum(): ?int
    {
        return $this->stockMinimum;
    }

    public function setStockMinimum(?int $stockMinimum): static
    {
        $this->stockMinimum = $stockMinimum;
        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function isGestionStock(): ?bool
    {
        return $this->gestionStock;
    }

    public function setGestionStock(bool $gestionStock): static
    {
        $this->gestionStock = $gestionStock;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
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
            $devisItem->setProduit($this);
        }

        return $this;
    }

    public function removeDevisItem(DevisItem $devisItem): static
    {
        if ($this->devisItems->removeElement($devisItem)) {
            if ($devisItem->getProduit() === $this) {
                $devisItem->setProduit(null);
            }
        }

        return $this;
    }

    // Méthodes utilitaires

    public function getTypeLibelle(): string
    {
        return match($this->type) {
            'produit' => 'Produit',
            'service' => 'Service',
            'forfait' => 'Forfait',
            default => 'Inconnu'
        };
    }

    public function getPrixVenteTtc(): string
    {
        $ht = floatval($this->prixVenteHt);
        $tva = $ht * floatval($this->tvaPercent) / 100;
        return number_format($ht + $tva, 2, '.', '');
    }

    public function calculateMarge(): void
    {
        $achat = floatval($this->prixAchatHt);
        $vente = floatval($this->prixVenteHt);
        
        if ($achat > 0) {
            $marge = (($vente - $achat) / $achat) * 100;
            $this->margePercent = number_format($marge, 2, '.', '');
        }
    }

    public function isStockFaible(): bool
    {
        if (!$this->gestionStock || $this->stockMinimum === null) {
            return false;
        }
        
        return $this->stockQuantite !== null && $this->stockQuantite <= $this->stockMinimum;
    }

    public function isStockEpuise(): bool
    {
        if (!$this->gestionStock) {
            return false;
        }
        
        return $this->stockQuantite !== null && $this->stockQuantite <= 0;
    }

    public function __toString(): string
    {
        return $this->designation . ($this->reference ? ' (' . $this->reference . ')' : '');
    }
}
