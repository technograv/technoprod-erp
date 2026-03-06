<?php

namespace App\Service\Production;

use App\Entity\Production\ProduitCatalogue;
use App\Repository\Production\NomenclatureRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de calcul du coût de revient complet
 *
 * Calcule le coût total d'un produit catalogue configuré en incluant :
 * - Matières premières (explosion nomenclature)
 * - Temps machine (gamme de fabrication)
 * - Main d'œuvre
 * - Frais généraux
 * - Chutes et rebuts
 *
 * Utilisé pour :
 * - Calculer le prix de vente avec marge
 * - Générer les fiches de production
 * - Analyser la rentabilité
 */
class CalculCoutRevient
{
    public function __construct(
        private readonly GestionNomenclature $gestionNomenclature,
        private readonly CalculTempsProduction $calculTempsProduction,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calcule le coût de revient complet d'un produit configuré
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration choisie (dimensions, options, etc.)
     * @param float $quantite Quantité à produire
     * @return array Détail complet des coûts
     */
    public function calculer(ProduitCatalogue $produit, array $configuration, float $quantite = 1.0): array
    {
        $resultat = [
            'produit_id' => $produit->getId(),
            'produit_code' => $produit->getProduit()->getReference(),
            'produit_libelle' => $produit->getProduit()->getDesignation(),
            'quantite' => $quantite,
            'configuration' => $configuration,
            'cout_matiere' => 0,
            'cout_machine' => 0,
            'cout_main_oeuvre' => 0,
            'cout_total_unitaire' => 0,
            'cout_total_lot' => 0,
            'details_matiere' => [],
            'details_machine' => [],
            'details_temps' => [],
            'marge_defaut' => (float)$produit->getMargeDefaut() ?? 0,
            'prix_vente_suggere' => 0
        ];

        // 1. Calculer le coût matière via explosion nomenclature
        if ($produit->getNomenclature()) {
            $coutMatiere = $this->calculerCoutMatiere(
                $produit->getNomenclature(),
                $configuration,
                $quantite
            );

            $resultat['cout_matiere'] = $coutMatiere['total'];
            $resultat['details_matiere'] = $coutMatiere['details'];
        }

        // 2. Calculer le coût machine et temps via gamme
        if ($produit->getGamme()) {
            $coutMachine = $this->calculerCoutMachine(
                $produit->getGamme(),
                $configuration,
                $quantite
            );

            $resultat['cout_machine'] = $coutMachine['total'];
            $resultat['details_machine'] = $coutMachine['details'];
            $resultat['details_temps'] = $coutMachine['temps'];
        }

        // 3. Calculer coût total
        $resultat['cout_total_unitaire'] = round(
            ($resultat['cout_matiere'] + $resultat['cout_machine']) / $quantite,
            2
        );

        $resultat['cout_total_lot'] = round(
            $resultat['cout_matiere'] + $resultat['cout_machine'],
            2
        );

        // 4. Calculer prix de vente suggéré avec marge
        if ($resultat['marge_defaut'] > 0) {
            // Formule : Prix = Coût × (1 + Marge/100)
            $resultat['prix_vente_suggere'] = round(
                $resultat['cout_total_unitaire'] * (1 + $resultat['marge_defaut'] / 100),
                2
            );
        }

        return $resultat;
    }

    /**
     * Calcule le coût matière en explosant la nomenclature
     *
     * @param \App\Entity\Production\Nomenclature $nomenclature Nomenclature à exploser
     * @param array $configuration Configuration produit
     * @param float $quantite Quantité à produire
     * @return array Coût total et détails
     */
    private function calculerCoutMatiere($nomenclature, array $configuration, float $quantite): array
    {
        // Exploser la nomenclature
        $explosion = $this->gestionNomenclature->exploser($nomenclature, $configuration, $quantite);

        $details = [];
        $total = 0;

        // Parcourir les besoins consolidés
        foreach ($explosion['besoins_consolides'] as $besoin) {
            if (!$besoin['produit_id']) {
                continue;
            }

            // Récupérer le produit pour obtenir le prix d'achat
            $produit = $this->entityManager->getRepository(\App\Entity\Produit::class)
                ->find($besoin['produit_id']);

            if (!$produit) {
                continue;
            }

            $prixUnitaire = (float)$produit->getPrixAchat() ?? 0;
            $quantiteNecessaire = $besoin['quantite'];
            $coutLigne = $prixUnitaire * $quantiteNecessaire;

            $details[] = [
                'produit_id' => $besoin['produit_id'],
                'reference' => $besoin['reference'],
                'designation' => $besoin['designation'],
                'quantite' => round($quantiteNecessaire, 4),
                'unite' => $besoin['unite'],
                'prix_unitaire' => $prixUnitaire,
                'cout_total' => round($coutLigne, 2),
                'type' => $besoin['type'],
                'utilisations' => $besoin['utilisations']
            ];

            $total += $coutLigne;
        }

        return [
            'total' => round($total, 2),
            'details' => $details
        ];
    }

    /**
     * Calcule le coût machine en analysant la gamme
     *
     * @param \App\Entity\Production\Gamme $gamme Gamme de fabrication
     * @param array $configuration Configuration produit
     * @param float $quantite Quantité à produire
     * @return array Coût total, détails et temps
     */
    private function calculerCoutMachine($gamme, array $configuration, float $quantite): array
    {
        // Ajouter la quantité dans la configuration pour les formules
        $configuration['quantite_lot'] = $quantite;

        // Calculer le temps total
        $calcul = $this->calculTempsProduction->calculerTempsTotal($gamme, $configuration);

        $details = [];
        $total = 0;

        // Parcourir les postes utilisés
        foreach ($calcul['postes_utilises'] as $poste) {
            $details[] = [
                'poste_id' => $poste['id'],
                'poste_code' => $poste['code'],
                'poste_libelle' => $poste['libelle'],
                'cout_horaire' => $poste['cout_horaire'],
                'temps_minutes' => $poste['temps_total'],
                'temps_heures' => round($poste['temps_total'] / 60, 2),
                'cout_total' => $poste['cout_total'],
                'nb_operations' => $poste['nb_operations']
            ];

            $total += $poste['cout_total'];
        }

        return [
            'total' => round($total, 2),
            'details' => $details,
            'temps' => [
                'total_minutes' => $calcul['temps_total_minutes'],
                'total_heures' => $calcul['temps_total_heures'],
                'operations' => $calcul['operations']
            ]
        ];
    }

    /**
     * Calcule le prix de vente avec marge personnalisée
     *
     * @param float $coutRevient Coût de revient unitaire
     * @param float $marge Marge en pourcentage
     * @return float Prix de vente
     */
    public function calculerPrixVente(float $coutRevient, float $marge): float
    {
        if ($marge <= 0) {
            return $coutRevient;
        }

        return round($coutRevient * (1 + $marge / 100), 2);
    }

    /**
     * Calcule la marge réalisée
     *
     * @param float $prixVente Prix de vente
     * @param float $coutRevient Coût de revient
     * @return array Marge en euros et en pourcentage
     */
    public function calculerMarge(float $prixVente, float $coutRevient): array
    {
        $margeEuros = $prixVente - $coutRevient;

        $margePourcentage = 0;
        if ($coutRevient > 0) {
            $margePourcentage = ($margeEuros / $coutRevient) * 100;
        }

        return [
            'marge_euros' => round($margeEuros, 2),
            'marge_pourcentage' => round($margePourcentage, 2),
            'taux_marque' => round(($margeEuros / $prixVente) * 100, 2) // Marge commerciale
        ];
    }

    /**
     * Simule différentes quantités pour trouver le prix optimal
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration
     * @param array $quantites Quantités à tester
     * @return array Résultats pour chaque quantité
     */
    public function simulerQuantites(ProduitCatalogue $produit, array $configuration, array $quantites): array
    {
        $resultats = [];

        foreach ($quantites as $quantite) {
            $calcul = $this->calculer($produit, $configuration, $quantite);

            $resultats[] = [
                'quantite' => $quantite,
                'cout_unitaire' => $calcul['cout_total_unitaire'],
                'cout_total' => $calcul['cout_total_lot'],
                'prix_vente_unitaire' => $calcul['prix_vente_suggere'],
                'prix_vente_total' => round($calcul['prix_vente_suggere'] * $quantite, 2),
                'economie_vs_1' => null // Sera calculé après
            ];
        }

        // Calculer l'économie par rapport à quantité 1
        if (!empty($resultats)) {
            $coutUnitaire1 = $resultats[0]['cout_unitaire'];

            foreach ($resultats as &$resultat) {
                if ($resultat['quantite'] > 1) {
                    $economie = $coutUnitaire1 - $resultat['cout_unitaire'];
                    $economiePercent = ($economie / $coutUnitaire1) * 100;

                    $resultat['economie_vs_1'] = [
                        'euros' => round($economie, 2),
                        'pourcentage' => round($economiePercent, 2)
                    ];
                }
            }
        }

        return $resultats;
    }

    /**
     * Génère un rapport de rentabilité détaillé
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration
     * @param float $quantite Quantité
     * @param float $prixVentePropose Prix de vente proposé par commercial
     * @return array Rapport complet
     */
    public function genererRapportRentabilite(
        ProduitCatalogue $produit,
        array $configuration,
        float $quantite,
        float $prixVentePropose
    ): array {
        $calcul = $this->calculer($produit, $configuration, $quantite);
        $marge = $this->calculerMarge($prixVentePropose, $calcul['cout_total_unitaire']);

        $rapport = [
            'produit' => [
                'code' => $calcul['produit_code'],
                'libelle' => $calcul['produit_libelle'],
                'quantite' => $quantite
            ],
            'couts' => [
                'matiere_unitaire' => round($calcul['cout_matiere'] / $quantite, 2),
                'machine_unitaire' => round($calcul['cout_machine'] / $quantite, 2),
                'total_unitaire' => $calcul['cout_total_unitaire'],
                'total_lot' => $calcul['cout_total_lot'],
                'repartition' => [
                    'matiere_percent' => round(($calcul['cout_matiere'] / $calcul['cout_total_lot']) * 100, 1),
                    'machine_percent' => round(($calcul['cout_machine'] / $calcul['cout_total_lot']) * 100, 1)
                ]
            ],
            'prix_vente' => [
                'propose' => $prixVentePropose,
                'suggere' => $calcul['prix_vente_suggere'],
                'total_lot' => round($prixVentePropose * $quantite, 2)
            ],
            'marge' => $marge,
            'rentabilite' => [
                'acceptable' => $marge['marge_pourcentage'] >= $calcul['marge_defaut'],
                'marge_objectif' => $calcul['marge_defaut'],
                'ecart_objectif' => round($marge['marge_pourcentage'] - $calcul['marge_defaut'], 2)
            ],
            'temps_production' => $calcul['details_temps'] ?? null
        ];

        return $rapport;
    }
}
