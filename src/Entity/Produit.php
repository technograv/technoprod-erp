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

    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: ProductImage::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['isDefault' => 'DESC', 'createdAt' => 'ASC'])]
    private Collection $images;

    // PHASE 1 - Nouveaux champs produits simples
    #[ORM\ManyToOne(targetEntity: FamilleProduit::class, inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FamilleProduit $famille = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'produitsCommeDefaut')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Fournisseur $fournisseurPrincipal = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $fraisPourcentage = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $quantiteDefaut = '1.0000';

    #[ORM\Column]
    private int $nombreDecimalesPrix = 2;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Unite $uniteVente = null;

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Unite $uniteAchat = null;

    // Comptabilité
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeDestination = null; // MARCHANDISE, PRODUIT_FINI, MATIERE_PREMIERE, etc.

    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(name: 'compte_vente_numero', referencedColumnName: 'numero_compte', nullable: true)]
    private ?ComptePCG $compteVente = null;

    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(name: 'compte_achat_numero', referencedColumnName: 'numero_compte', nullable: true)]
    private ?ComptePCG $compteAchat = null;

    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(name: 'compte_stock_numero', referencedColumnName: 'numero_compte', nullable: true)]
    private ?ComptePCG $compteStock = null;

    #[ORM\ManyToOne(targetEntity: ComptePCG::class)]
    #[ORM\JoinColumn(name: 'compte_variation_stock_numero', referencedColumnName: 'numero_compte', nullable: true)]
    private ?ComptePCG $compteVariationStock = null;

    // Phase 2 - Produits catalogue (à venir)
    #[ORM\Column]
    private bool $estCatalogue = false;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $typeProduction = null; // make_to_order, make_to_stock, assemble_to_order

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configurateur = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $complexite = null; // simple, moyen, complexe

    // Produits concurrent (prospection)
    #[ORM\Column]
    private bool $estConcurrent = false;

    // Collections
    #[ORM\OneToMany(mappedBy: 'produit', targetEntity: ProduitFournisseur::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $fournisseurs;

    #[ORM\OneToMany(mappedBy: 'produitPrincipal', targetEntity: ArticleLie::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $articlesLies;

    public function __construct()
    {
        $this->devisItems = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->fournisseurs = new ArrayCollection();
        $this->articlesLies = new ArrayCollection();
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

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduit($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getProduit() === $this) {
                $image->setProduit(null);
            }
        }

        return $this;
    }

    /**
     * Retourne l'image par défaut du produit
     */
    public function getDefaultImage(): ?ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->getIsDefault()) {
                return $image;
            }
        }
        
        // Si aucune image par défaut, retourner la première
        return $this->images->first() ?: null;
    }

    /**
     * Définit une image comme image par défaut (et retire le statut des autres)
     */
    public function setDefaultImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->addImage($image);
        }

        // Retirer le statut par défaut des autres images
        foreach ($this->images as $img) {
            $img->setIsDefault(false);
        }

        // Définir celle-ci comme par défaut
        $image->setIsDefault(true);

        return $this;
    }

    // PHASE 1 - Nouveaux getters/setters

    public function getFamille(): ?FamilleProduit
    {
        return $this->famille;
    }

    public function setFamille(?FamilleProduit $famille): static
    {
        $this->famille = $famille;
        return $this;
    }

    public function getFournisseurPrincipal(): ?Fournisseur
    {
        return $this->fournisseurPrincipal;
    }

    public function setFournisseurPrincipal(?Fournisseur $fournisseurPrincipal): static
    {
        $this->fournisseurPrincipal = $fournisseurPrincipal;
        return $this;
    }

    public function getFraisPourcentage(): string
    {
        return $this->fraisPourcentage;
    }

    public function setFraisPourcentage(string $fraisPourcentage): static
    {
        $this->fraisPourcentage = $fraisPourcentage;
        return $this;
    }

    public function getQuantiteDefaut(): string
    {
        return $this->quantiteDefaut;
    }

    public function setQuantiteDefaut(string $quantiteDefaut): static
    {
        $this->quantiteDefaut = $quantiteDefaut;
        return $this;
    }

    public function getNombreDecimalesPrix(): int
    {
        return $this->nombreDecimalesPrix;
    }

    public function setNombreDecimalesPrix(int $nombreDecimalesPrix): static
    {
        $this->nombreDecimalesPrix = $nombreDecimalesPrix;
        return $this;
    }

    public function getUniteVente(): ?Unite
    {
        return $this->uniteVente;
    }

    public function setUniteVente(?Unite $uniteVente): static
    {
        $this->uniteVente = $uniteVente;
        return $this;
    }

    public function getUniteAchat(): ?Unite
    {
        return $this->uniteAchat;
    }

    public function setUniteAchat(?Unite $uniteAchat): static
    {
        $this->uniteAchat = $uniteAchat;
        return $this;
    }

    public function getTypeDestination(): ?string
    {
        return $this->typeDestination;
    }

    public function setTypeDestination(?string $typeDestination): static
    {
        $this->typeDestination = $typeDestination;
        return $this;
    }

    public function getCompteVente(): ?ComptePCG
    {
        return $this->compteVente;
    }

    public function setCompteVente(?ComptePCG $compteVente): static
    {
        $this->compteVente = $compteVente;
        return $this;
    }

    public function getCompteAchat(): ?ComptePCG
    {
        return $this->compteAchat;
    }

    public function setCompteAchat(?ComptePCG $compteAchat): static
    {
        $this->compteAchat = $compteAchat;
        return $this;
    }

    public function getCompteStock(): ?ComptePCG
    {
        return $this->compteStock;
    }

    public function setCompteStock(?ComptePCG $compteStock): static
    {
        $this->compteStock = $compteStock;
        return $this;
    }

    public function getCompteVariationStock(): ?ComptePCG
    {
        return $this->compteVariationStock;
    }

    public function setCompteVariationStock(?ComptePCG $compteVariationStock): static
    {
        $this->compteVariationStock = $compteVariationStock;
        return $this;
    }

    public function isEstCatalogue(): bool
    {
        return $this->estCatalogue;
    }

    public function setEstCatalogue(bool $estCatalogue): static
    {
        $this->estCatalogue = $estCatalogue;
        return $this;
    }

    public function getTypeProduction(): ?string
    {
        return $this->typeProduction;
    }

    public function setTypeProduction(?string $typeProduction): static
    {
        $this->typeProduction = $typeProduction;
        return $this;
    }

    public function getConfigurateur(): ?array
    {
        return $this->configurateur;
    }

    public function setConfigurateur(?array $configurateur): static
    {
        $this->configurateur = $configurateur;
        return $this;
    }

    public function getComplexite(): ?string
    {
        return $this->complexite;
    }

    public function setComplexite(?string $complexite): static
    {
        $this->complexite = $complexite;
        return $this;
    }

    public function isEstConcurrent(): bool
    {
        return $this->estConcurrent;
    }

    public function setEstConcurrent(bool $estConcurrent): static
    {
        $this->estConcurrent = $estConcurrent;
        return $this;
    }

    /**
     * @return Collection<int, ProduitFournisseur>
     */
    public function getFournisseurs(): Collection
    {
        return $this->fournisseurs;
    }

    public function addFournisseur(ProduitFournisseur $fournisseur): static
    {
        if (!$this->fournisseurs->contains($fournisseur)) {
            $this->fournisseurs->add($fournisseur);
            $fournisseur->setProduit($this);
        }

        return $this;
    }

    public function removeFournisseur(ProduitFournisseur $fournisseur): static
    {
        if ($this->fournisseurs->removeElement($fournisseur)) {
            if ($fournisseur->getProduit() === $this) {
                $fournisseur->setProduit(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ArticleLie>
     */
    public function getArticlesLies(): Collection
    {
        return $this->articlesLies;
    }

    public function addArticlesLie(ArticleLie $articlesLie): static
    {
        if (!$this->articlesLies->contains($articlesLie)) {
            $this->articlesLies->add($articlesLie);
            $articlesLie->setProduitPrincipal($this);
        }

        return $this;
    }

    public function removeArticlesLie(ArticleLie $articlesLie): static
    {
        if ($this->articlesLies->removeElement($articlesLie)) {
            if ($articlesLie->getProduitPrincipal() === $this) {
                $articlesLie->setProduitPrincipal(null);
            }
        }

        return $this;
    }

    /**
     * Calcule le prix de revient (prix achat + frais)
     */
    public function getPrixRevient(): ?string
    {
        if ($this->prixAchatHt === null || $this->prixAchatHt === '0.00') {
            return null;
        }

        $pa = (float) $this->prixAchatHt;
        $frais = (float) $this->fraisPourcentage;

        return number_format($pa * (1 + $frais / 100), 4, '.', '');
    }

    /**
     * Calcule le taux de marge (réel basé sur prix de revient)
     */
    public function getTauxMargeReel(): ?float
    {
        $pr = $this->getPrixRevient();
        if ($pr === null || $pr === '0.0000') {
            return null;
        }

        $pv = (float) $this->prixVenteHt;
        $prFloat = (float) $pr;

        if ($prFloat === 0.0) {
            return null;
        }

        return (($pv - $prFloat) / $prFloat) * 100;
    }

    /**
     * Calcule le taux de marque
     */
    public function getTauxMarque(): ?float
    {
        $pr = $this->getPrixRevient();
        if ($pr === null) {
            return null;
        }

        $pv = (float) $this->prixVenteHt;
        if ($pv === 0.0) {
            return null;
        }

        $prFloat = (float) $pr;
        return (($pv - $prFloat) / $pv) * 100;
    }

    public function __toString(): string
    {
        return $this->designation . ($this->reference ? ' (' . $this->reference . ')' : '');
    }
}
