<?php

namespace App\Entity;

use App\Repository\ProspectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProspectRepository::class)]
class Prospect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Informations principales
    #[ORM\Column(length: 20, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $famille = null;

    #[ORM\Column(length: 20)]
    private string $typePersonne = 'morale'; // 'physique' ou 'morale'

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $civilite = null; // M., Mme, Mlle

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le nom/raison sociale est obligatoire')]
    #[Assert\Length(max: 200, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $nom = null; // Nom de l'entreprise ou nom de famille

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $formeJuridique = null; // SAS, SARL, EURL, etc.

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null; // Uniquement pour personne physique

    #[ORM\Column(length: 20)]
    private string $statut = 'prospect'; // 'prospect' ou 'client'
    
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide')]
    private ?string $email = null;
    
    #[ORM\Column(length: 25, nullable: true)]
    private ?string $telephone = null;
    
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $nomEntreprise = null;

    // Commercial assigné
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $commercial = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Secteur $secteur = null;

    // Adresses
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?AdresseFacturation $adresseFacturation = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?AdresseLivraison $adresseLivraison = null;

    // Contacts
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?ContactFacturation $contactFacturation = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?ContactLivraison $contactLivraison = null;

    // TODO: Collections pour adresses et contacts supplémentaires à implémenter plus tard

    // Gestion commerciale
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $regimeComptable = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(nullable: true)]
    private ?int $delaiPaiement = 30; // en jours

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $tauxTva = '20.00';

    #[ORM\Column(nullable: true)]
    private ?bool $assujettiTva = true;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $conditionsTarifs = null;

    // Notes et fichiers
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    // Métadonnées
    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateConversionClient = null;

    // TODO: Relations avec les documents commerciaux à implémenter plus tard

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getFamille(): ?string
    {
        return $this->famille;
    }

    public function setFamille(?string $famille): static
    {
        $this->famille = $famille;
        return $this;
    }

    public function getTypePersonne(): string
    {
        return $this->typePersonne;
    }

    public function setTypePersonne(string $typePersonne): static
    {
        $this->typePersonne = $typePersonne;
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

    public function setNom(string $nom): static
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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
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

    public function getSecteur(): ?Secteur
    {
        return $this->secteur;
    }

    public function setSecteur(?Secteur $secteur): static
    {
        $this->secteur = $secteur;
        return $this;
    }

    public function getAdresseFacturation(): ?AdresseFacturation
    {
        return $this->adresseFacturation;
    }

    public function setAdresseFacturation(?AdresseFacturation $adresseFacturation): static
    {
        $this->adresseFacturation = $adresseFacturation;
        return $this;
    }

    public function getAdresseLivraison(): ?AdresseLivraison
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?AdresseLivraison $adresseLivraison): static
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getContactFacturation(): ?ContactFacturation
    {
        return $this->contactFacturation;
    }

    public function setContactFacturation(?ContactFacturation $contactFacturation): static
    {
        $this->contactFacturation = $contactFacturation;
        return $this;
    }

    public function getContactLivraison(): ?ContactLivraison
    {
        return $this->contactLivraison;
    }

    public function setContactLivraison(?ContactLivraison $contactLivraison): static
    {
        $this->contactLivraison = $contactLivraison;
        return $this;
    }

    public function getRegimeComptable(): ?string
    {
        return $this->regimeComptable;
    }

    public function setRegimeComptable(?string $regimeComptable): static
    {
        $this->regimeComptable = $regimeComptable;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getDelaiPaiement(): ?int
    {
        return $this->delaiPaiement;
    }

    public function setDelaiPaiement(?int $delaiPaiement): static
    {
        $this->delaiPaiement = $delaiPaiement;
        return $this;
    }

    public function getTauxTva(): ?string
    {
        return $this->tauxTva;
    }

    public function setTauxTva(?string $tauxTva): static
    {
        $this->tauxTva = $tauxTva;
        return $this;
    }

    public function isAssujettiTva(): ?bool
    {
        return $this->assujettiTva;
    }

    public function setAssujettiTva(?bool $assujettiTva): static
    {
        $this->assujettiTva = $assujettiTva;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getDateConversionClient(): ?\DateTimeImmutable
    {
        return $this->dateConversionClient;
    }

    public function setDateConversionClient(?\DateTimeImmutable $dateConversionClient): static
    {
        $this->dateConversionClient = $dateConversionClient;
        return $this;
    }


    // Méthodes utilitaires
    public function isProspect(): bool
    {
        return $this->statut === 'prospect';
    }

    public function isClient(): bool
    {
        return $this->statut === 'client';
    }

    public function convertToClient(): static
    {
        $this->statut = 'client';
        $this->dateConversionClient = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getNomComplet(): string
    {
        if ($this->typePersonne === 'physique') {
            return trim(($this->civilite ? $this->civilite . ' ' : '') . $this->prenom . ' ' . $this->nom);
        } else {
            return $this->nom;
        }
    }

    public function generateCode(): string
    {
        $prefix = $this->isProspect() ? 'PROS' : 'CLI';
        return $prefix . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getConditionsTarifs(): ?string
    {
        return $this->conditionsTarifs;
    }

    public function setConditionsTarifs(?string $conditionsTarifs): static
    {
        $this->conditionsTarifs = $conditionsTarifs;
        return $this;
    }

    public function getFormeJuridique(): ?string
    {
        return $this->formeJuridique;
    }

    public function setFormeJuridique(?string $formeJuridique): static
    {
        $this->formeJuridique = $formeJuridique;
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

    public function getNomEntreprise(): ?string
    {
        return $this->nomEntreprise;
    }

    public function setNomEntreprise(?string $nomEntreprise): static
    {
        $this->nomEntreprise = $nomEntreprise;
        return $this;
    }
}