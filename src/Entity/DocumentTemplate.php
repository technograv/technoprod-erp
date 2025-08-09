<?php

namespace App\Entity;

use App\Repository\DocumentTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentTemplateRepository::class)]
class DocumentTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['devis', 'commande', 'facture', 'avoir', 'bon_livraison', 'bon_commande'])]
    private ?string $typeDocument = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $cheminFichier = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $estActif = true;

    #[ORM\Column]
    private bool $estDefaut = false;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\ManyToOne(targetEntity: Societe::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Societe $societe = null;

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

    public function getCheminFichier(): ?string
    {
        return $this->cheminFichier;
    }

    public function setCheminFichier(string $cheminFichier): static
    {
        $this->cheminFichier = $cheminFichier;
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

    public function isEstActif(): bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isEstDefaut(): bool
    {
        return $this->estDefaut;
    }

    public function setEstDefaut(bool $estDefaut): static
    {
        $this->estDefaut = $estDefaut;
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

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;
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

    /**
     * Types de documents supportés
     */
    public static function getTypesDocuments(): array
    {
        return [
            'devis' => 'Devis',
            'commande' => 'Commande',
            'facture' => 'Facture',
            'avoir' => 'Avoir',
            'bon_livraison' => 'Bon de livraison',
            'bon_commande' => 'Bon de commande'
        ];
    }

    /**
     * Retourne le libellé du type de document
     */
    public function getTypeDocumentLabel(): string
    {
        $types = self::getTypesDocuments();
        return $types[$this->typeDocument] ?? $this->typeDocument;
    }
}
