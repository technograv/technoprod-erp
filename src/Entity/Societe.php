<?php

namespace App\Entity;

use App\Repository\SocieteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SocieteRepository::class)]
#[ORM\Table(name: 'societe')]
class Societe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la société est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private string $nom;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: ['mere', 'fille'], message: 'Le type doit être "mère" ou "fille"')]
    private string $type;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'societesFilles')]
    #[ORM\JoinColumn(name: 'societe_parent_id', referencedColumnName: 'id', nullable: true)]
    private ?Societe $societeParent = null;

    #[ORM\OneToMany(mappedBy: 'societeParent', targetEntity: self::class)]
    private Collection $societesFilles;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $numeroTva = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $siteWeb = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur primaire doit être un code hexadécimal valide')]
    private ?string $couleurPrimaire = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur secondaire doit être un code hexadécimal valide')]
    private ?string $couleurSecondaire = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $parametresCustom = [];

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'societe', targetEntity: UserSocieteRole::class, orphanRemoval: true)]
    private Collection $userRoles;

    public function __construct()
    {
        $this->societesFilles = new ArrayCollection();
        $this->userRoles = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->parametresCustom = [];
    }

    // Getters et Setters
    public function getId(): int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        $this->updateTimestamp();
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        $this->updateTimestamp();
        return $this;
    }

    public function getSocieteParent(): ?self
    {
        return $this->societeParent;
    }

    public function setSocieteParent(?self $societeParent): self
    {
        $this->societeParent = $societeParent;
        $this->updateTimestamp();
        return $this;
    }

    /**
     * @return Collection<int, Societe>
     */
    public function getSocietesFilles(): Collection
    {
        return $this->societesFilles;
    }

    public function addSocieteFille(self $societeFille): self
    {
        if (!$this->societesFilles->contains($societeFille)) {
            $this->societesFilles->add($societeFille);
            $societeFille->setSocieteParent($this);
        }
        return $this;
    }

    public function removeSocieteFille(self $societeFille): self
    {
        if ($this->societesFilles->removeElement($societeFille)) {
            if ($societeFille->getSocieteParent() === $this) {
                $societeFille->setSocieteParent(null);
            }
        }
        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): self
    {
        $this->siret = $siret;
        $this->updateTimestamp();
        return $this;
    }

    public function getNumeroTva(): ?string
    {
        return $this->numeroTva;
    }

    public function setNumeroTva(?string $numeroTva): self
    {
        $this->numeroTva = $numeroTva;
        $this->updateTimestamp();
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        $this->updateTimestamp();
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;
        $this->updateTimestamp();
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        $this->updateTimestamp();
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = $pays;
        $this->updateTimestamp();
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        $this->updateTimestamp();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        $this->updateTimestamp();
        return $this;
    }

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): self
    {
        $this->siteWeb = $siteWeb;
        $this->updateTimestamp();
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        $this->updateTimestamp();
        return $this;
    }

    public function getCouleurPrimaire(): ?string
    {
        return $this->couleurPrimaire;
    }

    public function setCouleurPrimaire(?string $couleurPrimaire): self
    {
        $this->couleurPrimaire = $couleurPrimaire;
        $this->updateTimestamp();
        return $this;
    }

    public function getCouleurSecondaire(): ?string
    {
        return $this->couleurSecondaire;
    }

    public function setCouleurSecondaire(?string $couleurSecondaire): self
    {
        $this->couleurSecondaire = $couleurSecondaire;
        $this->updateTimestamp();
        return $this;
    }

    public function getParametresCustom(): ?array
    {
        return $this->parametresCustom;
    }

    public function setParametresCustom(?array $parametresCustom): self
    {
        $this->parametresCustom = $parametresCustom ?? [];
        $this->updateTimestamp();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->updateTimestamp();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, UserSocieteRole>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(UserSocieteRole $userRole): self
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles->add($userRole);
            $userRole->setSociete($this);
        }
        return $this;
    }

    public function removeUserRole(UserSocieteRole $userRole): self
    {
        if ($this->userRoles->removeElement($userRole)) {
            if ($userRole->getSociete() === $this) {
                $userRole->setSociete(null);
            }
        }
        return $this;
    }

    // Méthodes utilitaires
    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isMere(): bool
    {
        return $this->type === 'mere';
    }

    public function isFille(): bool
    {
        return $this->type === 'fille';
    }

    public function getDisplayName(): string
    {
        if ($this->isFille() && $this->societeParent) {
            return $this->societeParent->getNom() . ' - ' . $this->nom;
        }
        return $this->nom;
    }

    /**
     * Récupère un paramètre custom avec valeur par défaut
     */
    public function getParametreCustom(string $key, mixed $default = null): mixed
    {
        return $this->parametresCustom[$key] ?? $default;
    }

    /**
     * Définit un paramètre custom
     */
    public function setParametreCustom(string $key, mixed $value): self
    {
        if ($this->parametresCustom === null) {
            $this->parametresCustom = [];
        }
        $this->parametresCustom[$key] = $value;
        $this->updateTimestamp();
        return $this;
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}