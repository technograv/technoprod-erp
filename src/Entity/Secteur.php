<?php

namespace App\Entity;

use App\Repository\SecteurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecteurRepository::class)]
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

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $couleurHex = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'secteur', targetEntity: SecteurZone::class, orphanRemoval: true)]
    private Collection $secteurZones;

    #[ORM\OneToMany(mappedBy: 'secteur', targetEntity: Client::class)]
    private Collection $clients;

    #[ORM\ManyToMany(targetEntity: Zone::class, inversedBy: 'secteurs')]
    #[ORM\JoinTable(name: 'secteur_zone_new')]
    private Collection $zones;

    public function __construct()
    {
        $this->secteurZones = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->zones = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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
     * @return Collection<int, SecteurZone>
     */
    public function getSecteurZones(): Collection
    {
        return $this->secteurZones;
    }

    public function addSecteurZone(SecteurZone $secteurZone): static
    {
        if (!$this->secteurZones->contains($secteurZone)) {
            $this->secteurZones->add($secteurZone);
            $secteurZone->setSecteur($this);
        }
        return $this;
    }

    public function removeSecteurZone(SecteurZone $secteurZone): static
    {
        if ($this->secteurZones->removeElement($secteurZone)) {
            if ($secteurZone->getSecteur() === $this) {
                $secteurZone->setSecteur(null);
            }
        }
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

    /**
     * @return Collection<int, Zone>
     */
    public function getZones(): Collection
    {
        return $this->zones;
    }

    public function addZone(Zone $zone): static
    {
        if (!$this->zones->contains($zone)) {
            $this->zones->add($zone);
        }
        return $this;
    }

    public function removeZone(Zone $zone): static
    {
        $this->zones->removeElement($zone);
        return $this;
    }
}