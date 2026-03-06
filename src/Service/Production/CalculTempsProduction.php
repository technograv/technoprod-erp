<?php

namespace App\Service\Production;

use App\Entity\Production\Gamme;
use App\Entity\Production\GammeOperation;

/**
 * Service de calcul des temps de production
 *
 * Responsabilités :
 * - Calcul du temps total d'une gamme de fabrication
 * - Prise en compte des opérations parallèles
 * - Évaluation des formules de temps dynamiques
 * - Calcul des coûts horaires machine
 * - Génération du planning prévisionnel
 *
 * Exemples :
 * - Opération 1: Découpe (30min fixe)
 * - Opération 2: Impression (surface * 0.5 + 15min setup) → Parallèle avec Opération 3
 * - Opération 3: Découpe lettres (nb_lettres * 10min)     → Parallèle avec Opération 2
 * - Opération 4: Montage (45min fixe)
 *
 * Temps total séquentiel : 30 + max(impression, découpe_lettres) + 45
 */
class CalculTempsProduction
{
    public function __construct(
        private readonly MoteurFormules $moteurFormules
    ) {
    }

    /**
     * Calcule le temps total de production pour une gamme
     *
     * @param Gamme $gamme Gamme de fabrication
     * @param array $configuration Variables de configuration (dimensions, quantité, options, etc.)
     * @return array Détails du calcul avec temps total et décomposition
     */
    public function calculerTempsTotal(Gamme $gamme, array $configuration = []): array
    {
        $operations = $this->calculerOperations($gamme, $configuration);

        // Gérer les opérations parallèles
        $sequence = $this->construireSequence($operations);

        // Calculer le temps total en tenant compte des parallélismes
        $tempsTotal = $this->calculerTempsSequence($sequence);

        // Calculer le coût total machine
        $coutTotal = $this->calculerCoutTotal($operations);

        return [
            'gamme_id' => $gamme->getId(),
            'code' => $gamme->getCode(),
            'libelle' => $gamme->getLibelle(),
            'operations' => $operations,
            'sequence' => $sequence,
            'temps_total_minutes' => $tempsTotal,
            'temps_total_heures' => round($tempsTotal / 60, 2),
            'cout_total' => $coutTotal,
            'postes_utilises' => $this->extrairePostes($operations),
            'configuration_utilisee' => $configuration
        ];
    }

    /**
     * Calcule le temps pour chaque opération
     *
     * @param Gamme $gamme Gamme de fabrication
     * @param array $configuration Variables disponibles
     * @return array Opérations avec temps calculés
     */
    public function calculerOperations(Gamme $gamme, array $configuration): array
    {
        $operations = [];

        foreach ($gamme->getOperations() as $operation) {
            // Vérifier la condition d'exécution
            if (!$this->evaluerCondition($operation->getConditionExecution(), $configuration)) {
                continue;
            }

            // Calculer le temps
            $temps = $this->calculerTempsOperation($operation, $configuration);

            $operations[] = [
                'operation_id' => $operation->getId(),
                'ordre' => $operation->getOrdre(),
                'code' => $operation->getCode(),
                'libelle' => $operation->getLibelle(),
                'type_temps' => $operation->getTypeTemps(),
                'temps_fixe' => $operation->getTempsFixe(),
                'formule_temps' => $operation->getFormuleTemps(),
                'temps_calcule' => $temps,
                'parallele' => $operation->isTempsParallele(),
                'poste_travail' => [
                    'id' => $operation->getPosteTravail()->getId(),
                    'code' => $operation->getPosteTravail()->getCode(),
                    'libelle' => $operation->getPosteTravail()->getLibelle(),
                    'cout_horaire' => (float)$operation->getPosteTravail()->getCoutHoraire(),
                    'temps_setup' => $operation->getPosteTravail()->getTempsSetup(),
                    'temps_nettoyage' => $operation->getPosteTravail()->getTempsNettoyage()
                ],
                'cout_operation' => $this->calculerCoutOperation($operation, $temps),
                'controle_qualite' => $operation->isControleQualite(),
                'description_controle' => $operation->getDescriptionControle(),
                'instructions' => $operation->getInstructions(),
                'parametres_machine' => $operation->getParametresMachine()
            ];
        }

        return $operations;
    }

    /**
     * Calcule le temps d'une opération
     *
     * @param GammeOperation $operation Opération à calculer
     * @param array $configuration Variables disponibles
     * @return int Temps en minutes
     */
    private function calculerTempsOperation(GammeOperation $operation, array $configuration): int
    {
        $poste = $operation->getPosteTravail();

        // Temps de base (fixe ou formule)
        if ($operation->getTypeTemps() === GammeOperation::TYPE_TEMPS_FIXE) {
            $tempsBase = $operation->getTempsFixe();
        } else {
            // TYPE_TEMPS_FORMULE
            try {
                $tempsBase = (int)$this->moteurFormules->evaluer(
                    $operation->getFormuleTemps(),
                    $configuration
                );
            } catch (\Exception $e) {
                // Fallback sur temps fixe en cas d'erreur
                $tempsBase = $operation->getTempsFixe();
            }
        }

        // Ajouter temps de setup et nettoyage
        $tempsTotal = $tempsBase + $poste->getTempsSetup() + $poste->getTempsNettoyage();

        return max(0, $tempsTotal); // Jamais négatif
    }

