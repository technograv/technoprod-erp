<?php

namespace App\Entity;

use App\Repository\UserPermissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserPermissionRepository::class)]
#[ORM\Table(name: 'user_permission')]
#[ORM\HasLifecycleCallbacks]
class UserPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'permissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Societe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    // Permissions individuelles (JSON array)
    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    // Niveau de permission (1-10) pour hiérarchisation
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 10, notInRangeMessage: 'Le niveau doit être entre {{ min }} et {{ max }}')]
    private ?int $niveau = 5;

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        $this->setUpdatedAtValue();
        return $this;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;
        $this->setUpdatedAtValue();
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        $this->setUpdatedAtValue();
        return $this;
    }

    /**
     * Récupère la clé unique pour cette permission (utilisateur + société)
     */
    public function getUniqueKey(): string
    {
        return $this->user?->getId() . '_' . $this->societe?->getId();
    }

    /**
     * Vérifie si cette permission est plus élevée qu'une autre
     */
    public function isHigherThan(UserPermission $other): bool
    {
        return $this->niveau > $other->niveau;
    }

    /**
     * Récupère un résumé des permissions pour affichage
     */
    public function getPermissionsSummary(): string
    {
        if (empty($this->permissions)) {
            return 'Aucune permission';
        }
        
        if (count($this->permissions) <= 3) {
            return implode(', ', $this->permissions);
        }
        
        return implode(', ', array_slice($this->permissions, 0, 3)) . ' +' . (count($this->permissions) - 3);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s dans %s (niveau %d)',
            $this->user?->getFullName() ?? 'Utilisateur inconnu',
            $this->societe?->getNom() ?? 'Société inconnue',
            $this->niveau
        );
    }
}