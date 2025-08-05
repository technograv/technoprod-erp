<?php

namespace App\Entity;

use App\Repository\MethodeExpeditionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MethodeExpeditionRepository::class)]
class MethodeExpedition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $tarifBase = null; // Tarif de base

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $delaiMoyen = null; // Ex: "24h", "48h", "5 jours ouvrés"

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private bool $methodeParDefaut = false;

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

    public function getTarifBase(): ?string
    {
        return $this->tarifBase;
    }

    public function setTarifBase(?string $tarifBase): static
    {
        $this->tarifBase = $tarifBase;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDelaiMoyen(): ?string
    {
        return $this->delaiMoyen;
    }

    public function setDelaiMoyen(?string $delaiMoyen): static
    {
        $this->delaiMoyen = $delaiMoyen;
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

    public function isMethodeParDefaut(): bool
    {
        return $this->methodeParDefaut;
    }

    public function setMethodeParDefaut(bool $methodeParDefaut): static
    {
        $this->methodeParDefaut = $methodeParDefaut;
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