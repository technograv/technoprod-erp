<?php

namespace App\Entity;

use App\Repository\AuditTrailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditTrailRepository::class)]
#[ORM\Table(name: 'audit_trail')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_entity_lookup')]
#[ORM\Index(columns: ['timestamp'], name: 'idx_timestamp')]
#[ORM\Index(columns: ['action'], name: 'idx_action')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user')]
class AuditTrail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // IDENTIFICATION
    #[ORM\Column(length: 100)]
    private string $entityType;

    #[ORM\Column(length: 50)]
    private string $entityId;

    #[ORM\Column(length: 20)]
    private string $action;

    // CHANGEMENTS
    #[ORM\Column(type: Types::JSON)]
    private array $oldValues = [];

    #[ORM\Column(type: Types::JSON)]
    private array $newValues = [];

    #[ORM\Column(type: Types::JSON)]
    private array $changedFields = [];

    // CONTEXTE
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $timestamp;

    #[ORM\Column(length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sessionId = null;

    // JUSTIFICATION
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $justification = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $approvedBy = null;

    // INTÉGRITÉ
    #[ORM\Column(length: 64)]
    private string $recordHash;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousRecordHash = null;

    // MÉTADONNÉES
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    public function __construct()
    {
        $this->timestamp = new \DateTime();
    }

    // GETTERS ET SETTERS

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getOldValues(): array
    {
        return $this->oldValues;
    }

    public function setOldValues(array $oldValues): static
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): array
    {
        return $this->newValues;
    }

    public function setNewValues(array $newValues): static
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    public function setChangedFields(array $changedFields): static
    {
        $this->changedFields = $changedFields;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(?string $justification): static
    {
        $this->justification = $justification;
        return $this;
    }

    public function getApprovedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?string $approvedBy): static
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getRecordHash(): string
    {
        return $this->recordHash;
    }

    public function setRecordHash(string $recordHash): static
    {
        $this->recordHash = $recordHash;
        return $this;
    }

    public function getPreviousRecordHash(): ?string
    {
        return $this->previousRecordHash;
    }

    public function setPreviousRecordHash(?string $previousRecordHash): static
    {
        $this->previousRecordHash = $previousRecordHash;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    // MÉTHODES UTILITAIRES

    /**
     * Ajoute une métadonnée
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Récupère une métadonnée
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Vérifie si l'action nécessite une justification
     */
    public function requiresJustification(): bool
    {
        return in_array($this->action, ['DELETE', 'ADMIN_UPDATE', 'BULK_UPDATE']);
    }

    /**
     * Retourne le nombre de champs modifiés
     */
    public function getChangedFieldsCount(): int
    {
        return count($this->changedFields);
    }

    /**
     * Vérifie si un champ spécifique a été modifié
     */
    public function hasFieldChanged(string $fieldName): bool
    {
        return in_array($fieldName, $this->changedFields);
    }

    /**
     * Retourne la description de l'action
     */
    public function getActionDescription(): string
    {
        return match($this->action) {
            'CREATE' => 'Création',
            'UPDATE' => 'Modification',
            'DELETE' => 'Suppression',
            'VIEW' => 'Consultation',
            'EXPORT' => 'Export',
            'PRINT' => 'Impression',
            'SEND_EMAIL' => 'Envoi email',
            'SIGN' => 'Signature',
            'VALIDATE' => 'Validation',
            'REJECT' => 'Rejet',
            'ARCHIVE' => 'Archivage',
            default => $this->action
        };
    }

    /**
     * Retourne le niveau de criticité de l'action
     */
    public function getCriticalityLevel(): string
    {
        return match($this->action) {
            'DELETE', 'ADMIN_UPDATE' => 'HIGH',
            'UPDATE', 'VALIDATE', 'REJECT' => 'MEDIUM',
            'CREATE', 'VIEW', 'EXPORT' => 'LOW',
            default => 'MEDIUM'
        };
    }

    /**
     * Retourne une description complète de l'audit
     */
    public function getFullDescription(): string
    {
        $entityClass = basename(str_replace('\\', '/', $this->entityType));
        
        return sprintf(
            '%s sur %s #%d par %s le %s',
            $this->getActionDescription(),
            $entityClass,
            $this->entityId,
            $this->user->getPrenom() . ' ' . $this->user->getNom(),
            $this->timestamp->format('d/m/Y à H:i:s')
        );
    }

    public function __toString(): string
    {
        return $this->getFullDescription();
    }
}