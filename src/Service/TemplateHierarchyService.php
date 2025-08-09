<?php

namespace App\Service;

use App\Entity\Societe;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TemplateHierarchyService
{
    private const BASE_TEMPLATE_DIR = 'base';
    private const CUSTOM_TEMPLATE_BASE = 'custom';

    public function __construct(
        private Environment $twig,
        private TenantService $tenantService,
        string $projectDir = null
    ) {
        $this->projectDir = $projectDir ?: dirname(__DIR__, 2);
    }

    private string $projectDir;

    /**
     * Résout le template à utiliser selon la hiérarchie société
     */
    public function resolveTemplate(string $templateName): string
    {
        $societe = $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return $templateName; // Template par défaut
        }

        // Construire les chemins de recherche par priorité
        $searchPaths = $this->buildSearchPaths($societe, $templateName);
        
        foreach ($searchPaths as $templatePath) {
            if ($this->templateExists($templatePath)) {
                return $templatePath;
            }
        }

        // Fallback sur template par défaut
        return $templateName;
    }

    /**
     * Construit les chemins de recherche selon la hiérarchie
     */
    private function buildSearchPaths(Societe $societe, string $templateName): array
    {
        $paths = [];
        
        if ($societe->isFille()) {
            // 1. Template spécifique fille
            $paths[] = $this->buildCustomPath($societe->getSocieteParent(), $societe, $templateName);
            
            // 2. Template société mère
            $paths[] = $this->buildCustomPath($societe->getSocieteParent(), null, $templateName);
        } else {
            // Template société mère
            $paths[] = $this->buildCustomPath($societe, null, $templateName);
        }

        // 3. Template de base (fallback)
        $paths[] = self::BASE_TEMPLATE_DIR . '/' . $templateName;
        
        // 4. Template original (fallback final)
        $paths[] = $templateName;

        return array_filter($paths);
    }

    /**
     * Construit le chemin custom pour une société
     */
    private function buildCustomPath(Societe $societeMere, ?Societe $societeFille, string $templateName): string
    {
        $path = self::CUSTOM_TEMPLATE_BASE . '/societe_' . $societeMere->getId();
        
        if ($societeFille) {
            $path .= '/' . $this->slugify($societeFille->getNom());
        }
        
        return $path . '/' . $templateName;
    }

    /**
     * Vérifie si un template existe
     */
    private function templateExists(string $templatePath): bool
    {
        try {
            $loader = $this->twig->getLoader();
            if ($loader instanceof FilesystemLoader) {
                return $loader->exists($templatePath);
            }
            
            // Méthode alternative
            $this->twig->load($templatePath);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Crée les répertoires de templates pour une société
     */
    public function createTemplateDirectories(Societe $societe): array
    {
        $createdDirs = [];
        $templatesDir = $this->projectDir . '/templates';
        
        if ($societe->isMere()) {
            // Répertoire société mère
            $mereDir = $templatesDir . '/' . self::CUSTOM_TEMPLATE_BASE . '/societe_' . $societe->getId();
            if ($this->createDirectory($mereDir)) {
                $createdDirs[] = $mereDir;
            }
            
            // Répertoires pour les filles
            foreach ($societe->getSocietesFilles() as $fille) {
                if ($fille->isActive()) {
                    $filleDir = $mereDir . '/' . $this->slugify($fille->getNom());
                    if ($this->createDirectory($filleDir)) {
                        $createdDirs[] = $filleDir;
                    }
                }
            }
        }

        return $createdDirs;
    }

    /**
     * Copie les templates de base pour une société
     */
    public function initializeTemplatesForSociete(Societe $societe, array $templatesToCopy = []): array
    {
        $copiedFiles = [];
        $templatesDir = $this->projectDir . '/templates';
        
        $defaultTemplates = $templatesToCopy ?: [
            'devis/pdf.html.twig',
            'email/devis_notification.html.twig',
            'components/invoice_header.html.twig'
        ];

        foreach ($defaultTemplates as $template) {
            $sourcePath = $templatesDir . '/base/' . $template;
            
            if (!file_exists($sourcePath)) {
                $sourcePath = $templatesDir . '/' . $template; // Fallback
            }
            
            if (file_exists($sourcePath)) {
                $targetPath = $this->getCustomTemplatePath($societe, $template);
                $targetDir = dirname($targetPath);
                
                if ($this->createDirectory($targetDir) && copy($sourcePath, $targetPath)) {
                    $copiedFiles[] = $targetPath;
                }
            }
        }

        return $copiedFiles;
    }

    /**
     * Récupère le chemin custom d'un template pour une société
     */
    public function getCustomTemplatePath(Societe $societe, string $templateName): string
    {
        $templatesDir = $this->projectDir . '/templates';
        
        if ($societe->isFille()) {
            return $templatesDir . '/' . $this->buildCustomPath($societe->getSocieteParent(), $societe, $templateName);
        }
        
        return $templatesDir . '/' . $this->buildCustomPath($societe, null, $templateName);
    }

    /**
     * Liste les templates disponibles pour une société
     */
    public function getAvailableTemplates(Societe $societe): array
    {
        $templatesDir = $this->projectDir . '/templates';
        $customDir = dirname($this->getCustomTemplatePath($societe, ''));
        
        $templates = [];
        
        if (is_dir($customDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($customDir)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'twig') {
                    $relativePath = str_replace($customDir . '/', '', $file->getPathname());
                    $templates[] = $relativePath;
                }
            }
        }

        return $templates;
    }

    /**
     * Récupère les variables de thème pour une société
     */
    public function getThemeVariables(Societe $societe): array
    {
        $variables = [
            'societe_nom' => $societe->getNom(),
            'societe_display_name' => $societe->getDisplayName(),
            'couleur_primaire' => $societe->getCouleurPrimaire() ?? '#dc3545',
            'couleur_secondaire' => $societe->getCouleurSecondaire() ?? '#6c757d',
            'logo_path' => $societe->getLogo(),
            'theme_name' => $societe->getParametreCustom('template_theme', 'default'),
        ];

        // Héritage depuis société mère si c'est une fille
        if ($societe->isFille() && $societe->getSocieteParent()) {
            $mere = $societe->getSocieteParent();
            
            // Valeurs par défaut depuis la mère si non définies
            foreach (['couleur_primaire', 'couleur_secondaire', 'logo_path'] as $prop) {
                if (!$variables[$prop] || $variables[$prop] === '#dc3545' || $variables[$prop] === '#6c757d') {
                    $mereValue = match($prop) {
                        'couleur_primaire' => $mere->getCouleurPrimaire(),
                        'couleur_secondaire' => $mere->getCouleurSecondaire(),
                        'logo_path' => $mere->getLogo(),
                    };
                    if ($mereValue) {
                        $variables[$prop] = $mereValue;
                    }
                }
            }
        }

        // Ajouter les paramètres custom
        if ($societe->getParametresCustom()) {
            foreach ($societe->getParametresCustom() as $key => $value) {
                $variables['custom_' . $key] = $value;
            }
        }

        return $variables;
    }

    /**
     * Crée un répertoire s'il n'existe pas
     */
    private function createDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }

    /**
     * Convertit un nom en slug
     */
    private function slugify(string $text): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    /**
     * Méthode utilitaire pour les contrôleurs
     */
    public function render(string $template, array $parameters = []): string
    {
        $resolvedTemplate = $this->resolveTemplate($template);
        
        // Ajouter les variables de thème automatiquement
        $societe = $this->tenantService->getCurrentSociete();
        if ($societe) {
            $parameters = array_merge(
                $this->getThemeVariables($societe),
                $parameters
            );
        }

        return $this->twig->render($resolvedTemplate, $parameters);
    }
}