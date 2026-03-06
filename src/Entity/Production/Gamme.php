<?php

namespace App\Entity\Production;

use App\Repository\Production\GammeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Gamme de fabrication (Routing / Manufacturing Process)
 *
 * Définit la séquence d'opérations nécessaires pour fabriquer un produit :
 * - Liste ordonnée des étapes de production
 * - Machines/postes utilisés
 * - Temps de fabrication (formules dynamiques)
 * - Instructions opératoires
 *
 * Exemples :
 * - Enseigne LED : Impression → Découpe → Montage LED → Câblage → Test
 * - Panneau PVC : Impression → Lamination → Découpe CNC → Ébavurage
 * - Lettre découpée : Fraisage recto → Fraisage verso → Ébavurage → Pose LED
 */
#[ORM\Entity(repositoryClass: GammeRepository::class)]
#[ORM\Table(name: 'gamme')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Ce code de gamme existe déjà.')]
class Gamme
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

    /**
     * Version de la gamme
     */
    #[ORM\Column(length: 20)]
    private string $version = '1.0';

    /**
     * @var Collection<int, GammeOperation>
     */
    #[ORM\OneToMany(targetEntity: GammeOperation::class, mappedBy: 'gamme', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $operations;

    /**
     * Statut de la gamme
     */
    #[ORM\Column(length: 20)]
    private string $statut = 'BROUILLON';

    /**
     * Constantes pour les statuts
     */
    public const STATUT_BROUILLON = 'BROUILLON';
    public const STATUT_VALIDEE = 'VALIDEE';
    public const STATUT_OBSOLETE = 'OBSOLETE';

    /**
     * Date de validation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    /**
     * Utilisateur ayant validé
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $validePar = null;

    /**
     * Notes techniques internes
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Temps total théorique (calculé automatiquement, en minutes)
     * Mis à jour lors de la validation
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $tempsTotalTheorique = null;

    #[ORM\Column]
    private bool $actif = true;

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
        return $this->code . ' - ' . $this->libelle;
    }

    /**
     * Vérifie si la gamme est validée
     */
    public function isValidee(): bool
    {
        return $this->statut === self::STATUT_VALIDEE;
    }

    /**
     * Valide la gamme
     */
    public function valider(string $username): static
    {
        $this->statut = self::STATUT_VALIDEE;
        $this->dateValidation = new \DateTimeImmutable();
        $this->validePar = $username;
        return $this;
    }

    /**
     * Marque comme obsolète
     */
    public function rendreObsolete(): static
    {
        $this->statut = self::STATUT_OBSOLETE;
        $this->actif = false;
        return $this;
    }

    /**
     * Compte le nombre d'opérations
     */
    public function compterOperations(): int
    {
        return $this->operations->count();
    }

    /**
     * Compte le nombre d'opérations parallèles
     */
    public function compterOperationsParalleles(): int
    {
        $count = 0;
        foreach ($this->operations as $operation) {
            if ($operation->isTempsParallele()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Retourne la liste des postes utilisés
     *
     * @return PosteTravail[]
     */
    public function getPostesUtilises(): array
    {
        $postes = [];
        foreach ($this->operations as $operation) {
            $poste = $operation->getPosteTravail();
            if ($poste && !in_array($poste, $postes, true)) {
                $postes[] = $poste;
            }
        }
        return $postes;
    }

    /**
     * Recalcule le temps total théorique (sans formules)
     * Utilise uniquement les temps fixes
     */
    public function recalculerTempsTotalTheorique(): static
    {
        $total = 0;
        foreach ($this->operations as $operation) {
            if ($operation->getTypeTemps() === GammeOperation::TYPE_TEMPS_FIXE) {
                $total += $operation->getTempsFixe();
            }
        }
        $this->tempsTotalTheorique = $total;
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

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;
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
            $operation->setGamme($this);
        }

        return $this;
    }

    public function removeOperation(GammeOperation $operation): static
    {
        if ($this->operations->removeElement($operation)) {
            if ($operation->getGamme() === $this) {
                $operation->setGamme(null);
            }
        }

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getTempsTotalTheorique(): ?int
    {
        return $this->tempsTotalTheorique;
    }

    public function setTempsTotalTheorique(?int $tempsTotalTheorique): static
    {
        $this->tempsTotalTheorique = $tempsTotalTheorique;
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
