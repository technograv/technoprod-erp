<?php

namespace App\Entity\Production;

use App\Entity\User;
use App\Repository\Production\TacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tâche de production
 *
 * Représente une étape de travail à réaliser dans l'atelier.
 * Générée automatiquement depuis une GammeOperation lors de la création
 * de la FicheProduction.
 *
 * Permet le suivi temps réel de la production :
 * - Assignation opérateur
 * - Statut avancement (A_FAIRE, EN_COURS, TERMINEE, BLOQUEE)
 * - Temps prévu vs temps réel
 * - Commentaires opérateur
 *
 * Workflow :
 * 1. A_FAIRE : Tâche créée, pas encore démarrée
 * 2. EN_COURS : Opérateur a démarré la tâche
 * 3. TERMINEE : Tâche terminée par l'opérateur
 * 4. BLOQUEE : Problème rencontré, production stoppée
 */
#[ORM\Entity(repositoryClass: TacheRepository::class)]
#[ORM\Table(name: 'tache')]
#[ORM\HasLifecycleCallbacks]
class Tache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FicheProduction::class, inversedBy: 'taches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FicheProduction $ficheProduction = null;

    /**
     * Opération de gamme source
     * Permet de remonter aux instructions, paramètres machine, etc.
     */
    #[ORM\ManyToOne(targetEntity: GammeOperation::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?GammeOperation $gammeOperation = null;

    /**
     * Ordre d'exécution
     */
    #[ORM\Column]
    private int $ordre = 0;

    /**
     * Code de la tâche
     * Repris de GammeOperation
     */
    #[ORM\Column(length: 50)]
    private string $code;

    /**
     * Libellé de la tâche
     */
    #[ORM\Column(length: 255)]
    private string $libelle;

    /**
     * Poste de travail / Machine
     */
    #[ORM\ManyToOne(targetEntity: PosteTravail::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PosteTravail $posteTravail = null;

    /**
     * Temps prévu (minutes)
     * Calculé par le service CalculTempsProduction
     */
    #[ORM\Column]
    private int $tempsPrevuMinutes = 0;

    /**
     * Temps réel (minutes)
     * Saisi par l'opérateur à la fin de la tâche
     */
    #[ORM\Column(nullable: true)]
    private ?int $tempsReelMinutes = null;

    /**
     * Statut de la tâche
     */
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_A_FAIRE;

    /**
     * Constantes pour les statuts
     */
    public const STATUT_A_FAIRE = 'A_FAIRE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_TERMINEE = 'TERMINEE';
    public const STATUT_BLOQUEE = 'BLOQUEE';

    /**
     * Opérateur assigné
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $operateurAssigne = null;

    /**
     * Date de début
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    /**
     * Date de fin
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    /**
     * Instructions pour l'opérateur
     * Reprises de GammeOperation ou personnalisées
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    /**
     * Paramètres machine (JSON)
     * Repris de GammeOperation
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametresMachine = null;

    /**
     * Commentaire de l'opérateur
     * Rempli à la fin de la tâche
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaireOperateur = null;

    /**
     * Motif de blocage (si statut = BLOQUEE)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifBlocage = null;

    /**
     * Contrôle qualité requis
     */
    #[ORM\Column]
    private bool $controleQualite = false;

    /**
     * Contrôle qualité effectué
     */
    #[ORM\Column]
    private bool $controleEffectue = false;

    /**
     * Résultat contrôle qualité
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $resultatControle = null;

    /**
     * Constantes pour résultat contrôle
     */
    public const CONTROLE_OK = 'OK';
    public const CONTROLE_MINEUR = 'MINEUR';
    public const CONTROLE_MAJEUR = 'MAJEUR';
    public const CONTROLE_REJET = 'REJET';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
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
     * Retourne le libellé du statut
     */
    public function getStatutLibelle(): string
    {
        return match($this->statut) {
            self::STATUT_A_FAIRE => 'À faire',
            self::STATUT_EN_COURS => 'En cours',
            self::STATUT_TERMINEE => 'Terminée',
            self::STATUT_BLOQUEE => 'Bloquée',
            default => 'Inconnu'
        };
    }

    /**
     * Retourne la couleur Bootstrap du statut
     */
    public function getStatutCouleur(): string
    {
        return match($this->statut) {
            self::STATUT_A_FAIRE => 'secondary',
            self::STATUT_EN_COURS => 'warning',
            self::STATUT_TERMINEE => 'success',
            self::STATUT_BLOQUEE => 'danger',
            default => 'dark'
        };
    }

    /**
     * Démarre la tâche
     */
    public function demarrer(?User $operateur = null): static
    {
        $this->statut = self::STATUT_EN_COURS;
        $this->dateDebut = new \DateTimeImmutable();

        if ($operateur) {
            $this->operateurAssigne = $operateur;
        }

        return $this;
    }

    /**
     * Termine la tâche
     */
    public function terminer(?int $tempsReel = null, ?string $commentaire = null): static
    {
        $this->statut = self::STATUT_TERMINEE;
        $this->dateFin = new \DateTimeImmutable();

        if ($tempsReel !== null) {
            $this->tempsReelMinutes = $tempsReel;
        } elseif ($this->dateDebut) {
            // Calcul automatique depuis dateDebut
            $diff = $this->dateFin->getTimestamp() - $this->dateDebut->getTimestamp();
            $this->tempsReelMinutes = (int)($diff / 60);
        }

        if ($commentaire) {
            $this->commentaireOperateur = $commentaire;
        }

        return $this;
    }

    /**
     * Bloque la tâche
     */
    public function bloquer(string $motif): static
    {
        $this->statut = self::STATUT_BLOQUEE;
        $this->motifBlocage = $motif;
        return $this;
    }

    /**
     * Débloque la tâche
     */
    public function debloquer(): static
    {
        $this->statut = self::STATUT_A_FAIRE;
        $this->motifBlocage = null;
        return $this;
    }

    /**
     * Calcule l'écart temps prévu / réel (%)
     */
    public function getEcartTemps(): ?float
    {
        if (!$this->tempsReelMinutes || $this->tempsPrevuMinutes === 0) {
            return null;
        }

        $ecart = (($this->tempsReelMinutes - $this->tempsPrevuMinutes) / $this->tempsPrevuMinutes) * 100;
        return round($ecart, 1);
    }

    /**
     * Vérifie si la tâche est en retard
     */
    public function isEnRetard(): bool
    {
        if ($this->tempsReelMinutes === null) {
            return false;
        }

        return $this->tempsReelMinutes > $this->tempsPrevuMinutes;
    }

    /**
     * Effectue le contrôle qualité
     */
    public function effectuerControle(string $resultat): static
    {
        $this->controleEffectue = true;
        $this->resultatControle = $resultat;
        return $this;
    }

    /**
     * Vérifie si le contrôle est OK
     */
    public function isControleOk(): bool
    {
        return $this->controleEffectue && $this->resultatControle === self::CONTROLE_OK;
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFicheProduction(): ?FicheProduction
    {
        return $this->ficheProduction;
    }

    public function setFicheProduction(?FicheProduction $ficheProduction): static
    {
        $this->ficheProduction = $ficheProduction;
        return $this;
    }

    public function getGammeOperation(): ?GammeOperation
    {
        return $this->gammeOperation;
    }

    public function setGammeOperation(?GammeOperation $gammeOperation): static
    {
        $this->gammeOperation = $gammeOperation;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
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

    public function getPosteTravail(): ?PosteTravail
    {
        return $this->posteTravail;
    }

    public function setPosteTravail(?PosteTravail $posteTravail): static
    {
        $this->posteTravail = $posteTravail;
        return $this;
    }

    public function getTempsPrevuMinutes(): ?int
    {
        return $this->tempsPrevuMinutes;
    }

    public function setTempsPrevuMinutes(int $tempsPrevuMinutes): static
    {
        $this->tempsPrevuMinutes = $tempsPrevuMinutes;
        return $this;
    }

    public function getTempsReelMinutes(): ?int
    {
        return $this->tempsReelMinutes;
    }

    public function setTempsReelMinutes(?int $tempsReelMinutes): static
    {
        $this->tempsReelMinutes = $tempsReelMinutes;
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

    public function getOperateurAssigne(): ?User
    {
        return $this->operateurAssigne;
    }

    public function setOperateurAssigne(?User $operateurAssigne): static
    {
        $this->operateurAssigne = $operateurAssigne;
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

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): static
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function getParametresMachine(): ?array
    {
        return $this->parametresMachine;
    }

    public function setParametresMachine(?array $parametresMachine): static
    {
        $this->parametresMachine = $parametresMachine;
        return $this;
    }

    public function getCommentaireOperateur(): ?string
    {
        return $this->commentaireOperateur;
    }

    public function setCommentaireOperateur(?string $commentaireOperateur): static
    {
        $this->commentaireOperateur = $commentaireOperateur;
        return $this;
    }

    public function getMotifBlocage(): ?string
    {
        return $this->motifBlocage;
    }

    public function setMotifBlocage(?string $motifBlocage): static
    {
        $this->motifBlocage = $motifBlocage;
        return $this;
    }

    public function isControleQualite(): ?bool
    {
        return $this->controleQualite;
    }

    public function setControleQualite(bool $controleQualite): static
    {
        $this->controleQualite = $controleQualite;
        return $this;
    }

    public function isControleEffectue(): ?bool
    {
        return $this->controleEffectue;
    }

    public function setControleEffectue(bool $controleEffectue): static
    {
        $this->controleEffectue = $controleEffectue;
        return $this;
    }

    public function getResultatControle(): ?string
    {
        return $this->resultatControle;
    }

    public function setResultatControle(?string $resultatControle): static
    {
        $this->resultatControle = $resultatControle;
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
