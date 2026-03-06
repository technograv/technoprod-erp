<?php

namespace App\Service\Catalogue;

use App\Entity\Catalogue\ProduitCatalogue;
use App\Entity\Catalogue\RegleCompatibilite;
use App\Service\Production\MoteurFormules;

/**
 * Moteur de validation des règles de compatibilité
 *
 * Responsabilités :
 * - Valider une configuration produit selon les règles définies
 * - Identifier les incompatibilités et suggestions
 * - Appliquer les actions automatiques
 * - Générer les messages d'erreur/avertissement
 *
 * Types de règles supportées :
 * - REQUIRE : Si A sélectionné, B doit être sélectionné
 * - EXCLUDE : A et B ne peuvent pas être ensemble
 * - IF_THEN : Si condition alors action
 * - FORMULA : Contrainte par formule (ex: largeur * hauteur <= 6000000)
 *
 * Exemples :
 * - "IF option_led == 'RGB' THEN require option_controleur == 'RGB'"
 * - "EXCLUDE (taille == 'XL' AND fixation == 'murale')"
 * - "largeur * hauteur <= 6000000" (max 6m²)
 */
class MoteurRegles
{
    public function __construct(
        private readonly MoteurFormules $moteurFormules
    ) {
    }

    /**
     * Valide une configuration complète
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration choisie par le client
     * @return array Résultat de validation avec erreurs/avertissements/infos
     */
    public function valider(ProduitCatalogue $produit, array $configuration): array
    {
        $resultat = [
            'valide' => true,
            'erreurs' => [],
            'avertissements' => [],
            'infos' => [],
            'suggestions' => [],
            'actions_auto' => []
        ];

        // Récupérer les règles actives triées par priorité
        $regles = $produit->getRegles()
            ->filter(fn(RegleCompatibilite $r) => $r->isActif())
            ->toArray();

        usort($regles, fn(RegleCompatibilite $a, RegleCompatibilite $b) =>
            $b->getPriorite() <=> $a->getPriorite()
        );

        // Évaluer chaque règle
        foreach ($regles as $regle) {
            $evaluation = $this->evaluerRegle($regle, $configuration);

            if ($evaluation['violation']) {
                // Règle violée
                $message = [
                    'regle_id' => $regle->getId(),
                    'code' => $regle->getCode(),
                    'nom' => $regle->getNom(),
                    'message' => $regle->getMessageErreur(),
                    'type_regle' => $regle->getTypeRegle(),
                    'severite' => $regle->getSeverite()
                ];

                switch ($regle->getSeverite()) {
                    case RegleCompatibilite::SEVERITE_ERREUR:
                        $resultat['erreurs'][] = $message;
                        $resultat['valide'] = false;
                        break;

                    case RegleCompatibilite::SEVERITE_AVERTISSEMENT:
                        $resultat['avertissements'][] = $message;
                        break;

                    case RegleCompatibilite::SEVERITE_INFO:
                        $resultat['infos'][] = $message;
                        break;
                }

                // Collecter les actions automatiques suggérées
                if ($regle->getActionsAuto()) {
                    $actions = $regle->getActionsAuto();

                    if (isset($actions['set'])) {
                        foreach ($actions['set'] as $option => $valeur) {
                            $resultat['actions_auto'][] = [
                                'type' => 'set',
                                'option' => $option,
                                'valeur' => $valeur,
                                'raison' => $regle->getNom()
                            ];
                        }
                    }

                    if (isset($actions['suggest'])) {
                        $resultat['suggestions'] = array_merge(
                            $resultat['suggestions'],
                            array_map(fn($opt) => [
                                'option' => $opt,
                                'raison' => $regle->getNom()
                            ], $actions['suggest'])
                        );
                    }
                }
            }
        }

        return $resultat;
    }

    /**
     * Évalue une règle spécifique
     *
     * @param RegleCompatibilite $regle Règle à évaluer
     * @param array $configuration Configuration actuelle
     * @return array Résultat avec 'violation' boolean et détails
     */
    public function evaluerRegle(RegleCompatibilite $regle, array $configuration): array
    {
        try {
            $violation = false;

            switch ($regle->getTypeRegle()) {
                case RegleCompatibilite::TYPE_REQUIRE:
                    $violation = $this->evaluerRequire($regle->getExpression(), $configuration);
                    break;

                case RegleCompatibilite::TYPE_EXCLUDE:
                    $violation = $this->evaluerExclude($regle->getExpression(), $configuration);
                    break;

                case RegleCompatibilite::TYPE_IF_THEN:
                    $violation = $this->evaluerIfThen($regle->getExpression(), $configuration);
                    break;

                case RegleCompatibilite::TYPE_FORMULA:
                    $violation = $this->evaluerFormula($regle->getExpression(), $configuration);
                    break;

                default:
                    $violation = false;
            }

            return [
                'violation' => $violation,
                'regle_id' => $regle->getId(),
                'type' => $regle->getTypeRegle()
            ];

        } catch (\Exception $e) {
            // En cas d'erreur d'évaluation, considérer comme non-violée
            // mais logger l'erreur
            return [
                'violation' => false,
                'erreur' => $e->getMessage()
            ];
        }
    }

