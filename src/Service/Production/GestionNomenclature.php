<?php

namespace App\Service\Production;

use App\Entity\Production\Nomenclature;
use App\Entity\Production\NomenclatureLigne;
use App\Repository\Production\NomenclatureRepository;

/**
 * Service de gestion des nomenclatures (BOM)
 *
 * Responsabilités :
 * - Explosion récursive de nomenclatures multi-niveaux
 * - Calcul des quantités avec formules dynamiques
 * - Application des taux de chute
 * - Gestion des conditions d'affichage
 * - Validation de cohérence
 *
 * Exemple d'explosion :
 * Enseigne drapeau (niveau 0)
 *   ├─ Caisson aluminium (niveau 1)
 *   │   ├─ Profil alu 50x50 : 2.4m (niveau 2)
 *   │   └─ Équerres inox : 8 pièces (niveau 2)
 *   ├─ Face PMMA (niveau 1)
 *   │   └─ Plaque PMMA 3mm : 0.36m² (niveau 2)
 *   └─ Éclairage LED (niveau 1)
 *       ├─ Bande LED : 2.4m (niveau 2)
 *       └─ Transformateur 24V : 1 pièce (niveau 2)
 */
class GestionNomenclature
{
    public function __construct(
        private readonly NomenclatureRepository $nomenclatureRepository,
        private readonly MoteurFormules $moteurFormules
    ) {
    }

    /**
     * Explose une nomenclature de manière récursive
     *
     * Parcourt tous les niveaux de la nomenclature et calcule les quantités
     * réelles en fonction de la configuration du produit (dimensions, options, etc.)
     *
     * @param Nomenclature $nomenclature Nomenclature à exploser
     * @param array $configuration Variables de configuration (largeur, hauteur, options, etc.)
     * @param float $quantiteParent Quantité du niveau parent (1 par défaut)
     * @param int $niveau Niveau de profondeur actuel (0 = racine)
     * @return array Structure explosée avec quantités calculées
     */
    public function exploser(
        Nomenclature $nomenclature,
        array $configuration = [],
        float $quantiteParent = 1.0,
        int $niveau = 0
    ): array {
        $resultat = [
            'nomenclature_id' => $nomenclature->getId(),
            'code' => $nomenclature->getCode(),
            'libelle' => $nomenclature->getLibelle(),
            'niveau' => $niveau,
            'quantite_parent' => $quantiteParent,
            'lignes' => [],
            'besoins_consolides' => [] // Besoins totaux par produit
        ];

        // Enrichir la configuration avec des variables calculées
        $configuration = $this->enrichirConfiguration($configuration);

        // Parcourir chaque ligne de la nomenclature
        foreach ($nomenclature->getLignes() as $ligne) {
            // Vérifier la condition d'affichage
            if (!$this->evaluerCondition($ligne->getConditionAffichage(), $configuration)) {
                continue;
            }

            // Calculer la quantité nécessaire
            $quantite = $this->calculerQuantite($ligne, $configuration, $quantiteParent);

            // Construire le détail de la ligne
            $detailLigne = [
                'ligne_id' => $ligne->getId(),
                'ordre' => $ligne->getOrdre(),
                'type' => $ligne->getType(),
                'designation' => $ligne->getDesignation(),
                'quantite_base' => (float)$ligne->getQuantiteBase(),
                'formule_quantite' => $ligne->getFormuleQuantite(),
                'quantite_calculee' => $quantite,
                'taux_chute' => (float)$ligne->getTauxChute(),
                'quantite_avec_chute' => $this->appliquerChute($quantite, (float)$ligne->getTauxChute()),
                'unite' => $ligne->getUniteQuantite()?->getCode(),
                'produit_simple_id' => $ligne->getProduitSimple()?->getId(),
                'produit_simple_reference' => $ligne->getProduitSimple()?->getReference(),
                'nomenclature_enfant_id' => $ligne->getNomenclatureEnfant()?->getId(),
                'obligatoire' => $ligne->isObligatoire(),
                'valoriser_chutes' => $ligne->isValoriserChutes(),
                'notes' => $ligne->getNotes()
            ];

            // Si c'est un sous-ensemble, exploser récursivement
            if ($ligne->getType() === NomenclatureLigne::TYPE_SOUS_ENSEMBLE && $ligne->getNomenclatureEnfant()) {
                $detailLigne['sous_nomenclature'] = $this->exploser(
                    $ligne->getNomenclatureEnfant(),
                    $configuration,
                    $detailLigne['quantite_avec_chute'],
                    $niveau + 1
                );
            }

            $resultat['lignes'][] = $detailLigne;
        }

        // Consolider les besoins (éviter doublons si même produit apparaît plusieurs fois)
        $resultat['besoins_consolides'] = $this->consoliderBesoins($resultat['lignes']);

        return $resultat;
    }

