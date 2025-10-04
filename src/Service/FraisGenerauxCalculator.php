<?php

namespace App\Service;

use App\Entity\FraisGeneraux;
use App\Repository\FraisGenerauxRepository;

/**
 * Service de calcul et répartition des frais généraux
 */
class FraisGenerauxCalculator
{
    public function __construct(
        private FraisGenerauxRepository $fraisGenerauxRepository
    ) {
    }

    /**
     * Récupère les frais généraux actifs pour une période donnée
     *
     * @param string|null $periode Format YYYY-MM, null = période courante
     * @return array<FraisGeneraux>
     */
    public function getFraisActifs(?string $periode = null): array
    {
        return $this->fraisGenerauxRepository->findActifsPourPeriode($periode);
    }

    /**
     * Calcule le montant de frais généraux à ajouter pour un type "volume_devis"
     *
     * @param string|null $periode
     * @return float Montant total à ajouter au devis
     */
    public function calculerFraisParDevis(?string $periode = null): float
    {
        $fraisActifs = $this->getFraisActifs($periode);
        $total = 0.0;

        foreach ($fraisActifs as $frais) {
            if ($frais->getTypeRepartition() === 'volume_devis') {
                $montantParDevis = $frais->getMontantParDevis();
                if ($montantParDevis !== null) {
                    $total += $montantParDevis;
                }
            }
        }

        return $total;
    }

    /**
     * Calcule le montant de frais généraux par heure de main d'œuvre
     *
     * @param string|null $periode
     * @return float Coût supplémentaire par heure MO
     */
    public function calculerFraisParHeureMO(?string $periode = null): float
    {
        $fraisActifs = $this->getFraisActifs($periode);
        $total = 0.0;

        foreach ($fraisActifs as $frais) {
            if ($frais->getTypeRepartition() === 'par_heure_mo') {
                $coutParHeure = $frais->getCoutParHeureMO();
                if ($coutParHeure !== null) {
                    $total += $coutParHeure;
                }
            }
        }

        return $total;
    }

    /**
     * Calcule le coefficient global à appliquer sur le prix
     * (multiplie tous les coefficients entre eux)
     *
     * @param string|null $periode
     * @return float Coefficient multiplicateur (1.0 = pas de majoration)
     */
    public function calculerCoefficientGlobal(?string $periode = null): float
    {
        $fraisActifs = $this->getFraisActifs($periode);
        $coefficient = 1.0;

        foreach ($fraisActifs as $frais) {
            if ($frais->getTypeRepartition() === 'coefficient_global') {
                $coeffFrais = $frais->getCoefficientMajoration();
                if ($coeffFrais !== null) {
                    $coefficient *= (float)$coeffFrais;
                }
            }
        }

        return $coefficient;
    }

    /**
     * Calcule le montant total des frais en "ligne cachée"
     *
     * @param string|null $periode
     * @return float Montant total des lignes cachées
     */
    public function calculerLignesCachees(?string $periode = null): float
    {
        $fraisActifs = $this->getFraisActifs($periode);
        $total = 0.0;

        foreach ($fraisActifs as $frais) {
            if ($frais->getTypeRepartition() === 'ligne_cachee') {
                // Pour les lignes cachées, on prend le montant mensuel divisé par le volume estimé
                // C'est une approximation, pourrait être amélioré avec un vrai volume mensuel
                $total += (float)$frais->getMontantMensuel();
            }
        }

        return $total;
    }

    /**
     * Calcule le coût total d'un devis avec tous les frais généraux appliqués
     *
     * @param float $prixBase Prix de base HT du devis (somme des lignes)
     * @param float $heuresMO Nombre d'heures de main d'œuvre
     * @param string|null $periode
     * @return array Détails du calcul
     */
    public function appliquerFraisAuDevis(
        float $prixBase,
        float $heuresMO = 0.0,
        ?string $periode = null
    ): array {
        // 1. Frais par devis (montant fixe)
        $fraisParDevis = $this->calculerFraisParDevis($periode);

        // 2. Frais par heure MO
        $fraisParHeure = $this->calculerFraisParHeureMO($periode);
        $fraisMO = $fraisParHeure * $heuresMO;

        // 3. Sous-total avant coefficient
        $sousTotal = $prixBase + $fraisParDevis + $fraisMO;

        // 4. Application du coefficient global
        $coefficient = $this->calculerCoefficientGlobal($periode);
        $montantAvecCoefficient = $sousTotal * $coefficient;

        // 5. Ajout des lignes cachées
        $lignesCachees = $this->calculerLignesCachees($periode);
        $totalFinal = $montantAvecCoefficient + $lignesCachees;

        return [
            'prix_base' => round($prixBase, 2),
            'frais_par_devis' => round($fraisParDevis, 2),
            'heures_mo' => $heuresMO,
            'frais_par_heure_mo' => round($fraisParHeure, 2),
            'frais_mo_total' => round($fraisMO, 2),
            'sous_total' => round($sousTotal, 2),
            'coefficient_global' => round($coefficient, 4),
            'montant_avec_coefficient' => round($montantAvecCoefficient, 2),
            'lignes_cachees' => round($lignesCachees, 2),
            'total_final' => round($totalFinal, 2),
            'total_frais_generaux' => round($totalFinal - $prixBase, 2),
            'pourcentage_frais' => $prixBase > 0 ? round((($totalFinal - $prixBase) / $prixBase) * 100, 2) : 0
        ];
    }

