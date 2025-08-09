<?php

namespace App\Service;

use App\Entity\DocumentTemplate;
use App\Entity\Societe;
use App\Repository\DocumentTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion des templates de documents avec héritage
 */
class TemplateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantService $tenantService,
        private InheritanceService $inheritanceService
    ) {
    }

    /**
     * Récupère le template par défaut pour un type de document avec héritage
     */
    public function getDefaultTemplate(string $typeDocument, ?Societe $societe = null): ?DocumentTemplate
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        return $this->getRepository()->findDefaultByTypeAndSociete($typeDocument, $societe);
    }

    /**
     * Récupère tous les templates disponibles pour un type de document avec héritage
     */
    public function getAvailableTemplates(string $typeDocument, ?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        return $this->getRepository()->findByTypeWithInheritance($typeDocument, $societe);
    }

    /**
     * Récupère tous les templates avec informations d'héritage
     */
    public function getTemplatesWithInheritance(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        $templates = $this->getRepository()->findBySocieteWithInheritance($societe);
        $enrichedTemplates = [];
        
        foreach ($templates as $template) {
            $enrichedTemplates[] = [
                'template' => $template,
                'source' => $this->getTemplateSource($template, $societe),
                'is_inherited' => $this->isTemplateInherited($template, $societe),
                'can_override' => $this->canOverrideTemplate($template, $societe),
            ];
        }
        
        return $enrichedTemplates;
    }

    /**
     * Détermine la source d'un template (local, hérité, global)
     */
    public function getTemplateSource(DocumentTemplate $template, ?Societe $societe = null): string
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$template->getSociete()) {
            return 'global';
        }
        
        if (!$societe) {
            return 'global';
        }
        
        if ($template->getSociete()->getId() === $societe->getId()) {
            return 'local';
        }
        
        if ($societe->isFille() && $template->getSociete()->getId() === $societe->getSocieteParent()?->getId()) {
            return 'inherited';
        }
        
        return 'other';
    }

    /**
     * Vérifie si un template est hérité
     */
    public function isTemplateInherited(DocumentTemplate $template, ?Societe $societe = null): bool
    {
        return $this->getTemplateSource($template, $societe) === 'inherited';
    }

    /**
     * Vérifie si un template peut être surchargé par la société courante
     */
    public function canOverrideTemplate(DocumentTemplate $template, ?Societe $societe = null): bool
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe || !$societe->isFille()) {
            return false;
        }
        
        $source = $this->getTemplateSource($template, $societe);
        return in_array($source, ['inherited', 'global']);
    }

    /**
     * Crée une surcharge locale d'un template
     */
    public function createTemplateOverride(DocumentTemplate $parentTemplate, ?Societe $societe = null): DocumentTemplate
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$this->canOverrideTemplate($parentTemplate, $societe)) {
            throw new \InvalidArgumentException('Ce template ne peut pas être surchargé par cette société');
        }
        
        $override = new DocumentTemplate();
        $override->setTypeDocument($parentTemplate->getTypeDocument());
        $override->setNom($parentTemplate->getNom() . ' (Override)');
        $override->setCheminFichier($parentTemplate->getCheminFichier());
        $override->setDescription($parentTemplate->getDescription());
        $override->setEstActif($parentTemplate->isEstActif());
        $override->setEstDefaut($parentTemplate->isEstDefaut());
        $override->setOrdre($parentTemplate->getOrdre());
        $override->setSociete($societe);
        
        $this->entityManager->persist($override);
        $this->entityManager->flush();
        
        return $override;
    }

    /**
     * Définit le template par défaut pour un type de document
     */
    public function setDefaultTemplate(DocumentTemplate $template, ?Societe $societe = null): void
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        // Vérifier que le template est accessible à cette société
        $availableTemplates = $this->getAvailableTemplates($template->getTypeDocument(), $societe);
        $templateIds = array_map(fn($t) => $t->getId(), $availableTemplates);
        
        if (!in_array($template->getId(), $templateIds)) {
            throw new \InvalidArgumentException('Ce template n\'est pas accessible à cette société');
        }
        
        $this->getRepository()->setAsDefault($template);
    }

    /**
     * Récupère les statistiques des templates par type
     */
    public function getTemplateStatistics(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        $stats = [];
        $typesDocuments = DocumentTemplate::getTypesDocuments();
        
        foreach ($typesDocuments as $type => $label) {
            $templates = $this->getAvailableTemplates($type, $societe);
            $defaultTemplate = $this->getDefaultTemplate($type, $societe);
            
            $stats[$type] = [
                'label' => $label,
                'total' => count($templates),
                'actifs' => count(array_filter($templates, fn($t) => $t->isEstActif())),
                'default' => $defaultTemplate?->getNom(),
                'has_default' => $defaultTemplate !== null,
                'can_create' => true, // Tous les types peuvent avoir des templates
            ];
        }
        
        return $stats;
    }

    /**
     * Récupère les templates manquants (sans défaut)
     */
    public function getMissingDefaultTemplates(?Societe $societe = null): array
    {
        $stats = $this->getTemplateStatistics($societe);
        
        return array_filter($stats, fn($stat) => !$stat['has_default']);
    }

    /**
     * Valide qu'un chemin de template est correct
     */
    public function validateTemplatePath(string $path): array
    {
        $errors = [];
        
        // Vérifications basiques
        if (empty($path)) {
            $errors[] = 'Le chemin ne peut pas être vide';
            return ['valid' => false, 'errors' => $errors];
        }
        
        if (!str_ends_with($path, '.html.twig')) {
            $errors[] = 'Le chemin doit se terminer par .html.twig';
        }
        
        if (str_starts_with($path, '/')) {
            $errors[] = 'Le chemin ne doit pas commencer par /';
        }
        
        if (strpos($path, '..') !== false) {
            $errors[] = 'Le chemin ne peut pas contenir ".."';
        }
        
        // Vérifier que le fichier existe (optionnel)
        $fullPath = $this->getTemplatePath($path);
        $fileExists = file_exists($fullPath);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'path' => $path,
            'full_path' => $fullPath,
            'file_exists' => $fileExists,
            'warnings' => !$fileExists ? ['Le fichier template n\'existe pas encore'] : []
        ];
    }

    /**
     * Récupère le chemin complet d'un template
     */
    public function getTemplatePath(string $relativePath): string
    {
        $templatesDir = $this->entityManager->getConfiguration()->getProjectDir() . '/templates/';
        return $templatesDir . ltrim($relativePath, '/');
    }

    /**
     * Crée un fichier template avec contenu de base
     */
    public function createTemplateFile(string $relativePath, string $typeDocument): bool
    {
        $fullPath = $this->getTemplatePath($relativePath);
        $directory = dirname($fullPath);
        
        // Créer le dossier si nécessaire
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                return false;
            }
        }
        
        // Contenu de base selon le type
        $content = $this->getDefaultTemplateContent($typeDocument);
        
        return file_put_contents($fullPath, $content) !== false;
    }

    /**
     * Génère le contenu par défaut d'un template
     */
    private function getDefaultTemplateContent(string $typeDocument): string
    {
        $label = DocumentTemplate::getTypesDocuments()[$typeDocument] ?? ucfirst($typeDocument);
        
        return <<<TWIG
{# Template {$label} - Généré automatiquement #}
{% extends 'base_document.html.twig' %}

{% block title %}{$label}{% endblock %}

{% block document_header %}
    <h1>{$label} #{{ document.numero }}</h1>
    <p class="text-muted">Date: {{ document.createdAt|date('d/m/Y') }}</p>
{% endblock %}

{% block document_content %}
    <div class="row">
        <div class="col-md-6">
            <h4>Client</h4>
            <p>{{ document.client.nom }}</p>
        </div>
        <div class="col-md-6">
            <h4>Total</h4>
            <p class="h3">{{ document.totalTTC|number_format(2, ',', ' ') }} €</p>
        </div>
    </div>
    
    {# TODO: Ajouter le contenu spécifique au {$label} #}
{% endblock %}
TWIG;
    }

    /**
     * Récupère le repository des templates
     */
    private function getRepository(): DocumentTemplateRepository
    {
        return $this->entityManager->getRepository(DocumentTemplate::class);
    }
}