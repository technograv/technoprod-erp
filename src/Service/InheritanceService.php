<?php

namespace App\Service;

use App\Entity\Societe;
use App\Entity\DocumentTemplate;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de gestion de l'héritage des paramètres entre sociétés mères et filles
 */
class InheritanceService
{
    public function __construct(
        private TenantService $tenantService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Récupère la valeur d'un paramètre avec héritage automatique
     * 
     * @param Societe|null $societe Société (null = société courante)
     * @param string $parameterKey Clé du paramètre à récupérer
     * @param mixed $defaultValue Valeur par défaut si aucune trouvée
     * @return mixed
     */
    public function getParameter(?Societe $societe, string $parameterKey, $defaultValue = null)
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return $defaultValue;
        }

        // Récupérer la valeur de la société courante
        $value = $societe->getParametreCustom($parameterKey);
        
        // Si pas de valeur et que c'est une société fille, hériter de la mère
        if ($value === null && $societe->isFille() && $societe->getSocieteParent()) {
            $value = $societe->getSocieteParent()->getParametreCustom($parameterKey);
        }
        
        return $value ?? $defaultValue;
    }

    /**
     * Récupère les couleurs avec héritage automatique
     */
    public function getColors(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return [
                'primary' => '#dc3545',
                'secondary' => '#6c757d',
            ];
        }

        // Les couleurs sont dans les propriétés directes de l'entité
        $primary = $societe->getCouleurPrimaire();
        $secondary = $societe->getCouleurSecondaire();
        $tertiary = $societe->getCouleurTertiaire();
        
        // Si pas de couleurs et société fille, hériter de la mère
        if ((!$primary || !$secondary || !$tertiary) && $societe->isFille() && $societe->getSocieteParent()) {
            $parent = $societe->getSocieteParent();
            if (!$primary) {
                $primary = $parent->getCouleurPrimaire();
            }
            if (!$secondary) {
                $secondary = $parent->getCouleurSecondaire();
            }
            if (!$tertiary) {
                $tertiary = $parent->getCouleurTertiaire();
            }
        }
        
        return [
            'primary' => $primary ?: '#dc3545',
            'secondary' => $secondary ?: '#6c757d',
            'tertiary' => $tertiary ?: '#28a745',
        ];
    }

    /**
     * Récupère le thème avec héritage automatique
     */
    public function getTheme(?Societe $societe = null): string
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        return $this->getParameter($societe, 'template_theme', 'default');
    }

    /**
     * Récupère le logo avec héritage automatique
     */
    public function getLogo(?Societe $societe = null): ?string
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        // Priorité au logo de la société
        $logo = $societe?->getLogo();
        
        // Si pas de logo et société fille, hériter de la mère
        if (!$logo && $societe?->isFille() && $societe->getSocieteParent()) {
            $logo = $societe->getSocieteParent()->getLogo();
        }
        
        return $logo;
    }

    /**
     * Récupère les préfixes de documents avec héritage
     */
    public function getDocumentPrefixes(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        $documentTypes = ['devis', 'facture', 'commande', 'avoir'];
        $prefixes = [];
        
        foreach ($documentTypes as $type) {
            $prefixes[$type] = $this->getParameter($societe, $type . '_prefix');
        }
        
        return array_filter($prefixes); // Enlever les valeurs nulles
    }

    /**
     * Récupère la signature email avec héritage
     */
    public function getEmailSignature(?Societe $societe = null): ?string
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        return $this->getParameter($societe, 'email_signature');
    }

    /**
     * Récupère tous les paramètres visuels avec héritage
     */
    public function getVisualParameters(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        return [
            'theme' => $this->getTheme($societe),
            'colors' => $this->getColors($societe),
            'logo' => $this->getLogo($societe),
            'email_signature' => $this->getEmailSignature($societe),
            'document_prefixes' => $this->getDocumentPrefixes($societe),
        ];
    }

    /**
     * Vérifie si un paramètre est défini localement (pas hérité)
     */
    public function hasLocalParameter(Societe $societe, string $parameterKey): bool
    {
        return $societe->getParametreCustom($parameterKey) !== null;
    }

    /**
     * Vérifie si un paramètre vient de l'héritage
     */
    public function isInheritedParameter(Societe $societe, string $parameterKey): bool
    {
        if ($societe->isMere()) {
            return false; // Une société mère n'hérite jamais
        }
        
        $localValue = $societe->getParametreCustom($parameterKey);
        $inheritedValue = $this->getParameter($societe, $parameterKey);
        
        return $localValue === null && $inheritedValue !== null;
    }

    /**
     * Récupère la source d'un paramètre (local, hérité, ou défaut)
     */
    public function getParameterSource(Societe $societe, string $parameterKey): string
    {
        if ($this->hasLocalParameter($societe, $parameterKey)) {
            return 'local';
        }
        
        if ($this->isInheritedParameter($societe, $parameterKey)) {
            return 'inherited';
        }
        
        return 'default';
    }

    /**
     * Récupère les informations détaillées d'un paramètre
     */
    public function getParameterInfo(Societe $societe, string $parameterKey): array
    {
        $localValue = $societe->getParametreCustom($parameterKey);
        $effectiveValue = $this->getParameter($societe, $parameterKey);
        $source = $this->getParameterSource($societe, $parameterKey);
        
        $info = [
            'key' => $parameterKey,
            'local_value' => $localValue,
            'effective_value' => $effectiveValue,
            'source' => $source,
            'is_inherited' => $source === 'inherited',
            'has_override' => $source === 'local',
        ];
        
        // Si hérité, indiquer la source
        if ($source === 'inherited' && $societe->isFille()) {
            $info['inherited_from'] = $societe->getSocieteParent()?->getNom();
        }
        
        return $info;
    }

    /**
     * Récupère les templates par défaut avec héritage
     */
    public function getDefaultTemplates(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return [];
        }
        
        // Récupérer le repository des templates
        $templateRepository = $this->entityManager->getRepository(DocumentTemplate::class);
        
        $defaults = [];
        $documentTypes = DocumentTemplate::getTypesDocuments();
        
        foreach ($documentTypes as $type => $label) {
            $template = $templateRepository->findDefaultByTypeAndSociete($type, $societe);
            if ($template) {
                $defaults[$type] = [
                    'template' => $template,
                    'is_inherited' => $template->getSociete() !== $societe,
                    'source' => $template->getSociete() ? 
                        ($template->getSociete() === $societe ? 'local' : 'inherited') : 'global'
                ];
            }
        }
        
        return $defaults;
    }

    /**
     * Récupère tous les paramètres avec informations d'héritage
     */
    public function getAllParametersInfo(Societe $societe): array
    {
        $allParameters = [];
        
        // Paramètres standards à vérifier
        $standardParameters = [
            'template_theme',
            'couleur_primaire', 
            'couleur_secondaire',
            'devis_prefix',
            'facture_prefix',
            'commande_prefix',
            'avoir_prefix',
            'email_signature',
        ];
        
        foreach ($standardParameters as $param) {
            $allParameters[$param] = $this->getParameterInfo($societe, $param);
        }
        
        // Ajouter les paramètres custom spécifiques à cette société
        $customParams = $societe->getParametresCustom() ?? [];
        foreach ($customParams as $key => $value) {
            if (!isset($allParameters[$key])) {
                $allParameters[$key] = $this->getParameterInfo($societe, $key);
            }
        }
        
        return $allParameters;
    }
}