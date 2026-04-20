<?php

namespace App\Entity;

use App\Repository\TemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TemplateRepository::class)]
#[ORM\Table(name: 'template')]
class Template
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $typeDocument = null; // devis, facture, bon_commande, etc.

    #[ORM\ManyToOne(targetEntity: Societe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    #[ORM\ManyToOne(targetEntity: ConditionsVente::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ConditionsVente $conditionsVente = null;

    #[ORM\ManyToOne(targetEntity: Banque::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Banque $banque = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $optionsMiseEnPage = null; // Options de layout en JSON

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?int $ordre = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->actif = true;
        $this->ordre = 0;
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

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): static
    {
        $this->typeDocument = $typeDocument;
        return $this;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;
        return $this;
    }

    public function getConditionsVente(): ?ConditionsVente
    {
        return $this->conditionsVente;
    }

    public function setConditionsVente(?ConditionsVente $conditionsVente): static
    {
        $this->conditionsVente = $conditionsVente;
        return $this;
    }

    public function getBanque(): ?Banque
    {
        return $this->banque;
    }

    public function setBanque(?Banque $banque): static
    {
        $this->banque = $banque;
        return $this;
    }

    public function getOptionsMiseEnPage(): ?array
    {
        return $this->optionsMiseEnPage;
    }

    public function setOptionsMiseEnPage(?array $optionsMiseEnPage): static
    {
        $this->optionsMiseEnPage = $optionsMiseEnPage;
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
