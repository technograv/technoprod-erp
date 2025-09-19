<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'client')]
class Client
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


    #[ORM\ManyToOne(targetEntity: FormeJuridique::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?FormeJuridique $formeJuridique = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'prospect'; // 'prospect' ou 'client'
    
    
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $nomEntreprise = null;

    // Commercial assigné
    #[ORM\ManyToOne(inversedBy: 'clients')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $commercial = null;

    #[ORM\ManyToOne(inversedBy: 'clients')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Secteur $secteur = null;

    // Les anciennes relations OneToOne sont remplacées par les collections définies plus bas

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
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateConversionClient = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $derniereVisite = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $chiffreAffaireAnnuel = null;

    // Collections
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Devis::class)]
    private Collection $devis;
    
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Contact::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $contacts;
    
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Adresse::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $adresses;
    
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ClientLog::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $logs;
    
    // Contacts par défaut pour facturation et livraison
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contactFacturationDefault = null;
    
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contactLivraisonDefault = null;

    public function __construct()
    {
        $this->devis = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->adresses = new ArrayCollection();
        $this->logs = new ArrayCollection();
        $this->tags = new ArrayCollection();
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

    /**
     * Récupère l'adresse de facturation par défaut
     */
    public function getAdresseFacturation(): ?Adresse
    {
        $contact = $this->contactFacturationDefault;
        if ($contact) {
            return $contact->getAdresse();
        }
        return null;
    }

    /**
     * Récupère l'adresse de livraison par défaut
     */
    public function getAdresseLivraison(): ?Adresse
    {
        $contact = $this->contactLivraisonDefault;
        if ($contact) {
            return $contact->getAdresse();
        }
        return null;
    }

    /**
     * Récupère le contact de facturation par défaut
     */
    public function getContactFacturation(): ?Contact
    {
        return $this->contactFacturationDefault;
    }

    /**
     * Récupère le contact de livraison par défaut
     */
    public function getContactLivraison(): ?Contact
    {
        return $this->contactLivraisonDefault;
    }

    public function getContactFacturationDefault(): ?Contact
    {
        return $this->contactFacturationDefault;
    }

    public function setContactFacturationDefault(?Contact $contactFacturationDefault): static
    {
        $this->contactFacturationDefault = $contactFacturationDefault;
        return $this;
    }

    public function getContactLivraisonDefault(): ?Contact
    {
        return $this->contactLivraisonDefault;
    }

    public function setContactLivraisonDefault(?Contact $contactLivraisonDefault): static
    {
        $this->contactLivraisonDefault = $contactLivraisonDefault;
        return $this;
    }

    /**
     * Setter pour formulaire - utilise setAdresseFacturationDefault
     */
    public function setAdresseFacturation(?Adresse $adresse): static
    {
        if ($adresse !== null) {
            $this->setAdresseFacturationDefault($adresse);
        }
        return $this;
    }

    /**
     * Setter pour formulaire - utilise setAdresseLivraisonDefault
     */
    public function setAdresseLivraison(?Adresse $adresse): static
    {
        if ($adresse !== null) {
            $this->setAdresseLivraisonDefault($adresse);
        }
        return $this;
    }

    /**
     * Setter pour formulaire - utilise setContactFacturationDefault
     */
    public function setContactFacturation(?Contact $contact): static
    {
        if ($contact !== null) {
            $this->setContactFacturationDefault($contact);
        }
        return $this;
    }

    /**
     * Setter pour formulaire - utilise setContactLivraisonDefault
     */
    public function setContactLivraison(?Contact $contact): static
    {
        if ($contact !== null) {
            $this->setContactLivraisonDefault($contact);
        }
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

    public function getDerniereVisite(): ?\DateTimeInterface
    {
        return $this->derniereVisite;
    }

    public function setDerniereVisite(?\DateTimeInterface $derniereVisite): static
    {
        $this->derniereVisite = $derniereVisite;
        return $this;
    }

    public function getChiffreAffaireAnnuel(): ?string
    {
        return $this->chiffreAffaireAnnuel;
    }

    public function setChiffreAffaireAnnuel(?string $chiffreAffaireAnnuel): static
    {
        $this->chiffreAffaireAnnuel = $chiffreAffaireAnnuel;
        return $this;
    }


    // Méthodes d'accès aux données via les relations (pour maintenir compatibilité)
    public function getEmail(): ?string
    {
        $contact = $this->getContactFacturationDefault();
        return $contact ? $contact->getEmail() : null;
    }

    public function getTelephone(): ?string
    {
        $contact = $this->getContactFacturationDefault();
        return $contact ? $contact->getTelephone() : null;
    }

    public function getCivilite(): ?string
    {
        $contact = $this->getContactFacturationDefault();
        return $contact ? $contact->getCivilite() : null;
    }

    public function getNom(): ?string
    {
        $contact = $this->getContactFacturationDefault();
        return $contact ? $contact->getNom() : null;
    }

    public function getPrenom(): ?string
    {
        $contact = $this->getContactFacturationDefault();
        return $contact ? $contact->getPrenom() : null;
    }

    public function isActif(): bool
    {
        // Pour maintenir la compatibilité, on considère qu'un client est actif
        // si il a au moins un contact (les contacts n'ont pas de champ actif)
        return $this->contacts->count() > 0;
    }

    public function isActive(): bool
    {
        return $this->isActif();
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
        if ($this->formeJuridique && $this->formeJuridique->isPersonnePhysique()) {
            // Pour une personne physique, utiliser civilité + prénom + nom du contact principal
            $contact = $this->getContactFacturationDefault();
            if ($contact) {
                $civilite = $contact->getCivilite() ? $contact->getCivilite() . ' ' : '';
                $prenom = $contact->getPrenom() ? $contact->getPrenom() . ' ' : '';
                $nom = $contact->getNom() ?: '';
                return trim($civilite . $prenom . $nom);
            }
            // Fallback si pas de contact
            return 'Personne physique sans contact';
        } else {
            // Pour une personne morale, utiliser la dénomination
            return $this->nomEntreprise ?: 'Entreprise sans nom';
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

    public function getFormeJuridique(): ?FormeJuridique
    {
        return $this->formeJuridique;
    }

    public function setFormeJuridique(?FormeJuridique $formeJuridique): static
    {
        $this->formeJuridique = $formeJuridique;
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
            $devis->setClient($this);
        }
        return $this;
    }

    public function removeDevis(Devis $devis): static
    {
        if ($this->devis->removeElement($devis)) {
            if ($devis->getClient() === $this) {
                $devis->setClient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setClient($this);
        }
        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            if ($contact->getClient() === $this) {
                $contact->setClient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Adresse>
     */
    public function getAdresses(): Collection
    {
        return $this->adresses;
    }

    public function addAdresse(Adresse $adresse): static
    {
        if (!$this->adresses->contains($adresse)) {
            $this->adresses->add($adresse);
            $adresse->setClient($this);
        }
        return $this;
    }

    public function removeAdresse(Adresse $adresse): static
    {
        if ($this->adresses->removeElement($adresse)) {
            if ($adresse->getClient() === $this) {
                $adresse->setClient(null);
            }
        }
        return $this;
    }

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'clients')]
    private Collection $tags;

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    /**
     * @return Collection<int, ClientLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(ClientLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setClient($this);
        }
        return $this;
    }

    public function removeLog(ClientLog $log): static
    {
        if ($this->logs->removeElement($log)) {
            if ($log->getClient() === $this) {
                $log->setClient(null);
            }
        }
        return $this;
    }

}