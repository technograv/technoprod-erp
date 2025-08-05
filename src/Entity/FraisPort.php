<?php

namespace App\Entity;

use App\Repository\FraisPortRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FraisPortRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FraisPort
{
    public const MODE_MONTANT_FIXE = 'montant_fixe';
    public const MODE_POURCENTAGE_HT = 'pourcentage_ht';
    public const MODE_PALIER_MONTANT_HT = 'palier_montant_ht';
    public const MODE_PALIER_QUANTITE = 'palier_quantite';
    public const MODE_PALIER_POIDS = 'palier_poids';
    public const MODE_PALIER_COLIS = 'palier_colis';
    public const MODE_PALIER_VOLUME = 'palier_volume';

    public const MODES_CALCUL = [
        self::MODE_MONTANT_FIXE => 'Montant fixe',
        self::MODE_POURCENTAGE_HT => '% du montant HT',
        self::MODE_PALIER_MONTANT_HT => 'Palier sur montant HT facturé',
        self::MODE_PALIER_QUANTITE => 'Palier sur quantité totale facturée',
        self::MODE_PALIER_POIDS => 'Palier sur poids total brut',
        self::MODE_PALIER_COLIS => 'Palier sur nombre total de colis',
        self::MODE_PALIER_VOLUME => 'Palier sur volume total'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $modeCalcul = self::MODE_MONTANT_FIXE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $valeur = null; // Valeur pour montant fixe ou pourcentage

    #[ORM\ManyToOne(targetEntity: TauxTVA::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?TauxTVA $tauxTva = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Transporteur $transporteur = null;

    #[ORM\OneToMany(targetEntity: PalierFraisPort::class, mappedBy: 'fraisPort', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['limiteJusqua' => 'ASC'])]
    private Collection $paliers;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private int $ordre = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->paliers = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
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

    public function getModeCalcul(): ?string
    {
        return $this->modeCalcul;
    }

    public function setModeCalcul(string $modeCalcul): static
    {
        $this->modeCalcul = $modeCalcul;
        return $this;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(?string $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getTauxTva(): ?TauxTVA
    {
        return $this->tauxTva;
    }

    public function setTauxTva(?TauxTVA $tauxTva): static
    {
        $this->tauxTva = $tauxTva;
        return $this;
    }

    public function getTransporteur(): ?Transporteur
    {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): static
    {
        $this->transporteur = $transporteur;
        return $this;
    }

    /**
     * @return Collection<int, PalierFraisPort>
     */
    public function getPaliers(): Collection
    {
        return $this->paliers;
    }

    public function addPalier(PalierFraisPort $palier): static
    {
        if (!$this->paliers->contains($palier)) {
            $this->paliers->add($palier);
            $palier->setFraisPort($this);
        }

        return $this;
    }

    public function removePalier(PalierFraisPort $palier): static
    {
        if ($this->paliers->removeElement($palier)) {
            if ($palier->getFraisPort() === $this) {
                $palier->setFraisPort(null);
            }
        }

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
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

    public function __toString(): string
    {
        return $this->nom ?: '';
    }

    /**
     * Retourne le libellé du mode de calcul
     */
    public function getModeCalculLibelle(): string
    {
        return self::MODES_CALCUL[$this->modeCalcul] ?? 'Mode inconnu';
    }

    /**
     * Vérifie si ce mode de calcul utilise des paliers
     */
    public function utiliserPaliers(): bool
    {
        return in_array($this->modeCalcul, [
            self::MODE_PALIER_MONTANT_HT,
            self::MODE_PALIER_QUANTITE,
            self::MODE_PALIER_POIDS,
            self::MODE_PALIER_COLIS,
            self::MODE_PALIER_VOLUME
        ]);
    }

    /**
     * Calcule les frais de port selon le mode de calcul
     * $critere = montant HT, quantité, poids, nb colis, ou volume selon le mode
     */
    public function calculerFrais(float $critere): float
    {
        switch ($this->modeCalcul) {
            case self::MODE_MONTANT_FIXE:
                return (float) $this->valeur;
                
            case self::MODE_POURCENTAGE_HT:
                return $critere * ((float) $this->valeur / 100);
                
            case self::MODE_PALIER_MONTANT_HT:
            case self::MODE_PALIER_QUANTITE:
            case self::MODE_PALIER_POIDS:
            case self::MODE_PALIER_COLIS:
            case self::MODE_PALIER_VOLUME:
                return $this->calculerFraisPalier($critere);
                
            default:
                return 0.0;
        }
    }

    /**
     * Calcule les frais selon les paliers
     */
    private function calculerFraisPalier(float $critere): float
    {
        $palierApplicable = null;
        
        foreach ($this->paliers as $palier) {
            if ($critere <= $palier->getLimiteJusqua()) {
                $palierApplicable = $palier;
                break;
            }
        }
        
        // Si aucun palier trouvé, prendre le dernier (limite la plus haute)
        if (!$palierApplicable && !$this->paliers->isEmpty()) {
            $palierApplicable = $this->paliers->last();
        }
        
        return $palierApplicable ? (float) $palierApplicable->getValeur() : 0.0;
    }
}
