<?php

namespace App\Entity;

use App\Repository\FormeJuridiqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormeJuridiqueRepository::class)]
class FormeJuridique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private string $templateFormulaire = 'personne_morale'; // 'personne_physique' ou 'personne_morale'

    #[ORM\Column]
    private bool $actif = true;

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

    public function getTemplateFormulaire(): string
    {
        return $this->templateFormulaire;
    }

    public function setTemplateFormulaire(string $templateFormulaire): static
    {
        $this->templateFormulaire = $templateFormulaire;
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

    public function isPersonnePhysique(): bool
    {
        return $this->templateFormulaire === 'personne_physique';
    }

    public function isPersonneMorale(): bool
    {
        return $this->templateFormulaire === 'personne_morale';
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }

    /**
     * Méthode pour le futur système d'administration des templates
     */
    public function getAvailableTemplates(): array
    {
        return [
            'personne_physique' => 'Template Personne Physique',
            'personne_morale' => 'Template Personne Morale'
        ];
    }

    /**
     * Méthode pour vérifier si un template est valide
     */
    public function isValidTemplate(string $template): bool
    {
        return in_array($template, array_keys($this->getAvailableTemplates()));
    }
}
