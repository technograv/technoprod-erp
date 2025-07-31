<?php

namespace App\Entity;

use App\Repository\TauxTVARepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TauxTVARepository::class)]
#[ORM\HasLifecycleCallbacks]
class TauxTVA
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $taux = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private bool $parDefaut = false;

    #[ORM\Column]
    private int $ordre = 1;

    // === PARTIE VENTE - Comptes TVA ===
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteCompteDebits = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteCompteEncaissements = null;

    // === PARTIE VENTE - Comptes de Gestion ===
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteCompteBiens = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteCompteServices = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteComptePorts = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteCompteEcoContribution = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $venteCompteEcoContributionMobilier = null;

    // === PARTIE ACHATS - Comptes TVA ===
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteDebits = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteEncaissements = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteAutoliquidationBiens = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteAutoliquidationServices = null;

    // === PARTIE ACHATS - Comptes de Gestion ===
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteBiens = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteServices = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatComptePorts = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteEcoContribution = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $achatCompteEcoContributionMobilier = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
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
        return $this;
    }

    public function getTaux(): ?string
    {
        return $this->taux;
    }

    public function setTaux(string $taux): static
    {
        $this->taux = $taux;
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

    public function isParDefaut(): bool
    {
        return $this->parDefaut;
    }

    public function setParDefaut(bool $parDefaut): static
    {
        $this->parDefaut = $parDefaut;
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

    // === GETTERS/SETTERS VENTE TVA ===
    public function getVenteCompteDebits(): ?string
    {
        return $this->venteCompteDebits;
    }

    public function setVenteCompteDebits(?string $venteCompteDebits): static
    {
        $this->venteCompteDebits = $venteCompteDebits;
        return $this;
    }

    public function getVenteCompteEncaissements(): ?string
    {
        return $this->venteCompteEncaissements;
    }

    public function setVenteCompteEncaissements(?string $venteCompteEncaissements): static
    {
        $this->venteCompteEncaissements = $venteCompteEncaissements;
        return $this;
    }

    // === GETTERS/SETTERS VENTE GESTION ===
    public function getVenteCompteBiens(): ?string
    {
        return $this->venteCompteBiens;
    }

    public function setVenteCompteBiens(?string $venteCompteBiens): static
    {
        $this->venteCompteBiens = $venteCompteBiens;
        return $this;
    }

    public function getVenteCompteServices(): ?string
    {
        return $this->venteCompteServices;
    }

    public function setVenteCompteServices(?string $venteCompteServices): static
    {
        $this->venteCompteServices = $venteCompteServices;
        return $this;
    }

    public function getVenteComptePorts(): ?string
    {
        return $this->venteComptePorts;
    }

    public function setVenteComptePorts(?string $venteComptePorts): static
    {
        $this->venteComptePorts = $venteComptePorts;
        return $this;
    }

    public function getVenteCompteEcoContribution(): ?string
    {
        return $this->venteCompteEcoContribution;
    }

    public function setVenteCompteEcoContribution(?string $venteCompteEcoContribution): static
    {
        $this->venteCompteEcoContribution = $venteCompteEcoContribution;
        return $this;
    }

    public function getVenteCompteEcoContributionMobilier(): ?string
    {
        return $this->venteCompteEcoContributionMobilier;
    }

    public function setVenteCompteEcoContributionMobilier(?string $venteCompteEcoContributionMobilier): static
    {
        $this->venteCompteEcoContributionMobilier = $venteCompteEcoContributionMobilier;
        return $this;
    }

    // === GETTERS/SETTERS ACHATS TVA ===
    public function getAchatCompteDebits(): ?string
    {
        return $this->achatCompteDebits;
    }

    public function setAchatCompteDebits(?string $achatCompteDebits): static
    {
        $this->achatCompteDebits = $achatCompteDebits;
        return $this;
    }

    public function getAchatCompteEncaissements(): ?string
    {
        return $this->achatCompteEncaissements;
    }

    public function setAchatCompteEncaissements(?string $achatCompteEncaissements): static
    {
        $this->achatCompteEncaissements = $achatCompteEncaissements;
        return $this;
    }

    public function getAchatCompteAutoliquidationBiens(): ?string
    {
        return $this->achatCompteAutoliquidationBiens;
    }

    public function setAchatCompteAutoliquidationBiens(?string $achatCompteAutoliquidationBiens): static
    {
        $this->achatCompteAutoliquidationBiens = $achatCompteAutoliquidationBiens;
        return $this;
    }

    public function getAchatCompteAutoliquidationServices(): ?string
    {
        return $this->achatCompteAutoliquidationServices;
    }

    public function setAchatCompteAutoliquidationServices(?string $achatCompteAutoliquidationServices): static
    {
        $this->achatCompteAutoliquidationServices = $achatCompteAutoliquidationServices;
        return $this;
    }

    // === GETTERS/SETTERS ACHATS GESTION ===
    public function getAchatCompteBiens(): ?string
    {
        return $this->achatCompteBiens;
    }

    public function setAchatCompteBiens(?string $achatCompteBiens): static
    {
        $this->achatCompteBiens = $achatCompteBiens;
        return $this;
    }

    public function getAchatCompteServices(): ?string
    {
        return $this->achatCompteServices;
    }

    public function setAchatCompteServices(?string $achatCompteServices): static
    {
        $this->achatCompteServices = $achatCompteServices;
        return $this;
    }

    public function getAchatComptePorts(): ?string
    {
        return $this->achatComptePorts;
    }

    public function setAchatComptePorts(?string $achatComptePorts): static
    {
        $this->achatComptePorts = $achatComptePorts;
        return $this;
    }

    public function getAchatCompteEcoContribution(): ?string
    {
        return $this->achatCompteEcoContribution;
    }

    public function setAchatCompteEcoContribution(?string $achatCompteEcoContribution): static
    {
        $this->achatCompteEcoContribution = $achatCompteEcoContribution;
        return $this;
    }

    public function getAchatCompteEcoContributionMobilier(): ?string
    {
        return $this->achatCompteEcoContributionMobilier;
    }

    public function setAchatCompteEcoContributionMobilier(?string $achatCompteEcoContributionMobilier): static
    {
        $this->achatCompteEcoContributionMobilier = $achatCompteEcoContributionMobilier;
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
}
