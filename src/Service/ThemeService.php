<?php

namespace App\Service;

use App\Entity\Societe;

class ThemeService
{
    public function __construct(
        private TenantService $tenantService,
        private InheritanceService $inheritanceService
    ) {
    }

    /**
     * Génère le CSS dynamique pour la société actuelle
     */
    public function generateDynamicCSS(?Societe $societe = null): string
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return $this->getDefaultCSS();
        }

        $variables = $this->getThemeVariables($societe);
        
        return $this->buildCSS($variables);
    }

    /**
     * Récupère les variables de thème pour une société avec héritage automatique
     */
    public function getThemeVariables(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return $this->getDefaultVariables();
        }

        // Utiliser l'InheritanceService pour récupérer les couleurs avec héritage
        $colors = $this->inheritanceService->getColors($societe);
        $theme = $this->inheritanceService->getTheme($societe);
        $logo = $this->inheritanceService->getLogo($societe);

        $variables = [
            // Couleurs principales avec héritage automatique
            'primary_color' => $colors['primary'],
            'secondary_color' => $colors['secondary'],
            'tertiary_color' => $colors['tertiary'],
            
            // Couleurs dérivées
            'primary_rgb' => $this->hexToRgb($colors['primary']),
            'secondary_rgb' => $this->hexToRgb($colors['secondary']),
            'tertiary_rgb' => $this->hexToRgb($colors['tertiary']),
            
            // Variantes de couleurs
            'primary_light' => $this->lightenColor($colors['primary'], 0.2),
            'primary_dark' => $this->darkenColor($colors['primary'], 0.2),
            'secondary_light' => $this->lightenColor($colors['secondary'], 0.2),
            'secondary_dark' => $this->darkenColor($colors['secondary'], 0.2),
            'tertiary_light' => $this->lightenColor($colors['tertiary'], 0.2),
            'tertiary_dark' => $this->darkenColor($colors['tertiary'], 0.2),
            
            // Informations société avec héritage
            'societe_name' => $societe->getNom(),
            'logo_url' => $logo,
            'theme_name' => $theme,
        ];

        // Couleurs spécifiques par thème
        $themeColors = $this->getThemeSpecificColors($variables['theme_name']);
        $variables = array_merge($variables, $themeColors);

        return $variables;
    }

    /**
     * Construit le CSS à partir des variables
     */
    private function buildCSS(array $variables): string
    {
        $css = ":root {\n";
        
        // Variables CSS custom
        $css .= "  /* Variables dynamiques société */\n";
        $css .= "  --bs-primary: {$variables['primary_color']};\n";
        $css .= "  --bs-secondary: {$variables['secondary_color']};\n";
        $css .= "  --bs-success: {$variables['tertiary_color']};\n";
        $css .= "  --bs-primary-rgb: {$variables['primary_rgb']};\n";
        $css .= "  --bs-secondary-rgb: {$variables['secondary_rgb']};\n";
        $css .= "  --bs-success-rgb: {$variables['tertiary_rgb']};\n";
        
        $css .= "  /* Variantes de couleurs */\n";
        $css .= "  --tenant-primary-light: {$variables['primary_light']};\n";
        $css .= "  --tenant-primary-dark: {$variables['primary_dark']};\n";
        $css .= "  --tenant-secondary-light: {$variables['secondary_light']};\n";
        $css .= "  --tenant-secondary-dark: {$variables['secondary_dark']};\n";
        $css .= "  --tenant-tertiary-light: {$variables['tertiary_light']};\n";
        $css .= "  --tenant-tertiary-dark: {$variables['tertiary_dark']};\n";
        
        $css .= "}\n\n";
        
        // Classes spécifiques au thème
        $css .= $this->buildThemeSpecificCSS($variables);
        
        // CSS pour la barre de navigation
        $css .= $this->buildNavigationCSS($variables);
        
        // CSS pour les boutons et éléments interactifs
        $css .= $this->buildInteractiveCSS($variables);
        
        return $css;
    }

    /**
     * CSS spécifique au thème
     */
    private function buildThemeSpecificCSS(array $variables): string
    {
        $css = "/* Styles spécifiques au thème {$variables['theme_name']} */\n";
        
        switch ($variables['theme_name']) {
            case 'blue': // TechnoGrav
                $css .= ".theme-blue .navbar-brand { color: {$variables['primary_color']} !important; }\n";
                $css .= ".theme-blue .badge { background: linear-gradient(45deg, {$variables['primary_color']}, {$variables['secondary_color']}); }\n";
                $css .= ".theme-blue .card-header { background: linear-gradient(135deg, {$variables['primary_color']}, {$variables['primary_light']}); }\n";
                break;
                
            case 'green': // TechnoPrint
                $css .= ".theme-green .navbar-brand { color: {$variables['primary_color']} !important; }\n";
                $css .= ".theme-green .alert-success { border-color: {$variables['primary_color']}; background: rgba({$variables['primary_rgb']}, 0.1); }\n";
                $css .= ".theme-green .progress-bar { background: linear-gradient(90deg, {$variables['primary_color']}, {$variables['secondary_color']}); }\n";
                break;
                
            case 'yellow': // TechnoBuro
                $css .= ".theme-yellow .navbar-brand { color: {$variables['primary_color']} !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }\n";
                $css .= ".theme-yellow .btn-primary { background: linear-gradient(45deg, {$variables['primary_color']}, {$variables['secondary_color']}); border: none; }\n";
                $css .= ".theme-yellow .table-striped tbody tr:nth-of-type(odd) { background: rgba({$variables['primary_rgb']}, 0.05); }\n";
                break;
        }
        
        $css .= "\n";
        return $css;
    }

    /**
     * CSS pour la barre de navigation
     */
    private function buildNavigationCSS(array $variables): string
    {
        return "/* Navigation dynamique */
.navbar-dark {
  background: linear-gradient(135deg, {$variables['primary_color']}, {$variables['primary_dark']}) !important;
}

.navbar-dark .navbar-nav .nav-link:hover {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.tenant-selector .dropdown-toggle:hover {
  background: rgba(255, 255, 255, 0.15) !important;
  border-color: rgba(255, 255, 255, 0.3) !important;
}

";
    }

    /**
     * CSS pour les éléments interactifs
     */
    private function buildInteractiveCSS(array $variables): string
    {
        return "/* Éléments interactifs */
.btn-primary {
  background: {$variables['primary_color']};
  border-color: {$variables['primary_color']};
}

.btn-primary:hover {
  background: {$variables['primary_dark']};
  border-color: {$variables['primary_dark']};
}

.btn-outline-primary {
  color: {$variables['primary_color']};
  border-color: {$variables['primary_color']};
}

.btn-outline-primary:hover {
  background: {$variables['primary_color']};
  border-color: {$variables['primary_color']};
}

.text-primary {
  color: {$variables['primary_color']} !important;
}

.bg-primary {
  background-color: {$variables['primary_color']} !important;
}

.border-primary {
  border-color: {$variables['primary_color']} !important;
}

/* Liens */
a {
  color: {$variables['primary_color']};
}

a:hover {
  color: {$variables['primary_dark']};
}

/* Éléments de formulaire */
.form-control:focus {
  border-color: {$variables['primary_light']};
  box-shadow: 0 0 0 0.2rem rgba({$variables['primary_rgb']}, 0.25);
}

";
    }

    /**
     * Récupère les couleurs spécifiques à un thème
     */
    private function getThemeSpecificColors(string $themeName): array
    {
        $themes = [
            'blue' => [
                'accent_color' => '#17a2b8',
                'success_color' => '#28a745',
                'warning_color' => '#ffc107',
            ],
            'green' => [
                'accent_color' => '#17a2b8',
                'success_color' => '#20c997',
                'warning_color' => '#fd7e14',
            ],
            'yellow' => [
                'accent_color' => '#fd7e14',
                'success_color' => '#28a745',
                'warning_color' => '#e0a800',
            ],
            'default' => [
                'accent_color' => '#6c757d',
                'success_color' => '#28a745',
                'warning_color' => '#ffc107',
            ]
        ];

        return $themes[$themeName] ?? $themes['default'];
    }


    /**
     * Variables par défaut
     */
    private function getDefaultVariables(): array
    {
        return [
            'primary_color' => '#dc3545',
            'secondary_color' => '#6c757d',
            'primary_rgb' => '220, 53, 69',
            'secondary_rgb' => '108, 117, 125',
            'primary_light' => '#e85a6a',
            'primary_dark' => '#b02a34',
            'secondary_light' => '#868e96',
            'secondary_dark' => '#4a5156',
            'societe_name' => 'TechnoProd',
            'logo_url' => null,
            'theme_name' => 'default',
            'accent_color' => '#6c757d',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
        ];
    }

    /**
     * CSS par défaut
     */
    private function getDefaultCSS(): string
    {
        return $this->buildCSS($this->getDefaultVariables());
    }

    /**
     * Convertit une couleur hex en RGB
     */
    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "$r, $g, $b";
    }

    /**
     * Éclaircit une couleur
     */
    private function lightenColor(string $hex, float $factor): string
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = min(255, $r + ($r * $factor));
        $g = min(255, $g + ($g * $factor));
        $b = min(255, $b + ($b * $factor));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Assombrit une couleur
     */
    private function darkenColor(string $hex, float $factor): string
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, $r - ($r * $factor));
        $g = max(0, $g - ($g * $factor));
        $b = max(0, $b - ($b * $factor));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Génère les variables CSS pour un usage dans JavaScript
     */
    public function getJavaScriptVariables(?Societe $societe = null): array
    {
        $variables = $this->getThemeVariables($societe);
        
        return [
            'primaryColor' => $variables['primary_color'],
            'secondaryColor' => $variables['secondary_color'],
            'themeName' => $variables['theme_name'],
            'societeName' => $variables['societe_name'],
            'logoUrl' => $variables['logo_url'],
        ];
    }
}