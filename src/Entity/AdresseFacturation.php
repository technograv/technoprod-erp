<?php

namespace App\Entity;

use App\Repository\AdresseFacturationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdresseFacturationRepository::class)]
class AdresseFacturation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Le code postal doit contenir 5 chiffres')]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères')]
    private ?string $ville = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le pays est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères')]
    private ?string $pays = 'France';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
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

    public function setPays(string $pays): static
    {
        $this->pays = $pays;
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

    public function getAdresseComplete(): string
    {
        $adresse = $this->ligne1;
        if ($this->ligne2) $adresse .= "\n" . $this->ligne2;
        if ($this->ligne3) $adresse .= "\n" . $this->ligne3;
        $adresse .= "\n" . $this->codePostal . ' ' . $this->ville;
        if ($this->pays !== 'France') $adresse .= "\n" . $this->pays;
        return $adresse;
    }

    public function copyFrom(AdresseFacturation $source): static
    {
        $this->ligne1 = $source->getLigne1();
        $this->ligne2 = $source->getLigne2();
        $this->ligne3 = $source->getLigne3();
        $this->codePostal = $source->getCodePostal();
        $this->ville = $source->getVille();
        $this->pays = $source->getPays();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Retourne l'adresse formatée pour les APIs de cartographie
     */
    public function getAdresseFormateeCartes(): string
    {
        $adresse = $this->ligne1;
        if ($this->ligne2) $adresse .= ', ' . $this->ligne2;
        if ($this->ligne3) $adresse .= ', ' . $this->ligne3;
        $adresse .= ', ' . $this->codePostal . ' ' . $this->ville;
        if ($this->pays !== 'France') $adresse .= ', ' . $this->pays;
        return $adresse;
    }

    /**
     * Génère l'URL Google Maps pour afficher cette adresse
     */
    public function getGoogleMapsUrl(): string
    {
        $adresse = urlencode($this->getAdresseFormateeCartes());
        return "https://www.google.com/maps/search/?api=1&query={$adresse}";
    }

    /**
     * Génère l'URL Google Maps pour un itinéraire vers cette adresse
     */
    public function getItineraireGoogleMapsUrl(): string
    {
        $destination = urlencode($this->getAdresseFormateeCartes());
        return "https://www.google.com/maps/dir/?api=1&destination={$destination}&travelmode=driving";
    }

    /**
     * Génère l'URL Waze pour navigation vers cette adresse
     */
    public function getWazeUrl(): string
    {
        $adresse = urlencode($this->getAdresseFormateeCartes());
        return "https://waze.com/ul?q={$adresse}&navigate=yes";
    }

    /**
     * Vérifie si l'adresse est complète pour la navigation
     */
    public function isNavigable(): bool
    {
        return !empty($this->ligne1) && !empty($this->codePostal) && !empty($this->ville);
    }
}