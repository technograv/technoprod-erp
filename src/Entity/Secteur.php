<?php

namespace App\Entity;

use App\Repository\SecteurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Secteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nomSecteur = null;

    #[ORM\ManyToOne(inversedBy: 'secteurs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $commercial = null;

    // Nouveau système : Type de secteur (comment il est défini)
    #[ORM\ManyToOne(inversedBy: 'secteurs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TypeSecteur $typeSecteur = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $couleurHex = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Nouveau système : Attributions basées sur les divisions administratives
    #[ORM\OneToMany(targetEntity: AttributionSecteur::class, mappedBy: 'secteur', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributions;

    // Relations avec les clients
    #[ORM\OneToMany(mappedBy: 'secteur', targetEntity: Client::class)]
    private Collection $clients;

    public function __construct()
    {
        $this->attributions = new ArrayCollection();
        $this->clients = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getNomSecteur(): ?string
    {
        return $this->nomSecteur;
    }

    public function setNomSecteur(string $nomSecteur): static
    {
        $this->nomSecteur = $nomSecteur;
        return $this;
    }

    public function getCommercial(): ?User
    {
        return $this->commercial;
    }

    public function setCommercial(?User $commercial): static
    {
        $this->commercial = $commercial;
        return $this;
    }

    public function getCouleurHex(): ?string
    {
        return $this->couleurHex;
    }

    public function setCouleurHex(?string $couleurHex): static
    {
        $this->couleurHex = $couleurHex;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function getIsActive(): ?bool
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
            $client->setSecteur($this);
        }
        return $this;
    }

    public function removeClient(Client $client): static
    {
        if ($this->clients->removeElement($client)) {
            if ($client->getSecteur() === $this) {
                $client->setSecteur(null);
            }
        }
        return $this;
    }


    // ===== NOUVEAUX GETTERS/SETTERS POUR LE SYSTÈME DE DIVISIONS ADMINISTRATIVES =====

    public function getTypeSecteur(): ?TypeSecteur
    {
        return $this->typeSecteur;
    }

    public function setTypeSecteur(?TypeSecteur $typeSecteur): static
    {
        $this->typeSecteur = $typeSecteur;
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

    /**
     * @return Collection<int, AttributionSecteur>
     */
    public function getAttributions(): Collection
    {
        return $this->attributions;
    }

    public function addAttribution(AttributionSecteur $attribution): static
    {
        if (!$this->attributions->contains($attribution)) {
            $this->attributions->add($attribution);
            $attribution->setSecteur($this);
        }

        return $this;
    }

    public function removeAttribution(AttributionSecteur $attribution): static
    {
        if ($this->attributions->removeElement($attribution)) {
            if ($attribution->getSecteur() === $this) {
                $attribution->setSecteur(null);
            }
        }

        return $this;
    }

    // ===== MÉTHODES MÉTIER POUR LE NOUVEAU SYSTÈME =====

    /**
     * Vérifie si ce secteur couvre une division administrative donnée
     */
    public function couvre(DivisionAdministrative $division): bool
    {
        foreach ($this->attributions as $attribution) {
            if ($attribution->couvre($division)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si ce secteur couvre un code postal donné
     */
    public function couvreCodePostal(string $codePostal): bool
    {
        foreach ($this->attributions as $attribution) {
            $division = $attribution->getDivisionAdministrative();
            if ($division && $division->getCodePostal() === $codePostal) {
                return true;
            }
            
            // Si attribution par département/région, vérifier si le code postal en fait partie
            if ($attribution->getTypeCritere() === TypeSecteur::TYPE_DEPARTEMENT) {
                $dept = substr($codePostal, 0, 2);
                if ($attribution->getValeurCritere() === $dept) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Retourne toutes les divisions administratives couvertes par ce secteur
     */
    public function getDivisionsCouvertes(): Collection
    {
        $divisions = new ArrayCollection();
        
        foreach ($this->attributions as $attribution) {
            $division = $attribution->getDivisionAdministrative();
            if ($division && !$divisions->contains($division)) {
                $divisions->add($division);
            }
        }
        
        return $divisions;
    }

    /**
     * Retourne un résumé textuel du secteur (codes postaux, départements, etc.)
     */
    public function getResumeTerritoire(): string
    {
        if ($this->attributions->isEmpty()) {
            return 'Aucune attribution';
        }

        $groupes = [];
        
        foreach ($this->attributions as $attribution) {
            $type = $attribution->getTypeCritere();
            $valeur = (string) $attribution;
            
            if (!isset($groupes[$type])) {
                $groupes[$type] = [];
            }
            $groupes[$type][] = $valeur;
        }

        $resume = [];
        foreach ($groupes as $type => $valeurs) {
            $typeLibelle = TypeSecteur::TYPES_DISPONIBLES[$type] ?? $type;
            $resume[] = $typeLibelle . ': ' . implode(', ', array_slice($valeurs, 0, 3)) . 
                       (count($valeurs) > 3 ? ' (+' . (count($valeurs) - 3) . ' autres)' : '');
        }

        return implode(' | ', $resume);
    }

    /**
     * Retourne le nombre total de divisions administratives couvertes
     */
    public function getNombreDivisionsCouvertes(): int
    {
        return $this->attributions->count();
    }

    /**
     * Ajoute une attribution basée sur une division administrative
     */
    public function ajouterAttribution(DivisionAdministrative $division, string $typeCritere, ?string $notes = null): static
    {
        $attribution = AttributionSecteur::creerDepuisDivision($this, $division, $typeCritere);
        if ($notes) {
            $attribution->setNotes($notes);
        }
        
        $this->addAttribution($attribution);
        
        return $this;
    }

    /**
     * Supprime toutes les attributions d'un type donné
     */
    public function supprimerAttributionsParType(string $typeCritere): static
    {
        $attributionsASupprimer = [];
        
        foreach ($this->attributions as $attribution) {
            if ($attribution->getTypeCritere() === $typeCritere) {
                $attributionsASupprimer[] = $attribution;
            }
        }
        
        foreach ($attributionsASupprimer as $attribution) {
            $this->removeAttribution($attribution);
        }
        
        return $this;
    }

    /**
     * Vérifie si ce secteur utilise le nouveau système de divisions administratives
     */
    public function utiliseNouveauSysteme(): bool
    {
        return !$this->attributions->isEmpty();
    }

    /**
     * Vérifie si ce secteur utilise encore l'ancien système de zones (toujours false maintenant)
     */
    public function utiliseAncienSysteme(): bool
    {
        return false; // L'ancien système de zones a été supprimé
    }


    /**
     * Propriété virtuelle pour Twig - nombre de divisions couvertes
     */
    public function __get($name)
    {
        if ($name === 'nombreDivisionsCouvertes') {
            return $this->getNombreDivisionsCouvertes();
        }
        throw new \InvalidArgumentException("Propriété '$name' non trouvée");
    }


    public function __toString(): string
    {
        return $this->nomSecteur ?: '';
    }
}