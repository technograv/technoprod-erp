<?php

namespace App\Entity;

use App\Repository\CommuneGeometryCacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommuneGeometryCacheRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['code_insee'], name: 'idx_code_insee')]
#[ORM\Index(columns: ['last_updated'], name: 'idx_last_updated')]
class CommuneGeometryCache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5, unique: true)]
    private ?string $codeInsee = null;

    #[ORM\Column(length: 255)]
    private ?string $nomCommune = null;

    #[ORM\Column(type: Types::JSON)]
    private array $geometryData = [];

    #[ORM\Column]
    private ?int $pointsCount = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $source = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastUpdated = null;

    #[ORM\Column]
    private bool $isValid = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastUpdated = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->lastUpdated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeInsee(): ?string
    {
        return $this->codeInsee;
    }

    public function setCodeInsee(string $codeInsee): static
    {
        $this->codeInsee = $codeInsee;
        return $this;
    }

    public function getNomCommune(): ?string
    {
        return $this->nomCommune;
    }

    public function setNomCommune(string $nomCommune): static
    {
        $this->nomCommune = $nomCommune;
        return $this;
    }

    public function getGeometryData(): array
    {
        return $this->geometryData;
    }

    public function setGeometryData(array $geometryData): static
    {
        $this->geometryData = $geometryData;
        $this->pointsCount = isset($geometryData['coordinates']) ? count($geometryData['coordinates']) : 0;
        return $this;
    }

    public function getPointsCount(): ?int
    {
        return $this->pointsCount;
    }

    public function setPointsCount(int $pointsCount): static
    {
        $this->pointsCount = $pointsCount;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;
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

    public function getLastUpdated(): ?\DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeImmutable $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setIsValid(bool $isValid): static
    {
        $this->isValid = $isValid;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /**
     * Vérifie si le cache est expiré (plus de 30 jours)
     */
    public function isExpired(): bool
    {
        $expirationDate = $this->lastUpdated->modify('+30 days');
        return $expirationDate < new \DateTimeImmutable();
    }

    /**
     * Retourne les coordonnées formatées pour Google Maps
     */
    public function getFormattedCoordinates(): array
    {
        if (!isset($this->geometryData['coordinates'])) {
            return [];
        }

        return array_map(function($coord) {
            return [
                'lat' => (float) $coord['lat'],
                'lng' => (float) $coord['lng']
            ];
        }, $this->geometryData['coordinates']);
    }
}