    /**
     * Construit la séquence d'exécution en tenant compte des parallélismes
     *
     * @param array $operations Opérations calculées
     * @return array Séquence avec blocs séquentiels et parallèles
     */
    private function construireSequence(array $operations): array
    {
        $sequence = [];
        $blocParallele = [];

        foreach ($operations as $operation) {
            if ($operation['parallele'] && !empty($sequence)) {
                // Cette opération peut se faire en parallèle avec les suivantes
                $blocParallele[] = $operation;
            } else {
                // Finaliser le bloc parallèle précédent si existant
                if (!empty($blocParallele)) {
                    $sequence[] = [
                        'type' => 'parallele',
                        'operations' => $blocParallele,
                        'temps' => $this->calculerTempsParallele($blocParallele)
                    ];
                    $blocParallele = [];
                }

                // Ajouter l'opération séquentielle
                $sequence[] = [
                    'type' => 'sequentiel',
                    'operations' => [$operation],
                    'temps' => $operation['temps_calcule']
                ];
            }
        }

        // Finaliser le dernier bloc parallèle si existant
        if (!empty($blocParallele)) {
            $sequence[] = [
                'type' => 'parallele',
                'operations' => $blocParallele,
                'temps' => $this->calculerTempsParallele($blocParallele)
            ];
        }

        return $sequence;
    }

    /**
     * Calcule le temps d'un bloc d'opérations parallèles
     * (= temps de l'opération la plus longue)
     *
     * @param array $operations Opérations du bloc parallèle
     * @return int Temps maximal
     */
    private function calculerTempsParallele(array $operations): int
    {
        $tempsMax = 0;

        foreach ($operations as $operation) {
            $tempsMax = max($tempsMax, $operation['temps_calcule']);
        }

        return $tempsMax;
    }

    /**
     * Calcule le temps total d'une séquence
     *
     * @param array $sequence Séquence construite
     * @return int Temps total en minutes
     */
    private function calculerTempsSequence(array $sequence): int
    {
        $tempsTotal = 0;

        foreach ($sequence as $bloc) {
            $tempsTotal += $bloc['temps'];
        }

        return $tempsTotal;
    }

    /**
     * Calcule le coût d'une opération
     *
     * @param GammeOperation $operation Opération
     * @param int $tempsMinutes Temps calculé
     * @return float Coût en euros
     */
    private function calculerCoutOperation(GammeOperation $operation, int $tempsMinutes): float
    {
        $poste = $operation->getPosteTravail();

        // Utiliser la méthode calculerCoutTotal du poste
        return $poste->calculerCoutTotal($tempsMinutes);
    }

    /**
     * Calcule le coût total de toutes les opérations
     *
     * @param array $operations Opérations calculées
     * @return float Coût total en euros
     */
    private function calculerCoutTotal(array $operations): float
    {
        $total = 0;

        foreach ($operations as $operation) {
            $total += $operation['cout_operation'];
        }

        return round($total, 2);
    }

    /**
     * Extrait la liste des postes de travail utilisés
     *
     * @param array $operations Opérations calculées
     * @return array Liste des postes avec temps et coût
     */
    private function extrairePostes(array $operations): array
    {
        $postes = [];

        foreach ($operations as $operation) {
            $posteId = $operation['poste_travail']['id'];

            if (isset($postes[$posteId])) {
                $postes[$posteId]['temps_total'] += $operation['temps_calcule'];
                $postes[$posteId]['cout_total'] += $operation['cout_operation'];
                $postes[$posteId]['nb_operations']++;
            } else {
                $postes[$posteId] = [
                    'id' => $posteId,
                    'code' => $operation['poste_travail']['code'],
                    'libelle' => $operation['poste_travail']['libelle'],
                    'cout_horaire' => $operation['poste_travail']['cout_horaire'],
                    'temps_total' => $operation['temps_calcule'],
                    'cout_total' => $operation['cout_operation'],
                    'nb_operations' => 1
                ];
            }
        }

        return array_values($postes);
    }

    /**
     * Évalue une condition d'exécution
     *
     * @param string|null $condition Expression conditionnelle
     * @param array $configuration Variables disponibles
     * @return bool True si l'opération doit être exécutée
     */
    private function evaluerCondition(?string $condition, array $configuration): bool
    {
        // Pas de condition = toujours exécuter
        if (!$condition || trim($condition) === '') {
            return true;
        }

        try {
            $resultat = $this->moteurFormules->evaluer($condition, $configuration);
            return (bool)$resultat;
        } catch (\Exception $e) {
            // En cas d'erreur, exécuter l'opération par défaut
            return true;
        }
    }

