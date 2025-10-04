<?php

namespace App\Entity;

use App\Repository\FournisseurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FournisseurRepository::class)]
#[ORM\Table(name: 'fournisseur')]
class Fournisseur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Le code fournisseur est obligatoire')]
    private ?string $code = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire')]
    private ?string $raisonSociale = null;

    #[ORM\ManyToOne(targetEntity: FormeJuridique::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?FormeJuridique $formeJuridique = null;

    // Identifiants légaux
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroTVA = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeNAF = null;

    // Coordonnées principales
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWeb = null;

    // Gestion commerciale
    #[ORM\ManyToOne(targetEntity: ModeReglement::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ModeReglement $modeReglement = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $conditionsPaiement = null; // Ex: "30 jours fin de mois"

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $remiseGenerale = '0.00'; // Remise globale sur tous les achats

    // Statut
    #[ORM\Column(length: 20)]
    private string $statut = 'actif'; // 'actif', 'inactif', 'bloqué'

    // Notes
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesInternes = null;

    // Métadonnées
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Collections
    #[ORM\OneToMany(mappedBy: 'fournisseur', targetEntity: Contact::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $contacts;

    #[ORM\OneToMany(mappedBy: 'fournisseur', targetEntity: Adresse::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $adresses;

    #[ORM\OneToMany(mappedBy: 'fournisseur', targetEntity: ProduitFournisseur::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $produitsFournis;

    #[ORM\OneToMany(mappedBy: 'fournisseurPrincipal', targetEntity: Produit::class)]
    private Collection $produitsCommeDefaut;

    // Contacts par défaut
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contactFacturationDefault = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Contact $contactLivraisonDefault = null;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->adresses = new ArrayCollection();
        $this->produitsFournis = new ArrayCollection();
        $this->produitsCommeDefaut = new ArrayCollection();
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

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(string $raisonSociale): static
    {
        $this->raisonSociale = $raisonSociale;
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

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = $siren;
        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;
        return $this;
    }

    public function getNumeroTVA(): ?string
    {
        return $this->numeroTVA;
    }

    public function setNumeroTVA(?string $numeroTVA): static
    {
        $this->numeroTVA = $numeroTVA;
        return $this;
    }

    public function getCodeNAF(): ?string
    {
        return $this->codeNAF;
    }

    public function setCodeNAF(?string $codeNAF): static
    {
        $this->codeNAF = $codeNAF;
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

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): static
    {
        $this->siteWeb = $siteWeb;
        return $this;
    }

    public function getModeReglement(): ?ModeReglement
    {
        return $this->modeReglement;
    }

    public function setModeReglement(?ModeReglement $modeReglement): static
    {
        $this->modeReglement = $modeReglement;
        return $this;
    }

    public function getConditionsPaiement(): ?string
    {
        return $this->conditionsPaiement;
    }

    public function setConditionsPaiement(?string $conditionsPaiement): static
    {
        $this->conditionsPaiement = $conditionsPaiement;
        return $this;
    }

    public function getRemiseGenerale(): ?string
    {
        return $this->remiseGenerale;
    }

    public function setRemiseGenerale(?string $remiseGenerale): static
    {
        $this->remiseGenerale = $remiseGenerale;
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

    public function getNotesInternes(): ?string
    {
        return $this->notesInternes;
    }

    public function setNotesInternes(?string $notesInternes): static
    {
        $this->notesInternes = $notesInternes;
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
            $contact->setFournisseur($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            if ($contact->getFournisseur() === $this) {
                $contact->setFournisseur(null);
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

    public function addAdress(Adresse $adress): static
    {
        if (!$this->adresses->contains($adress)) {
            $this->adresses->add($adress);
            $adress->setFournisseur($this);
        }

        return $this;
    }

    public function removeAdress(Adresse $adress): static
    {
        if ($this->adresses->removeElement($adress)) {
            if ($adress->getFournisseur() === $this) {
                $adress->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProduitFournisseur>
     */
    public function getProduitsFournis(): Collection
    {
        return $this->produitsFournis;
    }

    public function addProduitsFourni(ProduitFournisseur $produitsFourni): static
    {
        if (!$this->produitsFournis->contains($produitsFourni)) {
            $this->produitsFournis->add($produitsFourni);
            $produitsFourni->setFournisseur($this);
        }

        return $this;
    }

    public function removeProduitsFourni(ProduitFournisseur $produitsFourni): static
    {
        if ($this->produitsFournis->removeElement($produitsFourni)) {
            if ($produitsFourni->getFournisseur() === $this) {
                $produitsFourni->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Produit>
     */
    public function getProduitsCommeDefaut(): Collection
    {
        return $this->produitsCommeDefaut;
    }

    public function addProduitsCommeDefaut(Produit $produitsCommeDefaut): static
    {
        if (!$this->produitsCommeDefaut->contains($produitsCommeDefaut)) {
            $this->produitsCommeDefaut->add($produitsCommeDefaut);
            $produitsCommeDefaut->setFournisseurPrincipal($this);
        }

        return $this;
    }

    public function removeProduitsCommeDefaut(Produit $produitsCommeDefaut): static
    {
        if ($this->produitsCommeDefaut->removeElement($produitsCommeDefaut)) {
            if ($produitsCommeDefaut->getFournisseurPrincipal() === $this) {
                $produitsCommeDefaut->setFournisseurPrincipal(null);
            }
        }

        return $this;
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

    public function __toString(): string
    {
        return $this->raisonSociale ?? 'Fournisseur #' . $this->id;
    }
}
