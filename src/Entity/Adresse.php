<?php

namespace App\Entity;

use App\Repository\AdresseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdresseRepository::class)]
class Adresse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom de l\'adresse est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom de l\'adresse ne peut pas dépasser {{ limit }} caractères')]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'adresses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'L\'adresse ligne 1 est obligatoire')]
    #[Assert\Length(max: 200, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères')]
    private ?string $ligne1 = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères')]
    private ?string $ligne2 = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères')]
    private ?string $ligne3 = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire')]
    #[Assert\Regex(
        pattern: '/^\d{5}$/',
        message: 'Le code postal doit contenir exactement 5 chiffres'
    )]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères')]
    private ?string $ville = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères')]
    private ?string $pays = 'France';


    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getLigne1(): ?string
    {
        return $this->ligne1;
    }

    public function setLigne1(string $ligne1): static
    {
        $this->ligne1 = $ligne1;
        return $this;
    }

    public function getLigne2(): ?string
    {
        return $this->ligne2;
    }

    public function setLigne2(?string $ligne2): static
    {
        $this->ligne2 = $ligne2;
        return $this;
    }

    public function getLigne3(): ?string
    {
        return $this->ligne3;
    }

    public function setLigne3(?string $ligne3): static
    {
        $this->ligne3 = $ligne3;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Adresse complète sur une ligne
     */
    public function getAdresseComplete(): string
    {
        $parts = array_filter([
            $this->ligne1,
            $this->ligne2,
            $this->ligne3,
            $this->codePostal . ' ' . $this->ville,
            $this->pays !== 'France' ? $this->pays : null
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Adresse courte (ligne1 + ville)
     */
    public function getAdresseCourte(): string
    {
        return $this->ligne1 . ', ' . $this->ville;
    }

    /**
     * Vérifie si l'adresse est navigable (a les informations minimales)
     */
    public function isNavigable(): bool
    {
        return !empty($this->ligne1) && !empty($this->ville) && !empty($this->codePostal);
    }

    /**
     * URL Google Maps pour itinéraire
     */
    public function getItineraireGoogleMapsUrl(): ?string
    {
        if (!$this->isNavigable()) {
            return null;
        }
        
        $adresse = urlencode($this->getAdresseComplete());
        return "https://maps.google.com/maps?q={$adresse}";
    }

    /**
     * URL Waze pour navigation
     */
    public function getWazeUrl(): ?string
    {
        if (!$this->isNavigable()) {
            return null;
        }
        
        $adresse = urlencode($this->getAdresseComplete());
        return "https://waze.com/ul?q={$adresse}";
    }

    /**
     * URL Google Maps pour visualisation
     */
    public function getGoogleMapsUrl(): ?string
    {
        if (!$this->isNavigable()) {
            return null;
        }
        
        $adresse = urlencode($this->getAdresseComplete());
        return "https://maps.google.com/maps?q={$adresse}";
    }

    public function __toString(): string
    {
        return $this->getAdresseCourte();
    }
}