    /**
     * Génère un planning prévisionnel avec dates
     *
     * @param Gamme $gamme Gamme de fabrication
     * @param array $configuration Configuration du produit
     * @param \DateTimeImmutable $dateDebut Date de début souhaitée
     * @return array Planning avec dates de début/fin pour chaque opération
     */
    public function genererPlanning(Gamme $gamme, array $configuration, \DateTimeImmutable $dateDebut): array
    {
        $calcul = $this->calculerTempsTotal($gamme, $configuration);

        $planning = [
            'date_debut' => $dateDebut,
            'date_fin_prevue' => null,
            'operations' => []
        ];

        $dateActuelle = $dateDebut;

        foreach ($calcul['sequence'] as $bloc) {
            if ($bloc['type'] === 'sequentiel') {
                // Opération séquentielle
                $operation = $bloc['operations'][0];
                $dateFin = $dateActuelle->modify(sprintf('+%d minutes', $operation['temps_calcule']));

                $planning['operations'][] = [
                    'operation_id' => $operation['operation_id'],
                    'code' => $operation['code'],
                    'libelle' => $operation['libelle'],
                    'date_debut' => $dateActuelle,
                    'date_fin' => $dateFin,
                    'duree_minutes' => $operation['temps_calcule'],
                    'type_execution' => 'sequentiel'
                ];

                $dateActuelle = $dateFin;

            } else {
                // Bloc parallèle
                $dateFin = $dateActuelle->modify(sprintf('+%d minutes', $bloc['temps']));

                foreach ($bloc['operations'] as $operation) {
                    $planning['operations'][] = [
                        'operation_id' => $operation['operation_id'],
                        'code' => $operation['code'],
                        'libelle' => $operation['libelle'],
                        'date_debut' => $dateActuelle,
                        'date_fin' => $dateFin,
                        'duree_minutes' => $operation['temps_calcule'],
                        'type_execution' => 'parallele',
                        'temps_attente' => $bloc['temps'] - $operation['temps_calcule'] // Temps avant la fin du bloc
                    ];
                }

                $dateActuelle = $dateFin;
            }
        }

        $planning['date_fin_prevue'] = $dateActuelle;
        $planning['duree_totale_minutes'] = $calcul['temps_total_minutes'];
        $planning['cout_total'] = $calcul['cout_total'];

        return $planning;
    }

    /**
     * Valide la cohérence d'une gamme
     *
     * @param Gamme $gamme Gamme à valider
     * @return array Liste des erreurs (vide si OK)
     */
    public function valider(Gamme $gamme): array
    {
        $erreurs = [];

        if ($gamme->getOperations()->isEmpty()) {
            $erreurs[] = 'La gamme ne contient aucune opération';
            return $erreurs;
        }

        foreach ($gamme->getOperations() as $index => $operation) {
            $prefixe = sprintf('Opération %d (%s)', $index + 1, $operation->getLibelle());

            // Vérifier qu'un poste de travail est défini
            if (!$operation->getPosteTravail()) {
                $erreurs[] = "$prefixe : Aucun poste de travail défini";
            }

            // Vérifier cohérence type temps / formule
            if ($operation->getTypeTemps() === GammeOperation::TYPE_TEMPS_FORMULE) {
                if (!$operation->getFormuleTemps()) {
                    $erreurs[] = "$prefixe : Type FORMULE mais pas de formule définie";
                } else {
                    // Tester la formule
                    try {
                        $variables = $this->moteurFormules->extraireVariables($operation->getFormuleTemps());
                        $testVars = array_fill_keys($variables, 100);
                        $this->moteurFormules->evaluer($operation->getFormuleTemps(), $testVars);
                    } catch (\Exception $e) {
                        $erreurs[] = "$prefixe : Formule de temps invalide - " . $e->getMessage();
                    }
                }
            }

            // Vérifier condition d'exécution si présente
            if ($operation->getConditionExecution()) {
                try {
                    $variables = $this->moteurFormules->extraireVariables($operation->getConditionExecution());
                    $testVars = array_fill_keys($variables, 1);
                    $this->moteurFormules->evaluer($operation->getConditionExecution(), $testVars);
                } catch (\Exception $e) {
                    $erreurs[] = "$prefixe : Condition d'exécution invalide - " . $e->getMessage();
                }
            }

            // Vérifier cohérence contrôle qualité
            if ($operation->isControleQualite() && !$operation->getDescriptionControle()) {
                $erreurs[] = "$prefixe : Contrôle qualité activé mais pas de description";
            }
        }

        return $erreurs;
    }
}