    /**
     * Évalue une règle REQUIRE
     * Format: "option_led == 'RGB' REQUIRE option_controleur == 'RGB'"
     *
     * @param string $expression Expression de la règle
     * @param array $configuration Configuration actuelle
     * @return bool True si règle violée
     */
    private function evaluerRequire(string $expression, array $configuration): bool
    {
        // Parser l'expression REQUIRE
        if (!preg_match('/(.+)\s+REQUIRE\s+(.+)/i', $expression, $matches)) {
            // Format invalide, essayer évaluation directe
            return !$this->evaluerExpression($expression, $configuration);
        }

        $condition = trim($matches[1]);
        $requirement = trim($matches[2]);

        // Si la condition est vraie, le requirement doit l'être aussi
        if ($this->evaluerExpression($condition, $configuration)) {
            // Condition vraie, vérifier le requirement
            return !$this->evaluerExpression($requirement, $configuration);
        }

        // Condition fausse, règle non applicable
        return false;
    }

    /**
     * Évalue une règle EXCLUDE
     * Format: "EXCLUDE (taille == 'XL' AND fixation == 'murale')"
     *
     * @param string $expression Expression de la règle
     * @param array $configuration Configuration actuelle
     * @return bool True si règle violée
     */
    private function evaluerExclude(string $expression, array $configuration): bool
    {
        // Retirer le préfixe EXCLUDE si présent
        $expression = preg_replace('/^EXCLUDE\s*/i', '', $expression);

        // Si l'expression est vraie, c'est une violation (exclusion non respectée)
        return $this->evaluerExpression($expression, $configuration);
    }

    /**
     * Évalue une règle IF_THEN
     * Format: "IF condition THEN action"
     *
     * @param string $expression Expression de la règle
     * @param array $configuration Configuration actuelle
     * @return bool True si règle violée
     */
    private function evaluerIfThen(string $expression, array $configuration): bool
    {
        // Parser IF ... THEN ...
        if (!preg_match('/IF\s+(.+)\s+THEN\s+(.+)/i', $expression, $matches)) {
            // Format invalide
            return false;
        }

        $condition = trim($matches[1]);
        $action = trim($matches[2]);

        // Si la condition est vraie, vérifier l'action
        if ($this->evaluerExpression($condition, $configuration)) {
            // La condition est vraie, l'action doit l'être aussi
            // L'action peut être un REQUIRE ou une expression directe
            if (stripos($action, 'REQUIRE') !== false) {
                return $this->evaluerRequire($action, $configuration);
            } else {
                return !$this->evaluerExpression($action, $configuration);
            }
        }

        // Condition fausse, règle non applicable
        return false;
    }

    /**
     * Évalue une règle FORMULA
     * Format: "largeur * hauteur <= 6000000"
     *
     * @param string $expression Expression mathématique/logique
     * @param array $configuration Configuration actuelle
     * @return bool True si règle violée (formule fausse)
     */
    private function evaluerFormula(string $expression, array $configuration): bool
    {
        // Une formule violée = formule évaluée à false
        return !$this->evaluerExpression($expression, $configuration);
    }

    /**
     * Évalue une expression booléenne
     *
     * @param string $expression Expression à évaluer
     * @param array $configuration Variables disponibles
     * @return bool Résultat de l'évaluation
     */
    private function evaluerExpression(string $expression, array $configuration): bool
    {
        // Nettoyer l'expression
        $expression = trim($expression);

        // Remplacer les chaînes entre guillemets par des valeurs comparables
        $expression = $this->preparerExpression($expression, $configuration);

        try {
            $resultat = $this->moteurFormules->evaluer($expression, $configuration);
            return (bool)$resultat;
        } catch (\Exception $e) {
            // En cas d'erreur, retourner false (non violée)
            return false;
        }
    }

