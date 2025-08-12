<?php

namespace App\Entity;

use App\Repository\DocumentNumerotationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentNumerotationRepository::class)]
#[ORM\Table(name: 'document_numerotation')]
class DocumentNumerotation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2, unique: true)]
    private ?string $prefixe = null;

    #[ORM\Column(length: 50)]
    private ?string $libelle = null;

    #[ORM\Column]
    private ?int $compteur = 1;

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

    public function getPrefixe(): ?string
    {
        return $this->prefixe;
    }

    public function setPrefixe(string $prefixe): static
    {
        $this->prefixe = strtoupper($prefixe);
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getCompteur(): ?int
    {
        return $this->compteur;
    }

    public function setCompteur(int $compteur): static
    {
        $this->compteur = $compteur;
        $this->updatedAt = new \DateTimeImmutable();
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
     * Génère le prochain numéro de document et incrémente le compteur
     */
    public function genererProchainNumero(): string
    {
        $numero = $this->prefixe . str_pad($this->compteur, 8, '0', STR_PAD_LEFT);
        $this->compteur++;
        $this->updatedAt = new \DateTimeImmutable();
        return $numero;
    }

    /**
     * Retourne le prochain numéro sans incrémenter le compteur (pour prévisualisation)
     */
    public function getProchainNumero(): string
    {
        return $this->prefixe . str_pad($this->compteur, 8, '0', STR_PAD_LEFT);
    }
}
