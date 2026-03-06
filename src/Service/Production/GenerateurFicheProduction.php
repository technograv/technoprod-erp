<?php

namespace App\Service\Production;

use App\Entity\Production\FicheProduction;
use App\Entity\Production\Tache;
use App\Entity\Production\ProduitCatalogue;
use App\Entity\Devis;
use App\Entity\DevisItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de génération des fiches de production
 *
 * Responsabilités :
 * - Créer une fiche de production depuis un devis validé
 * - Générer automatiquement les tâches depuis la gamme
 * - Calculer les temps et coûts prévisionnels
 * - Exploser la nomenclature
 * - Assigner les opérateurs (optionnel)
 *
 * Workflow :
 * 1. Devis signé → génération fiche(s) de production
 * 2. Explosion nomenclature + calcul temps
 * 3. Création des tâches par opération
 * 4. Validation → lancement production
 */
class GenerateurFicheProduction
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GestionNomenclature $gestionNomenclature,
        private readonly CalculTempsProduction $calculTempsProduction,
        private readonly CalculCoutRevient $calculCoutRevient
    ) {
    }

    /**
     * Génère une fiche de production depuis un devis item
     *
     * @param DevisItem $devisItem Ligne de devis
     * @param array $configuration Configuration spécifique (dimensions, options)
     * @return FicheProduction Fiche créée (non persistée)
     */
    public function genererDepuisDevisItem(DevisItem $devisItem, array $configuration = []): FicheProduction
    {
        $produit = $devisItem->getProduit();

        // Vérifier que c'est un produit catalogue
        $produitCatalogue = $this->entityManager
            ->getRepository(ProduitCatalogue::class)
            ->findOneBy(['produit' => $produit]);

        if (!$produitCatalogue) {
            throw new \RuntimeException(
                sprintf('Le produit "%s" n\'est pas un produit catalogue', $produit->getDesignation())
            );
        }

        return $this->generer(
            $produitCatalogue,
            $configuration,
            (float)$devisItem->getQuantite(),
            $devisItem->getDevis(),
            $devisItem
        );
    }

    /**
     * Génère une fiche de production
     *
     * @param ProduitCatalogue $produit Produit catalogue
     * @param array $configuration Configuration choisie
     * @param float $quantite Quantité à produire
     * @param Devis|null $devis Devis associé (optionnel)
     * @param DevisItem|null $devisItem Item de devis associé (optionnel)
     * @return FicheProduction Fiche créée (non persistée)
     */
    public function generer(
        ProduitCatalogue $produit,
        array $configuration,
        float $quantite = 1.0,
        ?Devis $devis = null,
        ?DevisItem $devisItem = null
    ): FicheProduction {
        // Générer le numéro de fiche
        $numero = $this->genererNumero();

        // Créer la fiche
        $fiche = new FicheProduction();
        $fiche->setNumero($numero);
        $fiche->setProduitCatalogue($produit);
        $fiche->setQuantite($quantite);
        $fiche->setConfiguration($configuration);
        $fiche->setStatut(FicheProduction::STATUT_BROUILLON);
        $fiche->setPriorite(50); // Priorité normale

        if ($devis) {
            $fiche->setDevis($devis);
        }

        if ($devisItem) {
            $fiche->setDevisItem($devisItem);
        }

        // 1. Exploser la nomenclature
        if ($produit->getNomenclature()) {
            $explosion = $this->gestionNomenclature->exploser(
                $produit->getNomenclature(),
                $configuration,
                $quantite
            );

            $fiche->setNomenclatureExplosee($explosion);
        }

        // 2. Calculer la gamme
        if ($produit->getGamme()) {
            $calcul = $this->calculTempsProduction->calculerTempsTotal(
                $produit->getGamme(),
                array_merge($configuration, ['quantite_lot' => $quantite])
            );

            $fiche->setGammeCalculee($calcul);

            // Générer les tâches
            $this->genererTaches($fiche, $calcul);
        }

        // 3. Calculer le coût de revient
        $coutRevient = $this->calculCoutRevient->calculer($produit, $configuration, $quantite);
        $fiche->setCoutRevient($coutRevient);

        return $fiche;
    }

    /**
     * Génère les tâches à partir du calcul de gamme
     *
     * @param FicheProduction $fiche Fiche de production
     * @param array $calcul Résultat du calcul de temps
     */
    private function genererTaches(FicheProduction $fiche, array $calcul): void
    {
        $ordre = 0;

        foreach ($calcul['operations'] as $operation) {
            $tache = new Tache();
            $tache->setFicheProduction($fiche);
            $tache->setOrdre(++$ordre);
            $tache->setCode($operation['code']);
            $tache->setLibelle($operation['libelle']);
            $tache->setTempsPrevuMinutes($operation['temps_calcule']);
            $tache->setStatut(Tache::STATUT_A_FAIRE);

            // Poste de travail
            $poste = $this->entityManager->getReference(
                \App\Entity\Production\PosteTravail::class,
                $operation['poste_travail']['id']
            );
            $tache->setPosteTravail($poste);

            // Opération de gamme source (si disponible)
            if (isset($operation['operation_id'])) {
                $gammeOperation = $this->entityManager->getReference(
                    \App\Entity\Production\GammeOperation::class,
                    $operation['operation_id']
                );
                $tache->setGammeOperation($gammeOperation);
            }

            // Instructions et paramètres machine
            if (!empty($operation['instructions'])) {
                $tache->setInstructions($operation['instructions']);
            }

            if (!empty($operation['parametres_machine'])) {
                $tache->setParametresMachine($operation['parametres_machine']);
            }

            // Contrôle qualité
            if ($operation['controle_qualite']) {
                $tache->setControleQualite(true);
            }

            $fiche->addTache($tache);
        }
    }

    /**
     * Valide et démarre une fiche de production
     *
     * @param FicheProduction $fiche Fiche à valider
     * @param string $username Utilisateur qui valide
     * @return FicheProduction Fiche validée
     */
    public function valider(FicheProduction $fiche, string $username): FicheProduction
    {
        if ($fiche->getStatut() !== FicheProduction::STATUT_BROUILLON) {
            throw new \RuntimeException('Seules les fiches en brouillon peuvent être validées');
        }

        // Vérifier que la fiche a des tâches
        if ($fiche->getTaches()->isEmpty()) {
            throw new \RuntimeException('La fiche doit contenir au moins une tâche');
        }

        $fiche->setStatut(FicheProduction::STATUT_VALIDEE);
        $fiche->setDateValidation(new \DateTimeImmutable());
        $fiche->setValidePar($username);

        return $fiche;
    }

    /**
     * Démarre la production (passe en EN_COURS)
     *
     * @param FicheProduction $fiche Fiche à démarrer
     * @return FicheProduction Fiche démarrée
     */
    public function demarrer(FicheProduction $fiche): FicheProduction
    {
        if ($fiche->getStatut() !== FicheProduction::STATUT_VALIDEE) {
            throw new \RuntimeException('Seules les fiches validées peuvent être démarrées');
        }

        $fiche->setStatut(FicheProduction::STATUT_EN_COURS);
        $fiche->setDateDebut(new \DateTimeImmutable());

        return $fiche;
    }

    /**
     * Termine une fiche de production
     *
     * @param FicheProduction $fiche Fiche à terminer
     * @return FicheProduction Fiche terminée
     */
    public function terminer(FicheProduction $fiche): FicheProduction
    {
        if ($fiche->getStatut() !== FicheProduction::STATUT_EN_COURS) {
            throw new \RuntimeException('Seules les fiches en cours peuvent être terminées');
        }

        // Vérifier que toutes les tâches sont terminées
        foreach ($fiche->getTaches() as $tache) {
            if ($tache->getStatut() !== Tache::STATUT_TERMINEE) {
                throw new \RuntimeException(
                    sprintf('La tâche "%s" n\'est pas terminée', $tache->getLibelle())
                );
            }
        }

        $fiche->setStatut(FicheProduction::STATUT_TERMINEE);
        $fiche->setDateFin(new \DateTimeImmutable());

        return $fiche;
    }

    /**
     * Annule une fiche de production
     *
     * @param FicheProduction $fiche Fiche à annuler
     * @param string|null $motif Motif d'annulation
     * @return FicheProduction Fiche annulée
     */
    public function annuler(FicheProduction $fiche, ?string $motif = null): FicheProduction
    {
        if ($fiche->getStatut() === FicheProduction::STATUT_TERMINEE) {
            throw new \RuntimeException('Une fiche terminée ne peut pas être annulée');
        }

        $fiche->setStatut(FicheProduction::STATUT_ANNULEE);

        if ($motif) {
            $notes = $fiche->getNotes() ?? '';
            $notes .= "\n\nANNULATION : " . $motif;
            $fiche->setNotes($notes);
        }

        return $fiche;
    }

    /**
     * Génère un numéro unique de fiche
     *
     * Format : FP-YYYY-NNNNN (ex: FP-2025-00042)
     *
     * @return string Numéro généré
     */
    private function genererNumero(): string
    {
        $annee = date('Y');
        $prefix = sprintf('FP-%s-', $annee);

        // Trouver le dernier numéro de l'année
        $derniereFiche = $this->entityManager
            ->getRepository(FicheProduction::class)
            ->createQueryBuilder('f')
            ->where('f.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('f.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($derniereFiche) {
            // Extraire le numéro et incrémenter
            $dernierNumero = (int)substr($derniereFiche->getNumero(), -5);
            $nouveauNumero = $dernierNumero + 1;
        } else {
            // Première fiche de l'année
            $nouveauNumero = 1;
        }

        return $prefix . str_pad((string)$nouveauNumero, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Duplique une fiche de production
     *
     * @param FicheProduction $ficheSource Fiche à dupliquer
     * @return FicheProduction Nouvelle fiche (non persistée)
     */
    public function dupliquer(FicheProduction $ficheSource): FicheProduction
    {
        $nouvelleFiche = new FicheProduction();
        $nouvelleFiche->setNumero($this->genererNumero());
        $nouvelleFiche->setProduitCatalogue($ficheSource->getProduitCatalogue());
        $nouvelleFiche->setQuantite($ficheSource->getQuantite());
        $nouvelleFiche->setConfiguration($ficheSource->getConfiguration());
        $nouvelleFiche->setNomenclatureExplosee($ficheSource->getNomenclatureExplosee());
        $nouvelleFiche->setGammeCalculee($ficheSource->getGammeCalculee());
        $nouvelleFiche->setCoutRevient($ficheSource->getCoutRevient());
        $nouvelleFiche->setStatut(FicheProduction::STATUT_BROUILLON);
        $nouvelleFiche->setPriorite($ficheSource->getPriorite());

        if ($ficheSource->getNotes()) {
            $nouvelleFiche->setNotes('DUPLIQUÉE DE ' . $ficheSource->getNumero() . "\n\n" . $ficheSource->getNotes());
        }

        // Dupliquer les tâches
        foreach ($ficheSource->getTaches() as $tacheSource) {
            $nouvelleTache = new Tache();
            $nouvelleTache->setFicheProduction($nouvelleFiche);
            $nouvelleTache->setOrdre($tacheSource->getOrdre());
            $nouvelleTache->setCode($tacheSource->getCode());
            $nouvelleTache->setLibelle($tacheSource->getLibelle());
            $nouvelleTache->setTempsPrevuMinutes($tacheSource->getTempsPrevuMinutes());
            $nouvelleTache->setStatut(Tache::STATUT_A_FAIRE);
            $nouvelleTache->setPosteTravail($tacheSource->getPosteTravail());
            $nouvelleTache->setGammeOperation($tacheSource->getGammeOperation());
            $nouvelleTache->setInstructions($tacheSource->getInstructions());
            $nouvelleTache->setParametresMachine($tacheSource->getParametresMachine());
            $nouvelleTache->setControleQualite($tacheSource->isControleQualite());

            $nouvelleFiche->addTache($nouvelleTache);
        }

        return $nouvelleFiche;
    }

    /**
     * Recalcule une fiche de production existante
     * Utile si les nomenclatures ou gammes ont changé
     *
     * @param FicheProduction $fiche Fiche à recalculer
     * @return FicheProduction Fiche mise à jour
     */
    public function recalculer(FicheProduction $fiche): FicheProduction
    {
        if ($fiche->getStatut() !== FicheProduction::STATUT_BROUILLON) {
            throw new \RuntimeException('Seules les fiches en brouillon peuvent être recalculées');
        }

        $produit = $fiche->getProduitCatalogue();
        $configuration = $fiche->getConfiguration();
        $quantite = $fiche->getQuantite();

        // Réexploser la nomenclature
        if ($produit->getNomenclature()) {
            $explosion = $this->gestionNomenclature->exploser(
                $produit->getNomenclature(),
                $configuration,
                $quantite
            );
            $fiche->setNomenclatureExplosee($explosion);
        }

        // Recalculer la gamme
        if ($produit->getGamme()) {
            $calcul = $this->calculTempsProduction->calculerTempsTotal(
                $produit->getGamme(),
                array_merge($configuration, ['quantite_lot' => $quantite])
            );
            $fiche->setGammeCalculee($calcul);

            // Supprimer les anciennes tâches et en créer de nouvelles
            foreach ($fiche->getTaches() as $tache) {
                $fiche->removeTache($tache);
            }

            $this->genererTaches($fiche, $calcul);
        }

        // Recalculer le coût de revient
        $coutRevient = $this->calculCoutRevient->calculer($produit, $configuration, $quantite);
        $fiche->setCoutRevient($coutRevient);

        return $fiche;
    }
}
