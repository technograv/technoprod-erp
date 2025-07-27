<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $fonction = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $telephoneMobile = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $civilite = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $fax = null;

    #[ORM\Column]
    private bool $isFacturationDefault = false;

    #[ORM\Column]
    private bool $isLivraisonDefault = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Adresse::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Adresse $adresse = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
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

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): static
    {
        $this->fonction = $fonction;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getTelephoneMobile(): ?string
    {
        return $this->telephoneMobile;
    }

    public function setTelephoneMobile(?string $telephoneMobile): static
    {
        $this->telephoneMobile = $telephoneMobile;
        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): static
    {
        $this->civilite = $civilite;
        return $this;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): static
    {
        $this->fax = $fax;
        return $this;
    }

    public function isFacturationDefault(): bool
    {
        return $this->isFacturationDefault;
    }

    public function setIsFacturationDefault(bool $isFacturationDefault): static
    {
        // Si on veut désactiver ce contact comme contact de facturation par défaut
        if (!$isFacturationDefault && $this->isFacturationDefault && $this->client) {
            // Vérifier s'il y a d'autres contacts qui peuvent prendre le relais
            $otherFacturationContacts = [];
            foreach ($this->client->getContacts() as $contact) {
                if ($contact !== $this) {
                    $otherFacturationContacts[] = $contact;
                }
            }
            
            // Si c'est le dernier contact ou s'il y a d'autres contacts disponibles
            if (empty($otherFacturationContacts)) {
                throw new \InvalidArgumentException('Impossible de supprimer le dernier contact de facturation. Veuillez d\'abord en assigner un autre.');
            }
        }
        
        // Si on définit ce contact comme contact de facturation par défaut
        if ($isFacturationDefault && $this->client) {
            // Désactiver tous les autres contacts de facturation par défaut pour ce client
            foreach ($this->client->getContacts() as $contact) {
                if ($contact !== $this && $contact->isFacturationDefault()) {
                    $contact->isFacturationDefault = false;
                }
            }
        }
        
        $this->isFacturationDefault = $isFacturationDefault;
        return $this;
    }

    public function isLivraisonDefault(): bool
    {
        return $this->isLivraisonDefault;
    }

    public function setIsLivraisonDefault(bool $isLivraisonDefault): static
    {
        // Si on veut désactiver ce contact comme contact de livraison par défaut
        if (!$isLivraisonDefault && $this->isLivraisonDefault && $this->client) {
            // Vérifier s'il y a d'autres contacts qui peuvent prendre le relais
            $otherLivraisonContacts = [];
            foreach ($this->client->getContacts() as $contact) {
                if ($contact !== $this) {
                    $otherLivraisonContacts[] = $contact;
                }
            }
            
            // Si c'est le dernier contact ou s'il y a d'autres contacts disponibles
            if (empty($otherLivraisonContacts)) {
                throw new \InvalidArgumentException('Impossible de supprimer le dernier contact de livraison. Veuillez d\'abord en assigner un autre.');
            }
        }
        
        // Si on définit ce contact comme contact de livraison par défaut
        if ($isLivraisonDefault && $this->client) {
            // Désactiver tous les autres contacts de livraison par défaut pour ce client
            foreach ($this->client->getContacts() as $contact) {
                if ($contact !== $this && $contact->isLivraisonDefault()) {
                    $contact->isLivraisonDefault = false;
                }
            }
        }
        
        $this->isLivraisonDefault = $isLivraisonDefault;
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

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getNomComplet(): string
    {
        $parts = array_filter([
            $this->civilite,
            $this->prenom,
            $this->nom
        ]);
        
        return implode(' ', $parts);
    }

    /**
     * Téléphone à utiliser pour les appels (mobile prioritaire)
     */
    public function getTelephoneForCall(): ?string
    {
        return $this->telephoneMobile ?: $this->telephone;
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }

    public function getAdresse(): ?Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(?Adresse $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

}