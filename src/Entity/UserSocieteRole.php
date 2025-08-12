<?php

namespace App\Entity;

use App\Repository\UserSocieteRoleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSocieteRoleRepository::class)]
#[ORM\Table(name: 'user_societe_role')]
#[ORM\UniqueConstraint(name: 'user_societe_unique', columns: ['user_id', 'societe_id'])]
class UserSocieteRole
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_COMMERCIAL = 'commercial';
    public const ROLE_COMPTABLE = 'comptable';
    public const ROLE_OPERATEUR = 'operateur';
    public const ROLE_LECTURE = 'lecture';

    public const ROLES_DISPONIBLES = [
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_MANAGER => 'Manager', 
        self::ROLE_COMMERCIAL => 'Commercial',
        self::ROLE_COMPTABLE => 'Comptable',
        self::ROLE_OPERATEUR => 'Opérateur',
        self::ROLE_LECTURE => 'Lecture seule'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'societeRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Societe::class, inversedBy: 'userRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private Societe $societe;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: [
        self::ROLE_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_COMMERCIAL,
        self::ROLE_COMPTABLE,
        self::ROLE_OPERATEUR,
        self::ROLE_LECTURE
    ], message: 'Rôle non valide')]
    private string $role;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $permissionsSpecifiques = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->permissionsSpecifiques = [];
    }

    // Getters et Setters
    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        $this->updateTimestamp();
        return $this;
    }

    public function getSociete(): Societe
    {
        return $this->societe;
    }

    public function setSociete(Societe $societe): self
    {
        $this->societe = $societe;
        $this->updateTimestamp();
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
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

    public function getPermissionsSpecifiques(): ?array
    {
        return $this->permissionsSpecifiques;
    }

    public function setPermissionsSpecifiques(?array $permissionsSpecifiques): self
    {
        $this->permissionsSpecifiques = $permissionsSpecifiques ?? [];
        $this->updateTimestamp();
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        $this->updateTimestamp();
        return $this;
    }

    // Méthodes utilitaires
    private function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getRoleLibelle(): string
    {
        return self::ROLES_DISPONIBLES[$this->role] ?? $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    public function hasPermission(string $permission): bool
    {
        // Permissions par rôle
        $rolePermissions = [
            self::ROLE_ADMIN => ['*'], // Toutes permissions
            self::ROLE_MANAGER => ['create', 'read', 'update', 'delete', 'manage_users'],
            self::ROLE_COMMERCIAL => ['create', 'read', 'update', 'manage_clients', 'manage_devis'],
            self::ROLE_COMPTABLE => ['read', 'update', 'manage_comptabilite', 'manage_factures'],
            self::ROLE_OPERATEUR => ['create', 'read', 'update'],
            self::ROLE_LECTURE => ['read']
        ];

        $permissions = $rolePermissions[$this->role] ?? [];
        
        // Vérifier permission globale
        if (in_array('*', $permissions)) {
            return true;
        }

        // Vérifier permission spécifique
        if (in_array($permission, $permissions)) {
            return true;
        }

        // Vérifier permissions spécifiques personnalisées
        if ($this->permissionsSpecifiques && in_array($permission, $this->permissionsSpecifiques)) {
            return true;
        }

        return false;
    }

    public function addPermissionSpecifique(string $permission): self
    {
        if (!in_array($permission, $this->permissionsSpecifiques ?? [])) {
            $permissions = $this->permissionsSpecifiques ?? [];
            $permissions[] = $permission;
            $this->setPermissionsSpecifiques($permissions);
        }
        return $this;
    }

    public function removePermissionSpecifique(string $permission): self
    {
        if ($this->permissionsSpecifiques) {
            $permissions = array_filter($this->permissionsSpecifiques, fn($p) => $p !== $permission);
            $this->setPermissionsSpecifiques(array_values($permissions));
        }
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', 
            $this->user->getNomComplet(), 
            $this->societe->getDisplayName(), 
            $this->getRoleLibelle()
        );
    }
}