    /**
     * Prépare une expression pour l'évaluation
     * Gère les comparaisons de chaînes, les booléens, etc.
     *
     * @param string $expression Expression brute
     * @param array $configuration Configuration actuelle
     * @return string Expression préparée
     */
    private function preparerExpression(string $expression, array $configuration): string
    {
        // Remplacer les valeurs de configuration textuelles par des comparaisons numériques
        // Par exemple: option_led == 'RGB' devient option_led == 1 (si 'RGB' est sélectionné)

        // Trouver les comparaisons de chaînes : variable == 'valeur'
        preg_match_all('/(\w+)\s*(==|!=)\s*[\'"]([^\'"]+)[\'"]/', $expression, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $variable = $match[1];
            $operator = $match[2];
            $valeurAttendue = $match[3];

            if (isset($configuration[$variable])) {
                $valeurActuelle = $configuration[$variable];

                // Comparer les valeurs
                $comparaison = ($operator === '==')
                    ? ($valeurActuelle == $valeurAttendue ? '1' : '0')
                    : ($valeurActuelle != $valeurAttendue ? '1' : '0');

                // Remplacer dans l'expression
                $expression = str_replace($match[0], $comparaison, $expression);
            }
        }

        // Remplacer les opérateurs logiques textuels
        $expression = preg_replace('/\bAND\b/i', ' and ', $expression);
        $expression = preg_replace('/\bOR\b/i', ' or ', $expression);
        $expression = preg_replace('/\bNOT\b/i', ' not ', $expression);

        return $expression;
    }

    /**
     * Applique automatiquement les actions suggérées
     *
     * @param array $configuration Configuration actuelle
     * @param array $actions Actions à appliquer (résultat de valider())
     * @return array Configuration modifiée
     */
    public function appliquerActionsAuto(array $configuration, array $actions): array
    {
        $nouvelles = $configuration;

        foreach ($actions as $action) {
            if ($action['type'] === 'set') {
                $nouvelles[$action['option']] = $action['valeur'];
            }
        }

        return $nouvelles;
    }

    /**
     * Suggère des modifications pour résoudre les erreurs
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration problématique
     * @return array Suggestions de corrections
     */
    public function suggererCorrections(ProduitCatalogue $produit, array $configuration): array
    {
        $validation = $this->valider($produit, $configuration);

        if ($validation['valide']) {
            return []; // Pas de corrections nécessaires
        }

        $suggestions = [];

        // Pour chaque erreur, essayer de trouver une correction
        foreach ($validation['erreurs'] as $erreur) {
            // Analyser la règle pour proposer des corrections
            // Ceci est une implémentation basique, peut être enrichie

            if (isset($erreur['type_regle'])) {
                switch ($erreur['type_regle']) {
                    case RegleCompatibilite::TYPE_REQUIRE:
                        $suggestions[] = [
                            'type' => 'require_manquant',
                            'message' => $erreur['message'],
                            'action' => 'Ajouter l\'option requise'
                        ];
                        break;

                    case RegleCompatibilite::TYPE_EXCLUDE:
                        $suggestions[] = [
                            'type' => 'exclusion_violee',
                            'message' => $erreur['message'],
                            'action' => 'Désélectionner l\'une des options incompatibles'
                        ];
                        break;

                    case RegleCompatibilite::TYPE_FORMULA:
                        $suggestions[] = [
                            'type' => 'contrainte_depassee',
                            'message' => $erreur['message'],
                            'action' => 'Modifier les dimensions ou options'
                        ];
                        break;
                }
            }
        }

        // Ajouter les suggestions d'actions auto
        foreach ($validation['actions_auto'] as $action) {
            $suggestions[] = [
                'type' => 'action_automatique',
                'message' => sprintf('Définir %s = %s', $action['option'], $action['valeur']),
                'action' => $action
            ];
        }

        return $suggestions;
    }

    /**
     * Teste si une configuration est complète et valide
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration à tester
     * @return array Résultat avec 'complet' et 'valide'
     */
    public function testerConfiguration(ProduitCatalogue $produit, array $configuration): array
    {
        // Vérifier que toutes les options obligatoires sont renseignées
        $optionsManquantes = [];

        foreach ($produit->getOptions() as $option) {
            if ($option->isObligatoire()) {
                $code = $option->getCode();
                if (!isset($configuration[$code]) || $configuration[$code] === null || $configuration[$code] === '') {
                    $optionsManquantes[] = [
                        'code' => $code,
                        'libelle' => $option->getLibelle()
                    ];
                }
            }
        }

        // Valider les règles
        $validation = $this->valider($produit, $configuration);

        return [
            'complet' => empty($optionsManquantes),
            'valide' => $validation['valide'],
            'options_manquantes' => $optionsManquantes,
            'erreurs' => $validation['erreurs'],
            'avertissements' => $validation['avertissements'],
            'peut_commander' => empty($optionsManquantes) && $validation['valide']
        ];
    }
}
