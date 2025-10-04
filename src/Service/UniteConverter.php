<?php

namespace App\Service;

use App\Entity\Unite;

/**
 * Service de conversion entre unités
 * Gère les conversions pour les unités de longueur, surface, volume, poids
 */
class UniteConverter
{
    /**
     * Convertit une quantité d'une unité source vers une unité cible
     *
     * @param float $quantite Quantité à convertir
     * @param Unite $uniteSource Unité de départ
     * @param Unite $uniteCible Unité d'arrivée
     * @return float|null Quantité convertie, null si conversion impossible
     */
    public function convertir(float $quantite, Unite $uniteSource, Unite $uniteCible): ?float
    {
        // Si même unité, pas de conversion
        if ($uniteSource->getId() === $uniteCible->getId()) {
            return $quantite;
        }

        // Si les types ne correspondent pas, conversion impossible
        if ($uniteSource->getType() !== $uniteCible->getType()) {
            return null;
        }

        // Pas de coefficient de conversion = pas de conversion possible
        $coeffSource = $uniteSource->getCoefficientConversion();
        $coeffCible = $uniteCible->getCoefficientConversion();

        if ($coeffSource === null || $coeffCible === null) {
            return null;
        }

        // Conversion via l'unité de base (SI)
        // 1. Convertir vers unité de base
        $quantiteBase = $quantite * (float)$coeffSource;

        // 2. Convertir de l'unité de base vers l'unité cible
        $quantiteConvertie = $quantiteBase / (float)$coeffCible;

        return $quantiteConvertie;
    }

    /**
     * Calcule la surface en m² à partir de dimensions
     *
     * @param float $largeur Largeur
     * @param float $hauteur Hauteur
     * @param Unite $unite Unité des dimensions (mm, m, etc.)
     * @return float|null Surface en m², null si erreur
     */
    public function calculerSurface(float $largeur, float $hauteur, Unite $unite): ?float
    {
        // Vérifier que l'unité est bien de type longueur
        if ($unite->getType() !== 'longueur') {
            return null;
        }

        $coeff = $unite->getCoefficientConversion();
        if ($coeff === null) {
            return null;
        }

        // Convertir en mètres
        $largeurM = $largeur * (float)$coeff;
        $hauteurM = $hauteur * (float)$coeff;

        // Calculer la surface en m²
        return $largeurM * $hauteurM;
    }

    /**
     * Calcule le nombre de pièces nécessaires pour couvrir une surface
     * avec gestion des chutes et marges
     *
     * @param float $surfaceTotale Surface totale à couvrir (m²)
     * @param float $largeurPiece Largeur d'une pièce
     * @param float $hauteurPiece Hauteur d'une pièce
     * @param Unite $unitePiece Unité des dimensions de la pièce
     * @param float $margeChute Marge pour chutes en pourcentage (ex: 10 pour 10%)
     * @return array ['quantite' => int, 'surface_unitaire' => float, 'chute_totale' => float]
     */
    public function calculerQuantitePourSurface(
        float $surfaceTotale,
        float $largeurPiece,
        float $hauteurPiece,
        Unite $unitePiece,
        float $margeChute = 10.0
    ): array {
        $surfaceUnitaire = $this->calculerSurface($largeurPiece, $hauteurPiece, $unitePiece);

        if ($surfaceUnitaire === null || $surfaceUnitaire <= 0) {
            return [
                'quantite' => 0,
                'surface_unitaire' => 0,
                'chute_totale' => 0,
                'surface_theorique' => 0,
                'surface_commandee' => 0
            ];
        }

        // Appliquer la marge de chute
        $surfaceAvecMarge = $surfaceTotale * (1 + $margeChute / 100);

        // Calculer le nombre de pièces nécessaires (arrondi au supérieur)
        $quantite = (int)ceil($surfaceAvecMarge / $surfaceUnitaire);

        // Calculer la surface réellement commandée
        $surfaceCommandee = $quantite * $surfaceUnitaire;

        // Calculer la chute
        $chuteTotal = $surfaceCommandee - $surfaceTotale;

        return [
            'quantite' => $quantite,
            'surface_unitaire' => round($surfaceUnitaire, 4),
            'chute_totale' => round($chuteTotal, 4),
            'surface_theorique' => round($surfaceTotale, 4),
            'surface_commandee' => round($surfaceCommandee, 4),
            'pourcentage_chute' => $surfaceTotale > 0 ? round(($chuteTotal / $surfaceTotale) * 100, 2) : 0
        ];
    }

    /**
     * Calcule le périmètre à partir de dimensions
     *
     * @param float $largeur Largeur
     * @param float $hauteur Hauteur
     * @param Unite $unite Unité des dimensions
     * @return float|null Périmètre en mètres, null si erreur
     */
    public function calculerPerimetre(float $largeur, float $hauteur, Unite $unite): ?float
    {
        if ($unite->getType() !== 'longueur') {
            return null;
        }

        $coeff = $unite->getCoefficientConversion();
        if ($coeff === null) {
            return null;
        }

        // Convertir en mètres
        $largeurM = $largeur * (float)$coeff;
        $hauteurM = $hauteur * (float)$coeff;

        // Calculer le périmètre
        return 2 * ($largeurM + $hauteurM);
    }

    /**
     * Formate une quantité avec le bon nombre de décimales selon l'unité
     *
     * @param float $quantite Quantité à formater
     * @param Unite $unite Unité
     * @return string Quantité formatée avec symbole
     */
    public function formater(float $quantite, Unite $unite): string
    {
        $decimales = $unite->getDecimalesPrix() ?? 2;
        $quantiteFormatee = number_format($quantite, $decimales, ',', ' ');
        $symbole = $unite->getSymbole() ?? $unite->getCode();

        return $quantiteFormatee . ' ' . $symbole;
    }

    /**
     * Vérifie si une conversion est possible entre deux unités
     *
     * @param Unite $uniteSource
     * @param Unite $uniteCible
     * @return bool
     */
    public function estConversionPossible(Unite $uniteSource, Unite $uniteCible): bool
    {
        // Même unité = toujours possible
        if ($uniteSource->getId() === $uniteCible->getId()) {
            return true;
        }

        // Types différents = impossible
        if ($uniteSource->getType() !== $uniteCible->getType()) {
            return false;
        }

        // Vérifier que les coefficients existent
        return $uniteSource->getCoefficientConversion() !== null
            && $uniteCible->getCoefficientConversion() !== null;
    }

    /**
     * Calcule le poids volumétrique pour le transport
     * Formule standard: (Longueur x Largeur x Hauteur) / Facteur volumétrique
     *
     * @param float $longueur Longueur en cm
     * @param float $largeur Largeur en cm
     * @param float $hauteur Hauteur en cm
     * @param int $facteurVolumetrique Facteur (généralement 5000 pour route, 6000 pour aérien)
     * @return float Poids volumétrique en kg
     */
    public function calculerPoidsVolumetrique(
        float $longueur,
        float $largeur,
        float $hauteur,
        int $facteurVolumetrique = 5000
    ): float {
        return ($longueur * $largeur * $hauteur) / $facteurVolumetrique;
    }
}