    /**
     * Consolide les besoins en matières premières
     *
     * Regroupe les quantités par produit pour obtenir la liste
     * complète des achats/consommations nécessaires
     *
     * @param array $lignes Lignes de nomenclature explosée
     * @return array Besoins consolidés par produit
     */
    public function consoliderBesoins(array $lignes): array
    {
        $besoins = [];

        foreach ($lignes as $ligne) {
            // Récursivement consolider les sous-nomenclatures
            if (isset($ligne['sous_nomenclature'])) {
                $besoinsEnfants = $this->consoliderBesoins($ligne['sous_nomenclature']['lignes']);
                foreach ($besoinsEnfants as $produitId => $besoin) {
                    if (isset($besoins[$produitId])) {
                        $besoins[$produitId]['quantite'] += $besoin['quantite'];
                    } else {
                        $besoins[$produitId] = $besoin;
                    }
                }
            }

            // Ajouter le produit simple si présent
            if ($ligne['produit_simple_id']) {
                $produitId = $ligne['produit_simple_id'];
                $quantite = $ligne['quantite_avec_chute'];

                if (isset($besoins[$produitId])) {
                    $besoins[$produitId]['quantite'] += $quantite;
                    $besoins[$produitId]['utilisations'][] = [
                        'designation' => $ligne['designation'],
                        'quantite' => $quantite,
                        'formule' => $ligne['formule_quantite']
                    ];
                } else {
                    $besoins[$produitId] = [
                        'produit_id' => $produitId,
                        'reference' => $ligne['produit_simple_reference'],
                        'designation' => $ligne['designation'],
                        'quantite' => $quantite,
                        'unite' => $ligne['unite'],
                        'type' => $ligne['type'],
                        'utilisations' => [[
                            'designation' => $ligne['designation'],
                            'quantite' => $quantite,
                            'formule' => $ligne['formule_quantite']
                        ]]
                    ];
                }
            }
        }

        return $besoins;
    }

    /**
     * Calcule la quantité nécessaire pour une ligne
     *
     * @param NomenclatureLigne $ligne Ligne de nomenclature
     * @param array $configuration Variables disponibles
     * @param float $quantiteParent Quantité du niveau parent
     * @return float Quantité calculée
     */
    private function calculerQuantite(
        NomenclatureLigne $ligne,
        array $configuration,
        float $quantiteParent
    ): float {
        $quantiteBase = (float)$ligne->getQuantiteBase();

        // Si pas de formule, utiliser quantité de base
        if (!$ligne->getFormuleQuantite()) {
            return $quantiteBase * $quantiteParent;
        }

        // Évaluer la formule
        try {
            $quantiteCalculee = $this->moteurFormules->evaluer(
                $ligne->getFormuleQuantite(),
                $configuration
            );

            // Si quantite_base > 0, c'est un multiplicateur
            if ($quantiteBase > 0) {
                $quantiteCalculee *= $quantiteBase;
            }

            // Appliquer la quantité du parent (pour les niveaux imbriqués)
            return $quantiteCalculee * $quantiteParent;

        } catch (\Exception $e) {
            // En cas d'erreur, utiliser quantité de base
            return $quantiteBase * $quantiteParent;
        }
    }

    /**
     * Applique le taux de chute sur une quantité
     *
     * @param float $quantite Quantité de base
     * @param float $tauxChute Taux de chute en % (0-100)
     * @return float Quantité avec chute
     */
    private function appliquerChute(float $quantite, float $tauxChute): float
    {
        if ($tauxChute <= 0) {
            return $quantite;
        }

        // Formule : quantite * (1 + taux/100)
        // Exemple : 10m avec 15% de chute = 10 * 1.15 = 11.5m
        return $quantite * (1 + ($tauxChute / 100));
    }

    /**
     * Évalue une condition d'affichage
     *
     * @param string|null $condition Expression conditionnelle
     * @param array $configuration Variables disponibles
     * @return bool True si la ligne doit être affichée
     */
    private function evaluerCondition(?string $condition, array $configuration): bool
    {
        // Pas de condition = toujours afficher
        if (!$condition || trim($condition) === '') {
            return true;
        }

        try {
            // Convertir les opérateurs de comparaison en format ExpressionLanguage
            $condition = $this->normaliserCondition($condition);

            $resultat = $this->moteurFormules->evaluer($condition, $configuration);

            // Accepter 1/0, true/false, ou valeur truthy
            return (bool)$resultat;

        } catch (\Exception $e) {
            // En cas d'erreur, afficher la ligne par défaut
            return true;
        }
    }

    /**
     * Normalise une condition pour le moteur de formules
     *
     * Convertit les syntaxes courantes en format compatible :
     * - "option_eclairage == 'LED'" → "option_eclairage == 'LED'"
     * - "largeur > 1000" → "largeur > 1000"
     * - "option_fixation != 'aucune'" → "option_fixation != 'aucune'"
     *
     * @param string $condition Condition brute
     * @return string Condition normalisée
     */
    private function normaliserCondition(string $condition): string
    {
        // Pour l'instant, retourner tel quel
        // ExpressionLanguage gère déjà ==, !=, >, <, >=, <=, and, or
        return trim($condition);
    }

