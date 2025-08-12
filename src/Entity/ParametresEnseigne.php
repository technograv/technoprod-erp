<?php

namespace App\Entity;

use App\Repository\ParametresEnseigneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParametresEnseigneRepository::class)]
class ParametresEnseigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Societe::class, cascade: ['persist'], fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Societe $societe = null;

    // Informations en-tête documents
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomEnteteDocument = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adresseEnteteDocument = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephoneEnteteDocument = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailEnteteDocument = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWebEnteteDocument = null;

    // Conditions générales et particulières
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionsGeneralesVente = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionsParticulieresVente = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mentionsLegalesDocument = null;

    // Configuration page d'accueil
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titrePagesAccueil = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $messageAccueil = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contenuDashboard = null;

    // Configuration ergonomique
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $formatDateDefaut = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $deviseDefaut = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $langueDefaut = null;

    // Paramètres avancés
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $widgetsDashboardPersonnalises = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modulesActives = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configurationAvancee = null;

    // Métadonnées
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(Societe $societe): static
    {
        $this->societe = $societe;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getNomEnteteDocument(): ?string
    {
        return $this->nomEnteteDocument;
    }

    public function setNomEnteteDocument(?string $nomEnteteDocument): static
    {
        $this->nomEnteteDocument = $nomEnteteDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAdresseEnteteDocument(): ?string
    {
        return $this->adresseEnteteDocument;
    }

    public function setAdresseEnteteDocument(?string $adresseEnteteDocument): static
    {
        $this->adresseEnteteDocument = $adresseEnteteDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTelephoneEnteteDocument(): ?string
    {
        return $this->telephoneEnteteDocument;
    }

    public function setTelephoneEnteteDocument(?string $telephoneEnteteDocument): static
    {
        $this->telephoneEnteteDocument = $telephoneEnteteDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getEmailEnteteDocument(): ?string
    {
        return $this->emailEnteteDocument;
    }

    public function setEmailEnteteDocument(?string $emailEnteteDocument): static
    {
        $this->emailEnteteDocument = $emailEnteteDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSiteWebEnteteDocument(): ?string
    {
        return $this->siteWebEnteteDocument;
    }

    public function setSiteWebEnteteDocument(?string $siteWebEnteteDocument): static
    {
        $this->siteWebEnteteDocument = $siteWebEnteteDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getConditionsGeneralesVente(): ?string
    {
        return $this->conditionsGeneralesVente;
    }

    public function setConditionsGeneralesVente(?string $conditionsGeneralesVente): static
    {
        $this->conditionsGeneralesVente = $conditionsGeneralesVente;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getConditionsParticulieresVente(): ?string
    {
        return $this->conditionsParticulieresVente;
    }

    public function setConditionsParticulieresVente(?string $conditionsParticulieresVente): static
    {
        $this->conditionsParticulieresVente = $conditionsParticulieresVente;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMentionsLegalesDocument(): ?string
    {
        return $this->mentionsLegalesDocument;
    }

    public function setMentionsLegalesDocument(?string $mentionsLegalesDocument): static
    {
        $this->mentionsLegalesDocument = $mentionsLegalesDocument;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTitrePagesAccueil(): ?string
    {
        return $this->titrePagesAccueil;
    }

    public function setTitrePagesAccueil(?string $titrePagesAccueil): static
    {
        $this->titrePagesAccueil = $titrePagesAccueil;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMessageAccueil(): ?string
    {
        return $this->messageAccueil;
    }

    public function setMessageAccueil(?string $messageAccueil): static
    {
        $this->messageAccueil = $messageAccueil;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getContenuDashboard(): ?string
    {
        return $this->contenuDashboard;
    }

    public function setContenuDashboard(?string $contenuDashboard): static
    {
        $this->contenuDashboard = $contenuDashboard;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFormatDateDefaut(): ?string
    {
        return $this->formatDateDefaut;
    }

    public function setFormatDateDefaut(?string $formatDateDefaut): static
    {
        $this->formatDateDefaut = $formatDateDefaut;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDeviseDefaut(): ?string
    {
        return $this->deviseDefaut;
    }

    public function setDeviseDefaut(?string $deviseDefaut): static
    {
        $this->deviseDefaut = $deviseDefaut;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLangueDefaut(): ?string
    {
        return $this->langueDefaut;
    }

    public function setLangueDefaut(?string $langueDefaut): static
    {
        $this->langueDefaut = $langueDefaut;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getWidgetsDashboardPersonnalises(): ?array
    {
        return $this->widgetsDashboardPersonnalises;
    }

    public function setWidgetsDashboardPersonnalises(?array $widgetsDashboardPersonnalises): static
    {
        $this->widgetsDashboardPersonnalises = $widgetsDashboardPersonnalises;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getModulesActives(): ?array
    {
        return $this->modulesActives;
    }

    public function setModulesActives(?array $modulesActives): static
    {
        $this->modulesActives = $modulesActives;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getConfigurationAvancee(): ?array
    {
        return $this->configurationAvancee;
    }

    public function setConfigurationAvancee(?array $configurationAvancee): static
    {
        $this->configurationAvancee = $configurationAvancee;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Formats de date disponibles
     */
    public static function getFormatsDateDisponibles(): array
    {
        return [
            'd/m/Y' => 'DD/MM/YYYY (31/12/2024)',
            'm/d/Y' => 'MM/DD/YYYY (12/31/2024)',
            'Y-m-d' => 'YYYY-MM-DD (2024-12-31)',
            'd-m-Y' => 'DD-MM-YYYY (31-12-2024)',
            'd.m.Y' => 'DD.MM.YYYY (31.12.2024)',
        ];
    }

    /**
     * Devises disponibles
     */
    public static function getDevisesDisponibles(): array
    {
        return [
            'EUR' => '€ (Euro)',
            'USD' => '$ (Dollar US)',
            'GBP' => '£ (Livre Sterling)',
            'CHF' => 'CHF (Franc Suisse)',
        ];
    }

    /**
     * Langues disponibles
     */
    public static function getLanguesDisponibles(): array
    {
        return [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch',
        ];
    }
}
