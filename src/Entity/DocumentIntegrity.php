<?php

namespace App\Entity;

use App\Repository\DocumentIntegrityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentIntegrityRepository::class)]
#[ORM\Table(name: 'document_integrity')]
#[ORM\Index(columns: ['document_type', 'document_id'], name: 'idx_document_lookup')]
#[ORM\Index(columns: ['timestamp_creation'], name: 'idx_timestamp_creation')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
class DocumentIntegrity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // IDENTIFICATION DOCUMENT
    #[ORM\Column(length: 50)]
    private string $documentType;

    #[ORM\Column]
    private int $documentId;

    #[ORM\Column(length: 20)]
    private string $documentNumber;

    // INTÉGRITÉ CRYPTOGRAPHIQUE
    #[ORM\Column(length: 10)]
    private string $hashAlgorithm = 'SHA256';

    #[ORM\Column(length: 64)]
    private string $documentHash;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousHash = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $signatureData;

    // HORODATAGE SÉCURISÉ
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $timestampCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $timestampModification = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $qualifiedTimestamp = null;

    // UTILISATEUR ET CONTEXTE
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $modifiedBy = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    // STATUT ET VALIDATION
    #[ORM\Column(length: 20)]
    private string $status = 'valid';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastVerification = null;

    #[ORM\Column(nullable: true)]
    private ?bool $integrityValid = null;

    // BLOCKCHAIN (OPTIONNEL)
    #[ORM\Column(length: 66, nullable: true)]
    private ?string $blockchainTxHash = null;

    #[ORM\Column(nullable: true)]
    private ?int $blockchainBlockNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $blockchainTimestamp = null;

    // MÉTADONNÉES CONFORMITÉ
    #[ORM\Column(type: Types::JSON)]
    private array $complianceMetadata = [];

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $archivalReference = null;

    // AUDIT
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->timestampCreation = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // GETTERS ET SETTERS

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function setDocumentId(int $documentId): static
    {
        $this->documentId = $documentId;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): static
    {
        $this->documentNumber = $documentNumber;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getHashAlgorithm(): string
    {
        return $this->hashAlgorithm;
    }

    public function setHashAlgorithm(string $hashAlgorithm): static
    {
        $this->hashAlgorithm = $hashAlgorithm;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDocumentHash(): string
    {
        return $this->documentHash;
    }

    public function setDocumentHash(string $documentHash): static
    {
        $this->documentHash = $documentHash;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getPreviousHash(): ?string
    {
        return $this->previousHash;
    }

    public function setPreviousHash(?string $previousHash): static
    {
        $this->previousHash = $previousHash;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSignatureData(): string
    {
        return $this->signatureData;
    }

    public function setSignatureData(string $signatureData): static
    {
        $this->signatureData = $signatureData;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getTimestampCreation(): \DateTimeInterface
    {
        return $this->timestampCreation;
    }

    public function setTimestampCreation(\DateTimeInterface $timestampCreation): static
    {
        $this->timestampCreation = $timestampCreation;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getTimestampModification(): ?\DateTimeInterface
    {
        return $this->timestampModification;
    }

    public function setTimestampModification(?\DateTimeInterface $timestampModification): static
    {
        $this->timestampModification = $timestampModification;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getQualifiedTimestamp(): ?string
    {
        return $this->qualifiedTimestamp;
    }

    public function setQualifiedTimestamp(?string $qualifiedTimestamp): static
    {
        $this->qualifiedTimestamp = $qualifiedTimestamp;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getModifiedBy(): ?User
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?User $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLastVerification(): ?\DateTimeInterface
    {
        return $this->lastVerification;
    }

    public function setLastVerification(?\DateTimeInterface $lastVerification): static
    {
        $this->lastVerification = $lastVerification;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isIntegrityValid(): ?bool
    {
        return $this->integrityValid;
    }

    public function setIntegrityValid(?bool $integrityValid): static
    {
        $this->integrityValid = $integrityValid;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getBlockchainTxHash(): ?string
    {
        return $this->blockchainTxHash;
    }

    public function setBlockchainTxHash(?string $blockchainTxHash): static
    {
        $this->blockchainTxHash = $blockchainTxHash;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getBlockchainBlockNumber(): ?int
    {
        return $this->blockchainBlockNumber;
    }

    public function setBlockchainBlockNumber(?int $blockchainBlockNumber): static
    {
        $this->blockchainBlockNumber = $blockchainBlockNumber;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getBlockchainTimestamp(): ?\DateTimeInterface
    {
        return $this->blockchainTimestamp;
    }

    public function setBlockchainTimestamp(?\DateTimeInterface $blockchainTimestamp): static
    {
        $this->blockchainTimestamp = $blockchainTimestamp;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getComplianceMetadata(): array
    {
        return $this->complianceMetadata;
    }

    public function setComplianceMetadata(array $complianceMetadata): static
    {
        $this->complianceMetadata = $complianceMetadata;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getArchivalReference(): ?string
    {
        return $this->archivalReference;
    }

    public function setArchivalReference(?string $archivalReference): static
    {
        $this->archivalReference = $archivalReference;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // MÉTHODES UTILITAIRES

    /**
     * Vérifie si le document est ancré sur blockchain
     */
    public function isBlockchainAnchored(): bool
    {
        return $this->blockchainTxHash !== null;
    }

    /**
     * Retourne le statut de conformité
     */
    public function getComplianceStatus(): string
    {
        if ($this->integrityValid === false) {
            return 'NON_CONFORME';
        }
        
        if ($this->integrityValid === null) {
            return 'NON_VERIFIE';
        }
        
        return 'CONFORME';
    }

    /**
     * Retourne une représentation lisible du document
     */
    public function getDocumentDescription(): string
    {
        return sprintf(
            '%s #%d (%s)',
            ucfirst($this->documentType),
            $this->documentId,
            $this->documentNumber
        );
    }

    public function __toString(): string
    {
        return $this->getDocumentDescription();
    }
}