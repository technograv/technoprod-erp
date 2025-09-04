<?php

namespace App\Entity;

use App\Repository\UserPreferencesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPreferencesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserPreferences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\Column(length: 20, options: ['default' => 'company'])]
    private string $emailSignatureType = 'company'; // 'company' ou 'personal'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customEmailSignature = null;

    #[ORM\Column(length: 10, options: ['default' => 'fr'])]
    private string $language = 'fr';

    #[ORM\Column(length: 50, options: ['default' => 'Europe/Paris'])]
    private string $timezone = 'Europe/Paris';

    #[ORM\Column(options: ['default' => true])]
    private bool $emailNotifications = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $smsNotifications = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dashboardWidgets = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tablePreferences = null; // Colonnes visibles, tri par défaut, etc.

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null; // Notes personnelles pour futures fonctionnalités

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $calendarSettings = null; // Calendriers Google sélectionnés

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getEmailSignatureType(): string
    {
        return $this->emailSignatureType;
    }

    public function setEmailSignatureType(string $emailSignatureType): static
    {
        $this->emailSignatureType = $emailSignatureType;
        return $this;
    }

    public function getCustomEmailSignature(): ?string
    {
        return $this->customEmailSignature;
    }

    public function setCustomEmailSignature(?string $customEmailSignature): static
    {
        $this->customEmailSignature = $customEmailSignature;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function isEmailNotifications(): bool
    {
        return $this->emailNotifications;
    }

    public function setEmailNotifications(bool $emailNotifications): static
    {
        $this->emailNotifications = $emailNotifications;
        return $this;
    }

    public function isSmsNotifications(): bool
    {
        return $this->smsNotifications;
    }

    public function setSmsNotifications(bool $smsNotifications): static
    {
        $this->smsNotifications = $smsNotifications;
        return $this;
    }

    public function getDashboardWidgets(): ?array
    {
        return $this->dashboardWidgets;
    }

    public function setDashboardWidgets(?array $dashboardWidgets): static
    {
        $this->dashboardWidgets = $dashboardWidgets;
        return $this;
    }

    public function getTablePreferences(): ?array
    {
        return $this->tablePreferences;
    }

    public function setTablePreferences(?array $tablePreferences): static
    {
        $this->tablePreferences = $tablePreferences;
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

    /**
     * Vérifie si l'utilisateur utilise la signature Gmail personnelle
     */
    public function usePersonalSignature(): bool
    {
        return $this->emailSignatureType === 'personal';
    }

    /**
     * Vérifie si l'utilisateur utilise la signature d'entreprise
     */
    public function useCompanySignature(): bool
    {
        return $this->emailSignatureType === 'company';
    }

    /**
     * Retourne la signature à utiliser selon les préférences
     */
    public function getEffectiveSignature(?string $companySignature = null): ?string
    {
        if ($this->usePersonalSignature()) {
            return $this->customEmailSignature;
        }

        return $companySignature; // Signature d'entreprise par défaut
    }

    public function getCalendarSettings(): ?array
    {
        return $this->calendarSettings;
    }

    public function setCalendarSettings(?array $calendarSettings): static
    {
        $this->calendarSettings = $calendarSettings;
        return $this;
    }

    /**
     * Retourne les IDs des calendriers sélectionnés
     */
    public function getSelectedCalendarIds(): array
    {
        $settings = $this->getCalendarSettings();
        if (!$settings || !isset($settings['selected_calendars'])) {
            return ['primary']; // Par défaut, utiliser le calendrier principal
        }
        
        return $settings['selected_calendars'];
    }

    /**
     * Définit les calendriers sélectionnés
     */
    public function setSelectedCalendarIds(array $calendarIds): static
    {
        $settings = $this->getCalendarSettings() ?? [];
        $settings['selected_calendars'] = $calendarIds;
        $this->setCalendarSettings($settings);
        return $this;
    }

    /**
     * Retourne les calendriers d'écriture configurés
     */
    public function getWriteCalendarIds(): array
    {
        $settings = $this->getCalendarSettings();
        if (!$settings || !isset($settings['write_calendars'])) {
            return [
                'rdv_commerciaux' => 'primary',
                'reunions' => 'primary',
                'livraisons' => 'primary',
                'facturations' => 'primary'
            ];
        }
        
        return $settings['write_calendars'];
    }

    /**
     * Définit les calendriers d'écriture
     */
    public function setWriteCalendarIds(array $writeCalendarIds): static
    {
        $settings = $this->getCalendarSettings() ?? [];
        $settings['write_calendars'] = $writeCalendarIds;
        $this->setCalendarSettings($settings);
        return $this;
    }

    /**
     * Retourne le calendrier d'écriture pour un type d'événement
     */
    public function getWriteCalendarForType(string $eventType): string
    {
        $writeCalendars = $this->getWriteCalendarIds();
        return $writeCalendars[$eventType] ?? 'primary';
    }
}