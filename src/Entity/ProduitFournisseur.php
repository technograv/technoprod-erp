<?php

namespace App\Entity;

use App\Repository\ProduitFournisseurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitFournisseurRepository::class)]
#[ORM\Table(name: 'produit_fournisseur')]
#[ORM\UniqueConstraint(name: 'unique_produit_fournisseur', columns: ['produit_id', 'fournisseur_id'])]
class ProduitFournisseur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Produit::class, inversedBy: 'fournisseurs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class, inversedBy: 'produitsFournis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenceFournisseur = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $prixVenteConseille = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $remiseSurPVC = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    #[Assert\NotBlank(message: 'Le prix d\'achat public est obligatoire')]
    #[Assert\PositiveOrZero]
    private ?string $prixAchatPublic = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $remiseAchat = '0.00';

    #[ORM\ManyToOne(targetEntity: Unite::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Unite $uniteAchat = null;

    #[ORM\Column]
    private int $multipleCommande = 1;

    #[ORM\Column(nullable: true)]
    private ?int $delaiLivraisonJours = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codeEcoContribution = null;

    #[ORM\Column]
    private int $priorite = 0;

    #[ORM\Column]
    private bool $actif = true;

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

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;
        return $this;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): static
    {
        $this->fournisseur = $fournisseur;
        return $this;
    }

    public function getReferenceFournisseur(): ?string
    {
        return $this->referenceFournisseur;
    }

    public function setReferenceFournisseur(?string $referenceFournisseur): static
    {
        $this->referenceFournisseur = $referenceFournisseur;
        return $this;
    }

    public function getPrixVenteConseille(): ?string
    {
        return $this->prixVenteConseille;
    }

    public function setPrixVenteConseille(?string $prixVenteConseille): static
    {
        $this->prixVenteConseille = $prixVenteConseille;
        return $this;
    }

    public function getRemiseSurPVC(): ?string
    {
        return $this->remiseSurPVC;
    }

    public function setRemiseSurPVC(?string $remiseSurPVC): static
    {
        $this->remiseSurPVC = $remiseSurPVC;
        return $this;
    }

    public function getPrixAchatPublic(): ?string
    {
        return $this->prixAchatPublic;
    }

    public function setPrixAchatPublic(string $prixAchatPublic): static
    {
        $this->prixAchatPublic = $prixAchatPublic;
        return $this;
    }

    public function getRemiseAchat(): ?string
    {
        return $this->remiseAchat;
    }

    public function setRemiseAchat(string $remiseAchat): static
    {
        $this->remiseAchat = $remiseAchat;
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

    public function getMultipleCommande(): int
    {
        return $this->multipleCommande;
    }

    public function setMultipleCommande(int $multipleCommande): static
    {
        $this->multipleCommande = $multipleCommande;
        return $this;
    }

    public function getDelaiLivraisonJours(): ?int
    {
        return $this->delaiLivraisonJours;
    }

    public function setDelaiLivraisonJours(?int $delaiLivraisonJours): static
    {
        $this->delaiLivraisonJours = $delaiLivraisonJours;
        return $this;
    }

    public function getCodeEcoContribution(): ?string
    {
        return $this->codeEcoContribution;
    }

    public function setCodeEcoContribution(?string $codeEcoContribution): static
    {
        $this->codeEcoContribution = $codeEcoContribution;
        return $this;
    }

    public function getPriorite(): int
    {
        return $this->priorite;
    }

    public function setPriorite(int $priorite): static
    {
        $this->priorite = $priorite;
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
     * Calcule le prix d'achat net HT
     */
    public function getPrixAchatNetHT(): string
    {
        $prixPublic = (float) $this->prixAchatPublic;
        $remise = (float) $this->remiseAchat;

        return number_format($prixPublic * (1 - $remise / 100), 4, '.', '');
    }

    public function __toString(): string
    {
        return sprintf(
            '%s chez %s',
            $this->produit?->getDesignation() ?? 'Produit',
            $this->fournisseur?->getRaisonSociale() ?? 'Fournisseur'
        );
    }
}
