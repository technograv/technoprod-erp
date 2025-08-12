<?php

namespace App\Entity;

use App\Repository\CommuneFrancaiseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommuneFrancaiseRepository::class)]
#[ORM\Index(name: 'idx_code_postal', columns: ['code_postal'])]
#[ORM\Index(name: 'idx_nom_commune', columns: ['nom_commune'])]
class CommuneFrancaise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'code_postal', length: 5)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Le code postal doit contenir 5 chiffres')]
    private ?string $codePostal = null;

    #[ORM\Column(name: 'nom_commune', length: 100)]
    #[Assert\NotBlank]
    private ?string $nomCommune = null;

    #[ORM\Column(name: 'code_departement', length: 50, nullable: true)]
    private ?string $codeDepartement = null;

    #[ORM\Column(name: 'nom_departement', length: 100, nullable: true)]
    private ?string $nomDepartement = null;

    #[ORM\Column(name: 'code_region', length: 50, nullable: true)]
    private ?string $codeRegion = null;

    #[ORM\Column(name: 'nom_region', length: 100, nullable: true)]
    private ?string $nomRegion = null;

    #[ORM\Column(nullable: true)]
    private ?int $population = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNomCommune(): ?string
    {
        return $this->nomCommune;
    }

    public function setNomCommune(string $nomCommune): static
    {
        $this->nomCommune = $nomCommune;
        return $this;
    }

    public function getCodeDepartement(): ?string
    {
        return $this->codeDepartement;
    }

    public function setCodeDepartement(?string $codeDepartement): static
    {
        $this->codeDepartement = $codeDepartement;
        return $this;
    }

    public function getNomDepartement(): ?string
    {
        return $this->nomDepartement;
    }

    public function setNomDepartement(?string $nomDepartement): static
    {
        $this->nomDepartement = $nomDepartement;
        return $this;
    }

    public function getCodeRegion(): ?string
    {
        return $this->codeRegion;
    }

    public function setCodeRegion(?string $codeRegion): static
    {
        $this->codeRegion = $codeRegion;
        return $this;
    }

    public function getNomRegion(): ?string
    {
        return $this->nomRegion;
    }

    public function setNomRegion(?string $nomRegion): static
    {
        $this->nomRegion = $nomRegion;
        return $this;
    }

    public function getPopulation(): ?int
    {
        return $this->population;
    }

    public function setPopulation(?int $population): static
    {
        $this->population = $population;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function __toString(): string
    {
        return $this->codePostal . ' ' . $this->nomCommune;
    }

    /**
     * Retourne le nom complet avec département
     */
    public function getNomComplet(): string
    {
        $nom = $this->nomCommune;
        if ($this->nomDepartement) {
            $nom .= ' (' . $this->nomDepartement . ')';
        }
        return $nom;
    }

    /**
     * Retourne l'adresse complète formatée
     */
    public function getAdresseComplete(): string
    {
        return $this->codePostal . ' ' . $this->nomCommune . ($this->nomDepartement ? ' - ' . $this->nomDepartement : '');
    }
}