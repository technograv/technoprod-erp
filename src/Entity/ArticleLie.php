<?php

namespace App\Entity;

use App\Repository\ArticleLieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleLieRepository::class)]
#[ORM\Table(name: 'article_lie')]
class ArticleLie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Produit::class, inversedBy: 'articlesLies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produitPrincipal = null;

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produitLie = null;

    #[ORM\Column(length: 20)]
    private string $typeRelation = 'optionnel'; // 'optionnel', 'complementaire', 'alternatif', 'pack'

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $quantiteDefaut = '1.0000';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduitPrincipal(): ?Produit
    {
        return $this->produitPrincipal;
    }

    public function setProduitPrincipal(?Produit $produitPrincipal): static
    {
        $this->produitPrincipal = $produitPrincipal;
        return $this;
    }

    public function getProduitLie(): ?Produit
    {
        return $this->produitLie;
    }

    public function setProduitLie(?Produit $produitLie): static
    {
        $this->produitLie = $produitLie;
        return $this;
    }

    public function getTypeRelation(): string
    {
        return $this->typeRelation;
    }

    public function setTypeRelation(string $typeRelation): static
    {
        $this->typeRelation = $typeRelation;
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

    public function getQuantiteDefaut(): string
    {
        return $this->quantiteDefaut;
    }

    public function setQuantiteDefaut(string $quantiteDefaut): static
    {
        $this->quantiteDefaut = $quantiteDefaut;
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

    public function getTypeRelationLibelle(): string
    {
        return match($this->typeRelation) {
            'optionnel' => 'Optionnel',
            'complementaire' => 'Complémentaire',
            'alternatif' => 'Alternatif',
            'pack' => 'Pack',
            default => 'Inconnu'
        };
    }
}
