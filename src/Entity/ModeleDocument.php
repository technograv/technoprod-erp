<?php

namespace App\Entity;

use App\Repository\ModeleDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeleDocumentRepository::class)]
class ModeleDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $typeDocument = null; // 'DEVIS', 'FACTURE', 'BON_COMMANDE', 'BON_LIVRAISON', etc.

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $templateFile = null; // Nom du fichier template

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $css = null; // CSS personnalisé pour le template

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private bool $modeleParDefaut = false;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): static
    {
        $this->typeDocument = $typeDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTemplateFile(): ?string
    {
        return $this->templateFile;
    }

    public function setTemplateFile(?string $templateFile): static
    {
        $this->templateFile = $templateFile;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCss(): ?string
    {
        return $this->css;
    }

    public function setCss(?string $css): static
    {
        $this->css = $css;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isModeleParDefaut(): bool
    {
        return $this->modeleParDefaut;
    }

    public function setModeleParDefaut(bool $modeleParDefaut): static
    {
        $this->modeleParDefaut = $modeleParDefaut;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}