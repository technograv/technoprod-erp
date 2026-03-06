<?php

namespace App\Service\Production;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Moteur d'évaluation des formules de calcul
 *
 * Permet d'évaluer des expressions mathématiques simples dans :
 * - Quantités de nomenclature
 * - Temps de production
 * - Prix de vente
 *
 * Syntaxe simple style Excel :
 * - Variables : largeur, hauteur, surface, nb_lettres, etc.
 * - Opérateurs : +, -, *, /, (), **
 * - Fonctions : max(), min(), round(), ceil(), floor(), abs(), sqrt()
 *
 * Exemples :
 * - "largeur * hauteur / 1000000" → surface en m²
 * - "(largeur + hauteur) * 2 / 1000" → périmètre en m
 * - "surface * 0.5 + 30" → temps = 0.5min/m² + 30min setup
 * - "max(largeur, hauteur)" → plus grande dimension
 * - "round(surface * 1.15, 2)" → surface avec 15% de chute arrondie
 */
class MoteurFormules
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();

        // Enregistrer les fonctions mathématiques personnalisées
        $this->registerFunctions();
    }

    /**
     * Évalue une formule avec les variables fournies
     *
     * @param string $formule Expression à évaluer (ex: "largeur * hauteur / 1000000")
     * @param array $variables Variables disponibles (ex: ['largeur' => 1200, 'hauteur' => 600])
     * @return float|int Résultat du calcul
     * @throws \Exception Si la formule est invalide
     */
    public function evaluer(string $formule, array $variables = []): float|int
    {
        if (empty(trim($formule))) {
            throw new \InvalidArgumentException('La formule ne peut pas être vide');
        }

        try {
            // Nettoyer la formule (retirer espaces superflus, convertir virgules en points)
            $formule = $this->nettoyerFormule($formule);

            // Valider les variables (doivent être des nombres)
            $variables = $this->validerVariables($variables);

            // Évaluer l'expression
            $resultat = $this->expressionLanguage->evaluate($formule, $variables);

            // Convertir en nombre si nécessaire
            if (!is_numeric($resultat)) {
                throw new \RuntimeException(
                    sprintf('Le résultat de la formule "%s" n\'est pas numérique : %s', $formule, var_export($resultat, true))
                );
            }

            return is_float($resultat) ? $resultat : (int)$resultat;

        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf('Erreur lors de l\'évaluation de la formule "%s" : %s', $formule, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Vérifie si une formule est valide sans l'évaluer
     *
     * @param string $formule Expression à vérifier
     * @param array $variables Variables de test (optionnel)
     * @return bool True si valide, false sinon
     */
    public function estValide(string $formule, array $variables = []): bool
    {
        try {
            $this->evaluer($formule, $variables);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extrait les noms de variables utilisées dans une formule
     *
     * @param string $formule Expression à analyser
     * @return array Liste des noms de variables
     */
    public function extraireVariables(string $formule): array
    {
        $formule = $this->nettoyerFormule($formule);

        // Pattern pour identifier les variables (lettres et underscores, pas de chiffres en début)
        preg_match_all('/\b([a-z_][a-z0-9_]*)\b/i', $formule, $matches);

        if (empty($matches[1])) {
            return [];
        }

        // Filtrer les fonctions et mots réservés
        $motsReserves = ['true', 'false', 'null', 'and', 'or', 'not', 'in', 'matches', 'contains', 'starts', 'ends'];
        $fonctions = ['max', 'min', 'round', 'ceil', 'floor', 'abs', 'sqrt', 'pow'];

        $variables = array_filter($matches[1], function($var) use ($motsReserves, $fonctions) {
            return !in_array(strtolower($var), array_merge($motsReserves, $fonctions));
        });

        return array_unique(array_values($variables));
    }

    /**
     * Teste une formule avec différents jeux de données
     * Utile pour déboguer ou valider une formule
     *
     * @param string $formule Expression à tester
     * @param array $jeuxDonnees Tableau de jeux de variables à tester
     * @return array Résultats pour chaque jeu de données
     */
    public function tester(string $formule, array $jeuxDonnees): array
    {
        $resultats = [];

        foreach ($jeuxDonnees as $index => $variables) {
            try {
                $resultat = $this->evaluer($formule, $variables);
                $resultats[] = [
                    'index' => $index,
                    'variables' => $variables,
                    'resultat' => $resultat,
                    'succes' => true,
                    'erreur' => null
                ];
            } catch (\Exception $e) {
                $resultats[] = [
                    'index' => $index,
                    'variables' => $variables,
                    'resultat' => null,
                    'succes' => false,
                    'erreur' => $e->getMessage()
                ];
            }
        }

        return $resultats;
    }

    /**
     * Nettoie et normalise une formule
     *
     * @param string $formule Expression brute
     * @return string Expression nettoyée
     */
    private function nettoyerFormule(string $formule): string
    {
        // Retirer espaces multiples
        $formule = preg_replace('/\s+/', ' ', trim($formule));

        // Convertir virgules en points pour les décimaux
        $formule = str_replace(',', '.', $formule);

        // Remplacer ^ par ** pour puissance (compatibilité Excel)
        $formule = str_replace('^', '**', $formule);

        return $formule;
    }

    /**
     * Valide et nettoie les variables
     *
     * @param array $variables Variables brutes
     * @return array Variables validées et converties en nombres
     * @throws \InvalidArgumentException Si une variable n'est pas numérique
     */
    private function validerVariables(array $variables): array
    {
        $validated = [];

        foreach ($variables as $nom => $valeur) {
            if (!is_numeric($valeur)) {
                throw new \InvalidArgumentException(
                    sprintf('La variable "%s" doit être numérique, "%s" fourni', $nom, gettype($valeur))
                );
            }

            // Convertir en float pour calculs précis
            $validated[$nom] = (float)$valeur;
        }

        return $validated;
    }

    /**
     * Enregistre les fonctions mathématiques personnalisées
     */
    private function registerFunctions(): void
    {
        // Fonction max()
        $this->expressionLanguage->register('max',
            function (...$args) {
                return sprintf('max(%s)', implode(', ', $args));
            },
            function ($arguments, ...$values) {
                return max(...$values);
            }
        );

        // Fonction min()
        $this->expressionLanguage->register('min',
            function (...$args) {
                return sprintf('min(%s)', implode(', ', $args));
            },
            function ($arguments, ...$values) {
                return min(...$values);
            }
        );

        // Fonction round(valeur, precision)
        $this->expressionLanguage->register('round',
            function ($valeur, $precision = 0) {
                return sprintf('round(%s, %s)', $valeur, $precision);
            },
            function ($arguments, $valeur, $precision = 0) {
                return round($valeur, $precision);
            }
        );

        // Fonction ceil()
        $this->expressionLanguage->register('ceil',
            function ($valeur) {
                return sprintf('ceil(%s)', $valeur);
            },
            function ($arguments, $valeur) {
                return ceil($valeur);
            }
        );

        // Fonction floor()
        $this->expressionLanguage->register('floor',
            function ($valeur) {
                return sprintf('floor(%s)', $valeur);
            },
            function ($arguments, $valeur) {
                return floor($valeur);
            }
        );

        // Fonction abs()
        $this->expressionLanguage->register('abs',
            function ($valeur) {
                return sprintf('abs(%s)', $valeur);
            },
            function ($arguments, $valeur) {
                return abs($valeur);
            }
        );

        // Fonction sqrt()
        $this->expressionLanguage->register('sqrt',
            function ($valeur) {
                return sprintf('sqrt(%s)', $valeur);
            },
            function ($arguments, $valeur) {
                return sqrt($valeur);
            }
        );

        // Fonction pow(base, exposant)
        $this->expressionLanguage->register('pow',
            function ($base, $exposant) {
                return sprintf('pow(%s, %s)', $base, $exposant);
            },
            function ($arguments, $base, $exposant) {
                return pow($base, $exposant);
            }
        );
    }
}
