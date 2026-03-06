<?php

namespace App\Entity\Production;

use App\Entity\Catalogue\ProduitCatalogue;
use App\Entity\Devis;
use App\Entity\DevisItem;
use App\Repository\Production\FicheProductionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fiche de production
 *
 * Document généré à partir d'un DevisItem validé pour lancer la fabrication.
 * Contient toutes les informations nécessaires à l'atelier :
 * - Nomenclature explosée (liste matières)
 * - Gamme calculée (étapes avec temps)
 * - Configuration produit (options choisies)
 * - Tâches à réaliser
 *
 * Workflow :
 * 1. BROUILLON : Création depuis devis
 * 2. VALIDEE : Validation par responsable production
 * 3. EN_COURS : Fabrication démarrée
 * 4. TERMINEE : Toutes tâches terminées
 * 5. ANNULEE : Annulation (devis annulé, erreur)
 */
#[ORM\Entity(repositoryClass: FicheProductionRepository::class)]
#[ORM\Table(name: 'fiche_production')]
#[ORM\HasLifecycleCallbacks]
class FicheProduction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Numéro de fiche unique
     * Format : FP-YYYY-NNNNN
     * Exemple : FP-2025-00042
     */
    #[ORM\Column(length: 20, unique: true)]
    private string $numero;

    /**
     * Devis source
     */
    #[ORM\ManyToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Devis $devis = null;

    /**
     * Ligne de devis concernée
     */
    #[ORM\ManyToOne(targetEntity: DevisItem::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?DevisItem $devisItem = null;

    /**
     * Produit catalogue à fabriquer
     */
    #[ORM\ManyToOne(targetEntity: ProduitCatalogue::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProduitCatalogue $produitCatalogue = null;

    /**
     * Configuration choisie par le client (JSON)
     *
     * Exemple :
     * {
     *   "largeur": 1200,
     *   "hauteur": 600,
     *   "couleur_led": "BLANC_CHAUD",
     *   "finition": "mat",
     *   "materiau": "PVC_3MM"
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $configuration = [];

    /**
     * Nomenclature explosée (résultat calcul BOM) (JSON)
     *
     * Résultat du service GestionNomenclature::exploserNomenclature()
     *
     * Exemple :
     * [
     *   {
     *     "produit_id": 123,
     *     "designation": "Plaque PVC 3mm 3000x1500",
     *     "quantite": 0.72,
     *     "unite": "m²",
     *     "prix_unitaire": 45.00,
     *     "total": 32.40
     *   },
     *   ...
     * ]
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $nomenclatureExplosee = null;

    /**
     * Gamme calculée (résultat calcul temps) (JSON)
     *
     * Résultat du service CalculTempsProduction::calculerTempsTotal()
     *
     * Exemple :
     * {
     *   "operations": [
     *     {
     *       "code": "OP010",
     *       "libelle": "Impression face avant",
     *       "poste": "IMP-LIYU-Q2",
     *       "temps_calcule": 45,
     *       "debut": 0,
     *       "fin": 45
     *     },
     *     ...
     *   ],
     *   "temps_total": 165,
     *   "temps_parallele": 145
     * }
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $gammeCalculee = null;

    /**
     * Coût de revient calculé (JSON)
     *
     * Résultat du service CalculCoutRevient::calculerProduitCatalogue()
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $coutRevient = null;

    /**
     * Quantité à produire
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $quantite = '1.0000';

    /**
     * Statut de la fiche
     */
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_BROUILLON;

    /**
     * Constantes pour les statuts
     */
    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_VALIDEE = 'VALIDEE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_TERMINEE = 'TERMINEE';
    public const STATUT_ANNULEE = 'ANNULEE';

    /**
     * @var Collection<int, Tache>
     */
    #[ORM\OneToMany(targetEntity: Tache::class, mappedBy: 'ficheProduction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $taches;

    /**
     * Priorité (1=urgent, 5=normal, 10=basse)
     */
    #[ORM\Column]
    private int $priorite = 5;

    /**
     * Date de création
     */
    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    /**
     * Date de validation (passage BROUILLON → VALIDEE)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    /**
     * Utilisateur ayant validé
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $validePar = null;

    /**
     * Date de début réel de fabrication
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    /**
     * Date de fin réelle de fabrication
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    /**
     * Date de livraison prévue
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateLivraisonPrevue = null;

    /**
     * Notes internes
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Path du PDF généré
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->taches = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->genererNumero();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->numero;
    }

    /**
     * Génère un numéro de fiche unique
     */
    private function genererNumero(): void
    {
        $year = date('Y');
        $random = str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $this->numero = sprintf('FP-%s-%s', $year, $random);
    }

    /**
     * Retourne le libellé du statut
     */
    public function getStatutLibelle(): string
    {
        return match($this->statut) {
            self::STATUT_BROUILLON => 'Brouillon',
            self::STATUT_VALIDEE => 'Validée',
            self::STATUT_EN_COURS => 'En cours',
            self::STATUT_TERMINEE => 'Terminée',
            self::STATUT_ANNULEE => 'Annulée',
            default => 'Inconnu'
        };
    }

    /**
     * Retourne la couleur Bootstrap du statut
     */
    public function getStatutCouleur(): string
    {
        return match($this->statut) {
            self::STATUT_BROUILLON => 'secondary',
            self::STATUT_VALIDEE => 'info',
            self::STATUT_EN_COURS => 'warning',
            self::STATUT_TERMINEE => 'success',
            self::STATUT_ANNULEE => 'danger',
            default => 'dark'
        };
    }

    /**
     * Valide la fiche (passage BROUILLON → VALIDEE)
     */
    public function valider(string $username): static
    {
        $this->statut = self::STATUT_VALIDEE;
        $this->dateValidation = new \DateTimeImmutable();
        $this->validePar = $username;
        return $this;
    }

    /**
     * Démarre la fabrication
     */
    public function demarrer(): static
    {
        $this->statut = self::STATUT_EN_COURS;
        if (!$this->dateDebut) {
            $this->dateDebut = new \DateTimeImmutable();
        }
        return $this;
    }

    /**
     * Termine la fabrication
     */
    public function terminer(): static
    {
        $this->statut = self::STATUT_TERMINEE;
        $this->dateFin = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Annule la fiche
     */
    public function annuler(): static
    {
        $this->statut = self::STATUT_ANNULEE;
        return $this;
    }

    /**
     * Calcule le taux d'avancement (%)
     */
    public function getTauxAvancement(): float
    {
        if ($this->taches->isEmpty()) {
            return 0;
        }

        $total = $this->taches->count();
        $terminees = 0;

        foreach ($this->taches as $tache) {
            if ($tache->getStatut() === Tache::STATUT_TERMINEE) {
                $terminees++;
            }
        }

        return round(($terminees / $total) * 100, 1);
    }

    /**
     * Calcule le temps total prévu (minutes)
     */
    public function getTempsTotalPrevu(): int
    {
        if ($this->gammeCalculee && isset($this->gammeCalculee['temps_total'])) {
            return $this->gammeCalculee['temps_total'];
        }

        $total = 0;
        foreach ($this->taches as $tache) {
            $total += $tache->getTempsPrevuMinutes();
        }

        return $total;
    }

    /**
     * Calcule le temps total réel (minutes)
     */
    public function getTempsTotalReel(): int
    {
        $total = 0;
        foreach ($this->taches as $tache) {
            if ($tache->getTempsReelMinutes()) {
                $total += $tache->getTempsReelMinutes();
            }
        }

        return $total;
    }

    /**
     * Calcule le coût total des matières
     */
    public function getCoutMatieres(): float
    {
        if ($this->coutRevient && isset($this->coutRevient['total_matieres'])) {
            return $this->coutRevient['total_matieres'];
        }

        return 0;
    }

    /**
     * Calcule le coût total de production
     */
    public function getCoutProduction(): float
    {
        if ($this->coutRevient && isset($this->coutRevient['total_production'])) {
            return $this->coutRevient['total_production'];
        }

        return 0;
    }

    /**
     * Vérifie si la fiche a un PDF généré
     */
    public function hasPdf(): bool
    {
        return !empty($this->pdfPath) && file_exists($this->pdfPath);
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;
        return $this;
    }

    public function getDevisItem(): ?DevisItem
    {
        return $this->devisItem;
    }

    public function setDevisItem(?DevisItem $devisItem): static
    {
        $this->devisItem = $devisItem;
        return $this;
    }

    public function getProduitCatalogue(): ?ProduitCatalogue
    {
        return $this->produitCatalogue;
    }

    public function setProduitCatalogue(?ProduitCatalogue $produitCatalogue): static
    {
        $this->produitCatalogue = $produitCatalogue;
        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): static
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getNomenclatureExplosee(): ?array
    {
        return $this->nomenclatureExplosee;
    }

    public function setNomenclatureExplosee(?array $nomenclatureExplosee): static
    {
        $this->nomenclatureExplosee = $nomenclatureExplosee;
        return $this;
    }

    public function getGammeCalculee(): ?array
    {
        return $this->gammeCalculee;
    }

    public function setGammeCalculee(?array $gammeCalculee): static
    {
        $this->gammeCalculee = $gammeCalculee;
        return $this;
    }

    public function getCoutRevient(): ?array
    {
        return $this->coutRevient;
    }

    public function setCoutRevient(?array $coutRevient): static
    {
        $this->coutRevient = $coutRevient;
        return $this;
    }

    public function getQuantite(): ?string
    {
        return $this->quantite;
    }

    public function setQuantite(string $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    /**
     * @return Collection<int, Tache>
     */
    public function getTaches(): Collection
    {
        return $this->taches;
    }

    public function addTach(Tache $tach): static
    {
        if (!$this->taches->contains($tach)) {
            $this->taches->add($tach);
            $tach->setFicheProduction($this);
        }

        return $this;
    }

    public function removeTach(Tache $tach): static
    {
        if ($this->taches->removeElement($tach)) {
            if ($tach->getFicheProduction() === $this) {
                $tach->setFicheProduction(null);
            }
        }

        return $this;
    }

    public function getPriorite(): ?int
    {
        return $this->priorite;
    }

    public function setPriorite(int $priorite): static
    {
        $this->priorite = $priorite;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function getValidePar(): ?string
    {
        return $this->validePar;
    }

    public function setValidePar(?string $validePar): static
    {
        $this->validePar = $validePar;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getDateLivraisonPrevue(): ?\DateTimeImmutable
    {
        return $this->dateLivraisonPrevue;
    }

    public function setDateLivraisonPrevue(?\DateTimeImmutable $dateLivraisonPrevue): static
    {
        $this->dateLivraisonPrevue = $dateLivraisonPrevue;
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

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;
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
