<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'alerte_type')]
class AlerteType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rolesCibles = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $societesCibles = [];

    #[ORM\Column(length: 255)]
    private ?string $classeDetection = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(nullable: true)]
    private ?int $ordre = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateModification = null;

    #[ORM\Column(length: 50, options: ['default' => 'warning'])]
    private string $severity = 'warning';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configuration = null;

    #[ORM\OneToMany(mappedBy: 'alerteType', targetEntity: AlerteInstance::class, cascade: ['remove'])]
    private Collection $instances;

    public function __construct()
    {
        $this->instances = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getRolesCibles(): array
    {
        return $this->rolesCibles ?? [];
    }

    public function setRolesCibles(?array $rolesCibles): static
    {
        $this->rolesCibles = $rolesCibles;
        return $this;
    }

    public function getSocietesCibles(): array
    {
        return $this->societesCibles ?? [];
    }

    public function setSocietesCibles(?array $societesCibles): static
    {
        $this->societesCibles = $societesCibles;
        return $this;
    }

    public function getClasseDetection(): ?string
    {
        return $this->classeDetection;
    }

    public function setClasseDetection(string $classeDetection): static
    {
        $this->classeDetection = $classeDetection;
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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;
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

    public function getDateModification(): ?\DateTimeImmutable
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeImmutable $dateModification): static
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function addInstance(AlerteInstance $instance): static
    {
        if (!$this->instances->contains($instance)) {
            $this->instances->add($instance);
            $instance->setAlerteType($this);
        }
        return $this;
    }

    public function removeInstance(AlerteInstance $instance): static
    {
        if ($this->instances->removeElement($instance)) {
            if ($instance->getAlerteType() === $this) {
                $instance->setAlerteType(null);
            }
        }
        return $this;
    }

    public function getInstancesCount(): int
    {
        return $this->instances->count();
    }

    public function getActiveInstancesCount(): int
    {
        try {
            error_log('🔍 [DEBUG] AlerteType::getActiveInstancesCount called for type ' . $this->getId());

            if (!$this->instances) {
                error_log('❌ [ERROR] instances collection is null for AlerteType ' . $this->getId());
                return 0;
            }

            error_log('🔍 [DEBUG] instances collection has ' . $this->instances->count() . ' total items');

            $filteredInstances = $this->instances->filter(fn(AlerteInstance $instance) => !$instance->isResolved());
            $activeCount = $filteredInstances->count();

            error_log('🔍 [DEBUG] Active instances count: ' . $activeCount);
            return $activeCount;
        } catch (\Exception $e) {
            error_log('❌ [ERROR] Exception in getActiveInstancesCount for AlerteType ' . $this->getId() . ': ' . $e->getMessage());
            error_log('❌ [ERROR] Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): static
    {
        $this->configuration = $configuration;
        return $this;
    }
}