    /**
     * Génère un rapport détaillé des frais généraux pour une période
     *
     * @param string|null $periode
     * @return array
     */
    public function genererRapport(?string $periode = null): array
    {
        $fraisActifs = $this->getFraisActifs($periode);

        $rapport = [
            'periode' => $periode ?? (new \DateTimeImmutable())->format('Y-m'),
            'total_mensuel' => 0.0,
            'par_type' => [
                'volume_devis' => ['count' => 0, 'montant' => 0.0, 'frais' => []],
                'par_heure_mo' => ['count' => 0, 'montant' => 0.0, 'frais' => []],
                'coefficient_global' => ['count' => 0, 'coefficient' => 1.0, 'frais' => []],
                'ligne_cachee' => ['count' => 0, 'montant' => 0.0, 'frais' => []]
            ],
            'frais_actifs_count' => count($fraisActifs)
        ];

        foreach ($fraisActifs as $frais) {
            $montant = (float)$frais->getMontantMensuel();
            $rapport['total_mensuel'] += $montant;

            $type = $frais->getTypeRepartition();
            $rapport['par_type'][$type]['count']++;
            $rapport['par_type'][$type]['frais'][] = [
                'id' => $frais->getId(),
                'libelle' => $frais->getLibelle(),
                'montant_mensuel' => $montant
            ];

            switch ($type) {
                case 'volume_devis':
                    $rapport['par_type'][$type]['montant'] += $montant;
                    break;
                case 'par_heure_mo':
                    $rapport['par_type'][$type]['montant'] += $montant;
                    break;
                case 'coefficient_global':
                    $coeff = $frais->getCoefficientMajoration();
                    if ($coeff !== null) {
                        $rapport['par_type'][$type]['coefficient'] *= (float)$coeff;
                    }
                    break;
                case 'ligne_cachee':
                    $rapport['par_type'][$type]['montant'] += $montant;
                    break;
            }
        }

        return $rapport;
    }

    /**
     * Calcule le coût horaire total (salaire + charges + frais généraux)
     * pour un taux horaire donné
     *
     * @param float $tauxHoraireBrut Taux horaire brut
     * @param float $chargesSociales Pourcentage de charges sociales (ex: 45 pour 45%)
     * @param string|null $periode
     * @return array Détails du calcul
     */
    public function calculerCoutHoraireTotal(
        float $tauxHoraireBrut,
        float $chargesSociales = 45.0,
        ?string $periode = null
    ): array {
        $chargesSocialesMontant = $tauxHoraireBrut * ($chargesSociales / 100);
        $coutHoraireSansGF = $tauxHoraireBrut + $chargesSocialesMontant;

        $fraisGFParHeure = $this->calculerFraisParHeureMO($periode);
        $coutHoraireTotal = $coutHoraireSansGF + $fraisGFParHeure;

        return [
            'taux_horaire_brut' => round($tauxHoraireBrut, 2),
            'charges_sociales_pourcent' => $chargesSociales,
            'charges_sociales_montant' => round($chargesSocialesMontant, 2),
            'cout_horaire_sans_gf' => round($coutHoraireSansGF, 2),
            'frais_generaux_par_heure' => round($fraisGFParHeure, 2),
            'cout_horaire_total' => round($coutHoraireTotal, 2),
            'majoration_totale_pourcent' => $tauxHoraireBrut > 0
                ? round((($coutHoraireTotal - $tauxHoraireBrut) / $tauxHoraireBrut) * 100, 2)
                : 0
        ];
    }
}
