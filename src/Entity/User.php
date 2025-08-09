<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleAccessToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleRefreshToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private ?bool $isGoogleAccount = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $gmailSignature = null;

    #[ORM\OneToMany(mappedBy: 'commercial', targetEntity: Client::class)]
    private Collection $clients;

    #[ORM\OneToMany(mappedBy: 'commercial', targetEntity: Secteur::class)]
    private Collection $secteurs;

    #[ORM\OneToMany(mappedBy: 'commercial', targetEntity: Devis::class)]
    private Collection $devis;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserSocieteRole::class, orphanRemoval: true)]
    private Collection $societeRoles;

    #[ORM\ManyToOne(targetEntity: Societe::class, fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Societe $societePrincipale = null;

    #[ORM\ManyToMany(targetEntity: GroupeUtilisateur::class, inversedBy: 'utilisateurs')]
    #[ORM\JoinTable(name: 'user_groupe_utilisateur')]
    private Collection $groupes;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserPermission::class)]
    private Collection $permissions;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->secteurs = new ArrayCollection();
        $this->devis = new ArrayCollection();
        $this->societeRoles = new ArrayCollection();
        $this->groupes = new ArrayCollection();
        $this->permissions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): static
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
            $client->setCommercial($this);
        }
        return $this;
    }

    public function removeClient(Client $client): static
    {
        if ($this->clients->removeElement($client)) {
            if ($client->getCommercial() === $this) {
                $client->setCommercial(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Secteur>
     */
    public function getSecteurs(): Collection
    {
        return $this->secteurs;
    }

    public function addSecteur(Secteur $secteur): static
    {
        if (!$this->secteurs->contains($secteur)) {
            $this->secteurs->add($secteur);
            $secteur->setCommercial($this);
        }
        return $this;
    }

    public function removeSecteur(Secteur $secteur): static
    {
        if ($this->secteurs->removeElement($secteur)) {
            if ($secteur->getCommercial() === $this) {
                $secteur->setCommercial(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Devis>
     */
    public function getDevis(): Collection
    {
        return $this->devis;
    }

    public function addDevis(Devis $devis): static
    {
        if (!$this->devis->contains($devis)) {
            $this->devis->add($devis);
            $devis->setCommercial($this);
        }
        return $this;
    }

    public function removeDevis(Devis $devis): static
    {
        if ($this->devis->removeElement($devis)) {
            if ($devis->getCommercial() === $this) {
                $devis->setCommercial(null);
            }
        }
        return $this;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getGoogleAccessToken(): ?string
    {
        return $this->googleAccessToken;
    }

    public function setGoogleAccessToken(?string $googleAccessToken): static
    {
        $this->googleAccessToken = $googleAccessToken;
        return $this;
    }

    public function getGoogleRefreshToken(): ?string
    {
        return $this->googleRefreshToken;
    }

    public function setGoogleRefreshToken(?string $googleRefreshToken): static
    {
        $this->googleRefreshToken = $googleRefreshToken;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function isGoogleAccount(): ?bool
    {
        return $this->isGoogleAccount;
    }

    public function setIsGoogleAccount(bool $isGoogleAccount): static
    {
        $this->isGoogleAccount = $isGoogleAccount;
        return $this;
    }

    public function getGmailSignature(): ?string
    {
        return $this->gmailSignature;
    }

    public function setGmailSignature(?string $gmailSignature): static
    {
        $this->gmailSignature = $gmailSignature;
        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->email === 'nicolas.michel@decorpub.fr';
    }

    public function isAllowedDomain(): bool
    {
        $allowedDomains = [
            'decorpub.fr',
            'technograv.fr', 
            'pimpanelo.fr',
            'technoburo.fr',
            'pimpanelo.com'
        ];

        $emailDomain = substr(strrchr($this->email, "@"), 1);
        return in_array($emailDomain, $allowedDomains);
    }

    // Méthodes pour la gestion des rôles de sociétés
    /**
     * @return Collection<int, UserSocieteRole>
     */
    public function getSocieteRoles(): Collection
    {
        return $this->societeRoles;
    }

    public function addSocieteRole(UserSocieteRole $societeRole): self
    {
        if (!$this->societeRoles->contains($societeRole)) {
            $this->societeRoles->add($societeRole);
            $societeRole->setUser($this);
        }
        return $this;
    }

    public function removeSocieteRole(UserSocieteRole $societeRole): self
    {
        if ($this->societeRoles->removeElement($societeRole)) {
            if ($societeRole->getUser() === $this) {
                $societeRole->setUser(null);
            }
        }
        return $this;
    }

    /**
     * Vérifie si l'utilisateur a accès à une société donnée
     */
    public function hasAccessToSociete(Societe $societe): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($this->societeRoles as $role) {
            if ($role->getSociete() === $societe && $role->isActive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Récupère le rôle de l'utilisateur dans une société
     */
    public function getRoleInSociete(Societe $societe): ?UserSocieteRole
    {
        foreach ($this->societeRoles as $role) {
            if ($role->getSociete() === $societe && $role->isActive()) {
                return $role;
            }
        }
        return null;
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique dans une société (tous les systèmes combinés)
     */
    public function hasPermissionInSociete(Societe $societe, string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // 1. Vérifier permission individuelle
        $userPermission = $this->getPermissionsForSociete($societe);
        if ($userPermission && $userPermission->hasPermission($permission)) {
            return true;
        }

        // 2. Vérifier permissions des groupes
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif() && $groupe->hasAccessToSociete($societe)) {
                if ($groupe->hasPermissionRecursive($permission)) {
                    return true;
                }
            }
        }

        // 3. Vérifier rôle direct dans la société (pour compatibilité)
        $role = $this->getRoleInSociete($societe);
        return $role ? $role->hasPermission($permission) : false;
    }

    /**
     * Récupère toutes les sociétés accessibles par l'utilisateur
     */
    public function getAccessibleSocietes(): array
    {
        if ($this->isSuperAdmin()) {
            // Le super admin a accès à toutes les sociétés
            // Cette méthode devra être complétée par un service
            return [];
        }

        $societes = [];
        foreach ($this->societeRoles as $role) {
            if ($role->isActive()) {
                $societes[] = $role->getSociete();
            }
        }
        return $societes;
    }

    public function getSocietePrincipale(): ?Societe
    {
        return $this->societePrincipale;
    }

    public function setSocietePrincipale(?Societe $societePrincipale): static
    {
        $this->societePrincipale = $societePrincipale;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }


    /**
     * Retourne la société principale ou la première accessible si non définie
     */
    public function getEffectiveSocietePrincipale(): ?Societe
    {
        // Si une société principale est définie et que l'utilisateur y a accès
        if ($this->societePrincipale && $this->hasAccessToSociete($this->societePrincipale)) {
            return $this->societePrincipale;
        }

        // Sinon, retourner la première société accessible
        $accessibles = $this->getAccessibleSocietes();
        return !empty($accessibles) ? $accessibles[0] : null;
    }

    /**
     * @return Collection<int, GroupeUtilisateur>
     */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    public function addGroupe(GroupeUtilisateur $groupe): static
    {
        if (!$this->groupes->contains($groupe)) {
            $this->groupes->add($groupe);
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function removeGroupe(GroupeUtilisateur $groupe): static
    {
        if ($this->groupes->removeElement($groupe)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    /**
     * Récupère toutes les permissions de l'utilisateur via ses groupes
     */
    public function getAllPermissionsFromGroupes(): array
    {
        $permissions = [];
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif()) {
                $permissions = array_merge($permissions, $groupe->getAllPermissions());
            }
        }
        return array_unique($permissions);
    }

    /**
     * Vérifie si l'utilisateur a une permission via ses groupes
     */
    public function hasPermissionFromGroupes(string $permission): bool
    {
        return in_array($permission, $this->getAllPermissionsFromGroupes(), true);
    }

    /**
     * Récupère toutes les sociétés accessibles via les groupes
     */
    public function getAccessibleSocietesFromGroupes(): array
    {
        $societes = [];
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif()) {
                $societes = array_merge($societes, $groupe->getAllSocietes());
            }
        }
        
        // Supprimer les doublons basés sur l'ID
        $uniqueSocietes = [];
        foreach ($societes as $societe) {
            $uniqueSocietes[$societe->getId()] = $societe;
        }
        
        return array_values($uniqueSocietes);
    }

    /**
     * Obtient le niveau maximum des groupes de l'utilisateur
     */
    public function getNiveauMaximumGroupes(): int
    {
        $niveauMax = 0;
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif() && $groupe->getNiveau() > $niveauMax) {
                $niveauMax = $groupe->getNiveau();
            }
        }
        return $niveauMax;
    }

    /**
     * Vérifie si l'utilisateur appartient à un groupe spécifique
     */
    public function belongsToGroupe(GroupeUtilisateur $groupe): bool
    {
        return $this->groupes->contains($groupe);
    }

    /**
     * Récupère les noms des groupes de l'utilisateur (pour affichage)
     */
    public function getNomsGroupes(): array
    {
        $noms = [];
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif()) {
                $noms[] = $groupe->getNom();
            }
        }
        return $noms;
    }

    // ===== GESTION DES PERMISSIONS INDIVIDUELLES =====

    /**
     * @return Collection<int, UserPermission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(UserPermission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->setUser($this);
        }
        return $this;
    }

    public function removePermission(UserPermission $permission): static
    {
        if ($this->permissions->removeElement($permission)) {
            if ($permission->getUser() === $this) {
                $permission->setUser(null);
            }
        }
        return $this;
    }

    /**
     * Récupère les permissions individuelles pour une société
     */
    public function getPermissionsForSociete(Societe $societe): ?UserPermission
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getSociete() === $societe && $permission->isActif()) {
                return $permission;
            }
        }
        return null;
    }


    /**
     * Récupère toutes les permissions de l'utilisateur dans une société (individuel + groupes)
     */
    public function getAllPermissionsInSociete(Societe $societe): array
    {
        $allPermissions = [];

        // Permissions individuelles
        $userPermission = $this->getPermissionsForSociete($societe);
        if ($userPermission) {
            $allPermissions = array_merge($allPermissions, $userPermission->getPermissions());
        }

        // Permissions des groupes
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif() && $groupe->hasAccessToSociete($societe)) {
                $allPermissions = array_merge($allPermissions, $groupe->getAllPermissions());
            }
        }

        return array_unique($allPermissions);
    }

    /**
     * Récupère le niveau maximum de l'utilisateur dans une société (individuel + groupes)
     */
    public function getMaxLevelInSociete(Societe $societe): int
    {
        $maxLevel = 0;

        // Niveau permission individuelle
        $userPermission = $this->getPermissionsForSociete($societe);
        if ($userPermission) {
            $maxLevel = max($maxLevel, $userPermission->getNiveau());
        }

        // Niveau des groupes
        foreach ($this->groupes as $groupe) {
            if ($groupe->isActif() && $groupe->hasAccessToSociete($societe)) {
                $maxLevel = max($maxLevel, $groupe->getNiveau());
            }
        }

        return $maxLevel;
    }

    /**
     * Vérifie si l'utilisateur a un niveau minimum dans une société
     */
    public function hasMinimumLevelInSociete(int $requiredLevel, Societe $societe): bool
    {
        return $this->getMaxLevelInSociete($societe) >= $requiredLevel;
    }
}
