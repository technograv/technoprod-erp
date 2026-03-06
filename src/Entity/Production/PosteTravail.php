<?php

namespace App\Entity\Production;

use App\Repository\Production\PosteTravailRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Poste de travail / Machine / Ressource de production
 *
 * Représente une machine, un poste manuel ou une ressource humaine
 * utilisée dans le processus de fabrication.
 *
 * Exemples :
 * - Imprimante LIYU Q2 (machine)
 * - Fraiseuse CNC Verso (machine)
 * - Poste de montage électronique (poste manuel)
 * - Graphiste (ressource humaine)
 * - Équipe de pose chantier (ressource humaine)
 */
#[ORM\Entity(repositoryClass: PosteTravailRepository::class)]
#[ORM\Table(name: 'poste_travail')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Ce code de poste existe déjà.')]
class PosteTravail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: CategoriePoste::class, inversedBy: 'postes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CategoriePoste $categorie = null;

    /**
     * Coût horaire du poste (€/heure)
     * Inclut : amortissement machine, énergie, maintenance
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $coutHoraire = '0.00';

    /**
     * Temps de setup/préparation (minutes)
     * Exemple : 45 min pour allumer et calibrer l'imprimante LIYU
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $tempsSetup = 0;

    /**
     * Temps de nettoyage après utilisation (minutes)
     * Exemple : 30 min pour éteindre et nettoyer l'imprimante
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $tempsNettoyage = 0;

    /**
     * Capacité maximale journalière
     * Production maximale par jour dans l'unité spécifiée
     * Exemple : 50 m²/jour, 200 pièces/jour, 100 ml/jour
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $capaciteJournaliere = null;

    /**
     * Unité de mesure de la capacité
     * Exemple : m² (mètres carrés), u (unités), ml (mètres linéaires)
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\Unite::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?\App\Entity\Unite $uniteCapacite = null;

    /**
     * Nécessite la présence d'un opérateur
     */
    #[ORM\Column]
    private bool $necessiteOperateur = true;

    /**
     * Polyvalent : peut effectuer plusieurs types d'opérations
     * Exemple : CNC peut découper, évider, rainurer, tarauder
     */
    #[ORM\Column]
    private bool $polyvalent = false;

    /**
     * Spécifications techniques (JSON)
     * Exemples :
     * - {"laize": 2050, "vitesse_max": "50m²/h"} pour imprimante
     * - {"plateau": "4000x2000", "precision": "0.1mm"} pour CNC
     * - {"capacite_poids": 200} pour table de pose
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $specifications = null;

    /**
     * Consommables associés (JSON)
     * Exemple : {"encre": "UV CMJN", "tetes": 4}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $consommables = null;

    #[ORM\Column]
    private bool $actif = true;

    /**
     * @var Collection<int, GammeOperation>
     */
    #[ORM\OneToMany(targetEntity: GammeOperation::class, mappedBy: 'posteTravail')]
    private Collection $operations;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->operations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->libelle;
    }

    /**
     * Calcule le coût total incluant setup et nettoyage
     *
     * @param int $tempsProductionMinutes Temps de production effectif
     * @return float Coût total en euros
     */
    public function calculerCoutTotal(int $tempsProductionMinutes): float
    {
        $tempsTotal = $this->tempsSetup + $tempsProductionMinutes + $this->tempsNettoyage;
        $heures = $tempsTotal / 60;
        return round($heures * (float)$this->coutHoraire, 2);
    }

    /**
     * Vérifie si le poste a une spécification donnée
     */
    public function hasSpecification(string $key): bool
    {
        return isset($this->specifications[$key]);
    }

    /**
     * Récupère une spécification
     */
    public function getSpecification(string $key): mixed
    {
        return $this->specifications[$key] ?? null;
    }

    /**
     * Définit une spécification
     */
    public function setSpecification(string $key, mixed $value): static
    {
        $specs = $this->specifications ?? [];
        $specs[$key] = $value;
        $this->specifications = $specs;
        return $this;
    }

    // Getters & Setters

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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategorie(): ?CategoriePoste
    {
        return $this->categorie;
    }

    public function setCategorie(?CategoriePoste $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getCoutHoraire(): ?string
    {
        return $this->coutHoraire;
    }

    public function setCoutHoraire(string $coutHoraire): static
    {
        $this->coutHoraire = $coutHoraire;
        return $this;
    }

    public function getTempsSetup(): ?int
    {
        return $this->tempsSetup;
    }

    public function setTempsSetup(int $tempsSetup): static
    {
        $this->tempsSetup = $tempsSetup;
        return $this;
    }

    public function getTempsNettoyage(): ?int
    {
        return $this->tempsNettoyage;
    }

    public function setTempsNettoyage(int $tempsNettoyage): static
    {
        $this->tempsNettoyage = $tempsNettoyage;
        return $this;
    }

    public function getCapaciteJournaliere(): ?string
    {
        return $this->capaciteJournaliere;
    }

    public function setCapaciteJournaliere(?string $capaciteJournaliere): static
    {
        $this->capaciteJournaliere = $capaciteJournaliere;
        return $this;
    }

    public function getUniteCapacite(): ?\App\Entity\Unite
    {
        return $this->uniteCapacite;
    }

    public function setUniteCapacite(?\App\Entity\Unite $uniteCapacite): static
    {
        $this->uniteCapacite = $uniteCapacite;
        return $this;
    }

    public function isNecessiteOperateur(): ?bool
    {
        return $this->necessiteOperateur;
    }

    public function setNecessiteOperateur(bool $necessiteOperateur): static
    {
        $this->necessiteOperateur = $necessiteOperateur;
        return $this;
    }

    public function isPolyvalent(): ?bool
    {
        return $this->polyvalent;
    }

    public function setPolyvalent(bool $polyvalent): static
    {
        $this->polyvalent = $polyvalent;
        return $this;
    }

    public function getSpecifications(): ?array
    {
        return $this->specifications;
    }

    public function setSpecifications(?array $specifications): static
    {
        $this->specifications = $specifications;
        return $this;
    }

    public function getConsommables(): ?array
    {
        return $this->consommables;
    }

    public function setConsommables(?array $consommables): static
    {
        $this->consommables = $consommables;
        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    /**
     * @return Collection<int, GammeOperation>
     */
    public function getOperations(): Collection
    {
        return $this->operations;
    }

    public function addOperation(GammeOperation $operation): static
    {
        if (!$this->operations->contains($operation)) {
            $this->operations->add($operation);
            $operation->setPosteTravail($this);
        }

        return $this;
    }

    public function removeOperation(GammeOperation $operation): static
    {
        if ($this->operations->removeElement($operation)) {
            if ($operation->getPosteTravail() === $this) {
                $operation->setPosteTravail(null);
            }
        }

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
}
