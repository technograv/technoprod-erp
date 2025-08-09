<?php

namespace App\Entity;

use App\Repository\GroupeUtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupeUtilisateurRepository::class)]
#[ORM\Table(name: 'groupe_utilisateur')]
#[ORM\HasLifecycleCallbacks]
class GroupeUtilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du groupe est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom du groupe ne peut pas dépasser {{ limit }} caractères')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?int $ordre = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Permissions du groupe (JSON)
    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    // Niveau de permission global (pour hiérarchisation des groupes)
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'Le niveau doit être entre {{ min }} et {{ max }}')]
    private ?int $niveau = 5;

    // Couleur pour identification visuelle
    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être au format hexadécimal (#RRGGBB)')]
    private ?string $couleur = null;

    // Relations avec les utilisateurs (Many-to-Many)
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'groupes')]
    private Collection $utilisateurs;

    // Groupe parent pour hiérarchie (optionnel)
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'enfants')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $parent = null;

    // Groupes enfants
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $enfants;

    // Sociétés auxquelles ce groupe donne accès (Many-to-Many)
    #[ORM\ManyToMany(targetEntity: Societe::class)]
    #[ORM\JoinTable(name: 'groupe_utilisateur_societe')]
    private Collection $societes;

    public function __construct()
    {
        $this->utilisateurs = new ArrayCollection();
        $this->enfants = new ArrayCollection();
        $this->societes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->permissions = [];
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
        $this->setUpdatedAtValue();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->setUpdatedAtValue();
        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        $this->setUpdatedAtValue();
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        $this->setUpdatedAtValue();
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

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;
        $this->setUpdatedAtValue();
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function addPermission(string $permission): static
    {
        if (!$this->hasPermission($permission)) {
            $this->permissions[] = $permission;
            $this->setUpdatedAtValue();
        }
        return $this;
    }

    public function removePermission(string $permission): static
    {
        $this->permissions = array_filter($this->permissions, fn($p) => $p !== $permission);
        $this->setUpdatedAtValue();
        return $this;
    }

    public function getNiveau(): ?int
    {
        return $this->niveau;
    }

    public function setNiveau(int $niveau): static
    {
        $this->niveau = $niveau;
        $this->setUpdatedAtValue();
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
        $this->setUpdatedAtValue();
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(User $utilisateur): static
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->add($utilisateur);
            $utilisateur->addGroupe($this);
        }
        return $this;
    }

    public function removeUtilisateur(User $utilisateur): static
    {
        if ($this->utilisateurs->removeElement($utilisateur)) {
            $utilisateur->removeGroupe($this);
        }
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        $this->setUpdatedAtValue();
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getEnfants(): Collection
    {
        return $this->enfants;
    }

    public function addEnfant(self $enfant): static
    {
        if (!$this->enfants->contains($enfant)) {
            $this->enfants->add($enfant);
            $enfant->setParent($this);
        }
        return $this;
    }

    public function removeEnfant(self $enfant): static
    {
        if ($this->enfants->removeElement($enfant)) {
            if ($enfant->getParent() === $this) {
                $enfant->setParent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Societe>
     */
    public function getSocietes(): Collection
    {
        return $this->societes;
    }

    public function addSociete(Societe $societe): static
    {
        if (!$this->societes->contains($societe)) {
            $this->societes->add($societe);
        }
        return $this;
    }

    public function removeSociete(Societe $societe): static
    {
        $this->societes->removeElement($societe);
        return $this;
    }

    // Méthodes utilitaires

    /**
     * Récupère toutes les permissions héritées (du parent et ses propres permissions)
     */
    public function getAllPermissions(): array
    {
        $allPermissions = $this->permissions;
        
        if ($this->parent) {
            $allPermissions = array_merge($allPermissions, $this->parent->getAllPermissions());
        }
        
        return array_unique($allPermissions);
    }

    /**
     * Vérifie si le groupe a une permission (directe ou héritée)
     */
    public function hasPermissionRecursive(string $permission): bool
    {
        return in_array($permission, $this->getAllPermissions(), true);
    }

    /**
     * Récupère toutes les sociétés accessibles (directes et héritées)
     */
    public function getAllSocietes(): array
    {
        $allSocietes = $this->societes->toArray();
        
        if ($this->parent) {
            $allSocietes = array_merge($allSocietes, $this->parent->getAllSocietes());
        }
        
        // Supprimer les doublons basés sur l'ID
        $uniqueSocietes = [];
        foreach ($allSocietes as $societe) {
            $uniqueSocietes[$societe->getId()] = $societe;
        }
        
        return array_values($uniqueSocietes);
    }

    /**
     * Vérifie si le groupe donne accès à une société (direct ou hérité)
     */
    public function hasAccessToSociete(Societe $societe): bool
    {
        foreach ($this->getAllSocietes() as $s) {
            if ($s->getId() === $societe->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Nombre total d'utilisateurs dans ce groupe
     */
    public function getNombreUtilisateurs(): int
    {
        return $this->utilisateurs->count();
    }

    /**
     * Obtient la profondeur dans la hiérarchie
     */
    public function getProfondeur(): int
    {
        $profondeur = 0;
        $parent = $this->parent;
        
        while ($parent !== null) {
            $profondeur++;
            $parent = $parent->getParent();
        }
        
        return $profondeur;
    }

    /**
     * Nom affiché avec indication de hiérarchie
     */
    public function getNomAffiche(): string
    {
        $prefix = str_repeat('—', $this->getProfondeur());
        return $prefix ? $prefix . ' ' . $this->nom : $this->nom;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
