<?php

namespace App\Entity;

use App\Repository\ContactLivraisonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactLivraisonRepository::class)]
class ContactLivraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $identiqueFacturation = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $civilite = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $fonction = null;

    #[ORM\Column(length: 25, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[1-9](?:[\s\.\-]?\d{2}){4}$/',
        message: 'Le numéro de téléphone doit être au format français valide (ex: 01 23 45 67 89, +33 1 23 45 67 89)'
    )]
    private ?string $telephone = null;

    #[ORM\Column(length: 25, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[6-7](?:[\s\.\-]?\d{2}){4}$/',
        message: 'Le numéro de mobile doit être au format français valide (ex: 06 12 34 56 78, +33 6 12 34 56 78)'
    )]
    private ?string $telephoneMobile = null;

    #[ORM\Column(length: 25, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:(?:\+|00)33[\s\.\-]?(?:\(0\)[\s\.\-]?)?|0)[1-9](?:[\s\.\-]?\d{2}){4}$/',
        message: 'Le numéro de fax doit être au format français valide (ex: 01 23 45 67 89)'
    )]
    private ?string $fax = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide')]
    #[Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères')]
    private ?string $email = null;

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

    public function isIdentiqueFacturation(): ?bool
    {
        return $this->identiqueFacturation;
    }

    public function setIdentiqueFacturation(bool $identiqueFacturation): static
    {
        $this->identiqueFacturation = $identiqueFacturation;
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
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

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): static
    {
        $this->fax = $fax;
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

    public function getNomComplet(): string
    {
        if ($this->identiqueFacturation) {
            return "Identique au contact de facturation";
        }
        return trim(($this->civilite ? $this->civilite . ' ' : '') . $this->prenom . ' ' . $this->nom);
    }

    public function copyFromFacturation(ContactFacturation $contactFacturation): static
    {
        if ($this->identiqueFacturation) {
            $this->civilite = $contactFacturation->getCivilite();
            $this->nom = $contactFacturation->getNom();
            $this->prenom = $contactFacturation->getPrenom();
            $this->fonction = $contactFacturation->getFonction();
            $this->telephone = $contactFacturation->getTelephone();
            $this->telephoneMobile = $contactFacturation->getTelephoneMobile();
            $this->fax = $contactFacturation->getFax();
            $this->email = $contactFacturation->getEmail();
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    /**
     * Nettoie le numéro de téléphone pour les liens tel:
     */
    public function getTelephoneForCall(): ?string
    {
        if (!$this->telephone) {
            return null;
        }
        
        // Supprime tous les caractères non numériques sauf le +
        $clean = preg_replace('/[^\d+]/', '', $this->telephone);
        
        // Si le numéro commence par 0, le remplace par +33
        if (str_starts_with($clean, '0')) {
            $clean = '+33' . substr($clean, 1);
        }
        
        return $clean;
    }

    /**
     * Nettoie le numéro de téléphone mobile pour les liens tel:
     */
    public function getTelephoneMobileForCall(): ?string
    {
        if (!$this->telephoneMobile) {
            return null;
        }
        
        // Supprime tous les caractères non numériques sauf le +
        $clean = preg_replace('/[^\d+]/', '', $this->telephoneMobile);
        
        // Si le numéro commence par 0, le remplace par +33
        if (str_starts_with($clean, '0')) {
            $clean = '+33' . substr($clean, 1);
        }
        
        return $clean;
    }
}