<?php

namespace App\Entity;

use App\Repository\LayoutElementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LayoutElementRepository::class)]
#[ORM\Table(name: 'layout_element')]
class LayoutElement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Devis::class, inversedBy: 'layoutElements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Devis $devis = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(['line_break', 'page_break', 'subtotal', 'section_title', 'separator'])]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Range(min: 0)]
    private ?int $ordreAffichage = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contenu = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $parametres = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->parametres = [];
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

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getOrdreAffichage(): ?int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getParametres(): ?array
    {
        return $this->parametres;
    }

    public function setParametres(?array $parametres): static
    {
        $this->parametres = $parametres ?? [];
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getParametre(string $key, mixed $default = null): mixed
    {
        return $this->parametres[$key] ?? $default;
    }

    public function setParametre(string $key, mixed $value): static
    {
        $parametres = $this->parametres ?? [];
        $parametres[$key] = $value;
        $this->parametres = $parametres;
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
     * Retourne le libellé affiché pour cet élément de mise en page
     */
    public function getDisplayLabel(): string
    {
        return match ($this->type) {
            'line_break' => 'Saut de ligne',
            'page_break' => 'Saut de page',
            'subtotal' => $this->titre ?: 'Sous-total',
            'section_title' => $this->titre ?: 'Titre de section',
            'separator' => 'Séparateur',
            default => 'Élément de mise en page'
        };
    }

    /**
     * Retourne l'icône FontAwesome associée au type
     */
    public function getIcon(): string
    {
        return match ($this->type) {
            'line_break' => 'fas fa-minus',
            'page_break' => 'fas fa-file-medical',
            'subtotal' => 'fas fa-calculator',
            'section_title' => 'fas fa-heading',
            'separator' => 'fas fa-grip-lines',
            default => 'fas fa-puzzle-piece'
        };
    }

    /**
     * Retourne la classe CSS pour le style de l'élément
     */
    public function getCssClass(): string
    {
        $baseClass = 'layout-element';
        $typeClass = match ($this->type) {
            'line_break' => 'layout-line-break',
            'page_break' => 'layout-page-break',
            'subtotal' => 'layout-subtotal',
            'section_title' => 'layout-section-title',
            'separator' => 'layout-separator',
            default => 'layout-generic'
        };
        
        return $baseClass . ' ' . $typeClass;
    }

    /**
     * Vérifie si cet élément de mise en page est éditable
     */
    public function isEditable(): bool
    {
        return in_array($this->type, ['subtotal', 'section_title']);
    }

    /**
     * Retourne les données JSON pour la sérialisation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'ordre_affichage' => $this->ordreAffichage,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'parametres' => $this->parametres,
            'display_label' => $this->getDisplayLabel(),
            'icon' => $this->getIcon(),
            'css_class' => $this->getCssClass(),
            'is_editable' => $this->isEditable(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }
}