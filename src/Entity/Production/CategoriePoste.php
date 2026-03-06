<?php

namespace App\Entity\Production;

use App\Repository\Production\CategoriePosteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Catégorie de poste de travail
 *
 * Permet de regrouper les postes par type d'activité :
 * - Impression (imprimantes UV, numériques, etc.)
 * - Découpe (plotters, CNC, laser, etc.)
 * - Montage (assemblage, câblage, etc.)
 * - Finition (lamination, vernis, etc.)
 * - Pose (chantier, installation)
 * - Graphisme (création, BAT)
 */
#[ORM\Entity(repositoryClass: CategoriePosteRepository::class)]
#[ORM\Table(name: 'categorie_poste')]
#[UniqueEntity(fields: ['code'], message: 'Ce code de catégorie existe déjà.')]
class CategoriePoste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Icône Font Awesome pour l'affichage
     * Exemples: "fa-print", "fa-cut", "fa-tools"
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icone = null;

    /**
     * Couleur pour l'affichage (code hexa)
     * Exemple: "#3498db"
     */
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private bool $actif = true;

    /**
     * @var Collection<int, PosteTravail>
     */
    #[ORM\OneToMany(targetEntity: PosteTravail::class, mappedBy: 'categorie')]
    private Collection $postes;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->postes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->libelle;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getIcone(): ?string
    {
        return $this->icone;
    }

    public function setIcone(?string $icone): static
    {
        $this->icone = $icone;
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
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

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    /**
     * @return Collection<int, PosteTravail>
     */
    public function getPostes(): Collection
    {
        return $this->postes;
    }

    public function addPoste(PosteTravail $poste): static
    {
        if (!$this->postes->contains($poste)) {
            $this->postes->add($poste);
            $poste->setCategorie($this);
        }

        return $this;
    }

    public function removePoste(PosteTravail $poste): static
    {
        if ($this->postes->removeElement($poste)) {
            if ($poste->getCategorie() === $this) {
                $poste->setCategorie(null);
            }
        }

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