    /**
     * Enrichit la configuration avec des variables calculées
     *
     * Ajoute des variables dérivées utiles :
     * - surface = largeur * hauteur / 1000000 (si dimensions en mm)
     * - perimetre = (largeur + hauteur) * 2 / 1000
     * - etc.
     *
     * @param array $configuration Configuration de base
     * @return array Configuration enrichie
     */
    private function enrichirConfiguration(array $configuration): array
    {
        // Si largeur et hauteur présents, calculer surface et périmètre
        if (isset($configuration['largeur']) && isset($configuration['hauteur'])) {
            $largeur = (float)$configuration['largeur'];
            $hauteur = (float)$configuration['hauteur'];

            // Surface en m² (si dimensions en mm)
            if (!isset($configuration['surface'])) {
                $configuration['surface'] = ($largeur * $hauteur) / 1000000;
            }

            // Périmètre en m (si dimensions en mm)
            if (!isset($configuration['perimetre'])) {
                $configuration['perimetre'] = (($largeur + $hauteur) * 2) / 1000;
            }

            // Plus grande dimension
            if (!isset($configuration['dimension_max'])) {
                $configuration['dimension_max'] = max($largeur, $hauteur);
            }

            // Plus petite dimension
            if (!isset($configuration['dimension_min'])) {
                $configuration['dimension_min'] = min($largeur, $hauteur);
            }
        }

        return $configuration;
    }

    /**
     * Valide la cohérence d'une nomenclature
     *
     * Vérifie :
     * - Pas de référence circulaire
     * - Toutes les lignes ont un produit OU une nomenclature enfant
     * - Les formules sont valides
     *
     * @param Nomenclature $nomenclature Nomenclature à valider
     * @return array Liste des erreurs (vide si OK)
     */
    public function valider(Nomenclature $nomenclature): array
    {
        $erreurs = [];

        // Vérifier références circulaires
        if ($this->detecterReferenceCirculaire($nomenclature)) {
            $erreurs[] = 'Référence circulaire détectée dans la nomenclature';
        }

        // Vérifier chaque ligne
        foreach ($nomenclature->getLignes() as $index => $ligne) {
            $prefixe = sprintf('Ligne %d (%s)', $index + 1, $ligne->getDesignation());

            // Vérifier qu'il y a un produit OU une nomenclature enfant (sauf pour FOURNITURE et MAIN_OEUVRE)
            if ($ligne->getType() === NomenclatureLigne::TYPE_SOUS_ENSEMBLE) {
                if (!$ligne->getNomenclatureEnfant()) {
                    $erreurs[] = "$prefixe : Type SOUS_ENSEMBLE mais pas de nomenclature enfant définie";
                }
            } elseif ($ligne->getType() === NomenclatureLigne::TYPE_MATIERE_PREMIERE) {
                if (!$ligne->getProduitSimple()) {
                    $erreurs[] = "$prefixe : Type MATIERE_PREMIERE mais pas de produit défini";
                }
            }

            // Vérifier la formule de quantité si présente
            if ($ligne->getFormuleQuantite()) {
                try {
                    $variables = $this->moteurFormules->extraireVariables($ligne->getFormuleQuantite());
                    // Tester avec des valeurs fictives
                    $testVars = array_fill_keys($variables, 100);
                    $this->moteurFormules->evaluer($ligne->getFormuleQuantite(), $testVars);
                } catch (\Exception $e) {
                    $erreurs[] = "$prefixe : Formule invalide - " . $e->getMessage();
                }
            }

            // Vérifier la condition d'affichage si présente
            if ($ligne->getConditionAffichage()) {
                try {
                    $variables = $this->moteurFormules->extraireVariables($ligne->getConditionAffichage());
                    $testVars = array_fill_keys($variables, 1);
                    $this->moteurFormules->evaluer($ligne->getConditionAffichage(), $testVars);
                } catch (\Exception $e) {
                    $erreurs[] = "$prefixe : Condition d'affichage invalide - " . $e->getMessage();
                }
            }

            // Vérifier le taux de chute
            $tauxChute = (float)$ligne->getTauxChute();
            if ($tauxChute < 0 || $tauxChute > 100) {
                $erreurs[] = "$prefixe : Taux de chute invalide ($tauxChute%) - doit être entre 0 et 100";
            }
        }

        return $erreurs;
    }

    /**
     * Détecte une référence circulaire dans la nomenclature
     *
     * @param Nomenclature $nomenclature Nomenclature à vérifier
     * @param array $visited IDs déjà visités (pour détection cycle)
     * @return bool True si cycle détecté
     */
    private function detecterReferenceCirculaire(Nomenclature $nomenclature, array $visited = []): bool
    {
        $id = $nomenclature->getId();

        if (in_array($id, $visited)) {
            return true; // Cycle détecté
        }

        $visited[] = $id;

        // Vérifier chaque sous-nomenclature
        foreach ($nomenclature->getLignes() as $ligne) {
            if ($ligne->getType() === NomenclatureLigne::TYPE_SOUS_ENSEMBLE && $ligne->getNomenclatureEnfant()) {
                if ($this->detecterReferenceCirculaire($ligne->getNomenclatureEnfant(), $visited)) {
                    return true;
                }
            }
        }

        return false;
    }
}
