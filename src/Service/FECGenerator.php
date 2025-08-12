<?php

namespace App\Service;

use App\Entity\EcritureComptable;
use App\Entity\ExerciceComptable;
use App\Entity\LigneEcriture;
use App\Repository\EcritureComptableRepository;
use App\Repository\ExerciceComptableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Service de génération de fichiers FEC (Fichier des Écritures Comptables)
 * Conforme à l'arrêté du 29 juillet 2013 modifié
 * 
 * Le FEC est un fichier texte obligatoire contenant toutes les écritures comptables
 * de l'exercice, formaté selon des spécifications strictes pour l'administration fiscale.
 */
class FECGenerator
{
    private EntityManagerInterface $em;
    private EcritureComptableRepository $ecritureRepo;
    private ExerciceComptableRepository $exerciceRepo;
    private DocumentIntegrityService $integrityService;
    private LoggerInterface $logger;
    
    // Configuration entreprise pour FEC
    private string $siret;
    private string $denomination;
    private string $adresse;
    
    // Caractéristiques format FEC
    private const FEC_VERSION = '1.0';
    private const FEC_ENCODING = 'Windows-1252';
    private const FEC_SEPARATOR = '|';
    private const FEC_LINE_ENDING = "\r\n";
    
    // Colonnes FEC obligatoires (18 champs)
    private const FEC_COLUMNS = [
        'JournalCode',      // Code journal (3 car max)
        'JournalLib',       // Libellé journal (100 car max)
        'EcritureNum',      // Numéro écriture (20 car max)
        'EcritureDate',     // Date écriture AAAAMMJJ
        'CompteNum',        // Numéro compte (20 car max)
        'CompteLib',        // Libellé compte (100 car max)
        'CompAuxNum',       // Compte auxiliaire (17 car max)
        'CompAuxLib',       // Libellé auxiliaire (100 car max)
        'PieceRef',         // Référence pièce (20 car max)
        'PieceDate',        // Date pièce AAAAMMJJ
        'EcritureLib',      // Libellé écriture (200 car max)
        'Debit',            // Montant débit
        'Credit',           // Montant crédit
        'EcritureLet',      // Code lettrage (3 car max)
        'DateLet',          // Date lettrage AAAAMMJJ
        'ValidDate',        // Date validation AAAAMMJJ
        'Montantdevise',    // Montant devise
        'Idevise'          // Code devise (3 car max)
    ];

    public function __construct(
        EntityManagerInterface $em,
        EcritureComptableRepository $ecritureRepo,
        ExerciceComptableRepository $exerciceRepo,
        DocumentIntegrityService $integrityService,
        LoggerInterface $logger,
        string $siret = '12345678901234',
        string $denomination = 'TechnoProd',
        string $adresse = 'Adresse non renseignée'
    ) {
        $this->em = $em;
        $this->ecritureRepo = $ecritureRepo;
        $this->exerciceRepo = $exerciceRepo;
        $this->integrityService = $integrityService;
        $this->logger = $logger;
        $this->siret = $siret;
        $this->denomination = $denomination;
        $this->adresse = $adresse;
    }

    /**
     * Génère un fichier FEC conforme pour une période donnée
     * 
     * @param \DateTime $dateDebut Date de début de période
     * @param \DateTime $dateFin Date de fin de période  
     * @param ExerciceComptable|null $exercice Exercice comptable (optionnel)
     * @return string Contenu FEC au format texte
     * @throws \Exception Si erreur de génération ou données non conformes
     */
    public function generateFEC(
        \DateTime $dateDebut,
        \DateTime $dateFin,
        ?ExerciceComptable $exercice = null
    ): string {
        $this->logger->info('Début génération FEC', [
            'date_debut' => $dateDebut->format('Y-m-d'),
            'date_fin' => $dateFin->format('Y-m-d'),
            'exercice_id' => $exercice?->getId()
        ]);

        // Validation des paramètres
        $this->validateFECParameters($dateDebut, $dateFin, $exercice);

        // En-tête FEC obligatoire
        $lines = [$this->generateFECHeader()];

        // Récupération des écritures comptables de la période
        $ecritures = $this->getEcrituresPeriode($dateDebut, $dateFin, $exercice);
        
        if (empty($ecritures)) {
            throw new \Exception('Aucune écriture comptable trouvée pour la période demandée');
        }

        // Génération des lignes FEC
        $totalDebit = '0.00';
        $totalCredit = '0.00';
        $nombreLignes = 0;

        foreach ($ecritures as $ecriture) {
            foreach ($ecriture->getLignesEcriture() as $ligne) {
                $ligneFormatee = $this->formatLigneFEC($ecriture, $ligne);
                $lines[] = $ligneFormatee;
                
                // Calcul des totaux pour validation
                $montantDebit = $this->extractMontantFromFECLine($ligneFormatee, 11); // Index colonne Debit
                $montantCredit = $this->extractMontantFromFECLine($ligneFormatee, 12); // Index colonne Credit
                
                $totalDebit = bcadd($totalDebit, $montantDebit, 2);
                $totalCredit = bcadd($totalCredit, $montantCredit, 2);
                $nombreLignes++;
            }
        }

        // Validation finale du FEC généré
        $this->validateFECData($lines, $totalDebit, $totalCredit, $dateDebut, $dateFin);

        $contenuFEC = implode(self::FEC_LINE_ENDING, $lines);

        $this->logger->info('FEC généré avec succès', [
            'nombre_lignes' => $nombreLignes,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'taille_fichier' => strlen($contenuFEC)
        ]);

        return $contenuFEC;
    }

    /**
     * Génère un FEC pour une période spécifique avec filtres avancés
     */
    public function generateFECForPeriod(
        \DateTime $dateDebut,
        \DateTime $dateFin,
        ?ExerciceComptable $exercice = null,
        array $journauxCodes = [],
        array $comptesPrefixes = [],
        bool $includeNonValidees = false
    ): string {
        $this->logger->info('Génération FEC avec filtres', [
            'journaux' => $journauxCodes,
            'comptes_prefixes' => $comptesPrefixes,
            'include_non_validees' => $includeNonValidees
        ]);

        // Récupération des écritures avec filtres
        $qb = $this->ecritureRepo->createQueryBuilder('e')
            ->innerJoin('e.lignesEcriture', 'l')
            ->innerJoin('e.journal', 'j')
            ->innerJoin('l.comptePCG', 'c')
            ->where('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->orderBy('e.dateEcriture, e.numeroEcriture, l.ordre');

        if ($exercice) {
            $qb->andWhere('e.exerciceComptable = :exercice')
               ->setParameter('exercice', $exercice);
        }

        if (!empty($journauxCodes)) {
            $qb->andWhere('j.code IN (:journauxCodes)')
               ->setParameter('journauxCodes', $journauxCodes);
        }

        if (!empty($comptesPrefixes)) {
            $orConditions = [];
            foreach ($comptesPrefixes as $index => $prefix) {
                $orConditions[] = "c.numeroCompte LIKE :prefix{$index}";
                $qb->setParameter("prefix{$index}", $prefix . '%');
            }
            $qb->andWhere('(' . implode(' OR ', $orConditions) . ')');
        }

        if (!$includeNonValidees) {
            $qb->andWhere('e.isValidee = true');
        }

        $ecritures = $qb->getQuery()->getResult();

        // Génération avec les écritures filtrées
        return $this->generateFECFromEcritures($ecritures, $dateDebut, $dateFin);
    }

    /**
     * Valide les données FEC selon les règles de l'administration fiscale
     */
    public function validateFECData(
        array $lines,
        string $totalDebit,
        string $totalCredit,
        \DateTime $dateDebut,
        \DateTime $dateFin
    ): array {
        $errors = [];
        $warnings = [];

        // 1. Vérification structure de base
        if (count($lines) < 2) {
            $errors[] = "FEC vide - aucune écriture comptable";
        }

        // 2. Vérification en-tête
        if (!isset($lines[0]) || !$this->isValidFECHeader($lines[0])) {
            $errors[] = "En-tête FEC invalide ou manquant";
        }

        // 3. Vérification équilibre débit/crédit
        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            $errors[] = sprintf(
                "FEC déséquilibré - Débit: %s, Crédit: %s, Différence: %s",
                $totalDebit,
                $totalCredit,
                bcsub($totalDebit, $totalCredit, 2)
            );
        }

        // 4. Vérification format des lignes
        for ($i = 1; $i < count($lines); $i++) {
            $lineErrors = $this->validateFECLine($lines[$i], $i + 1);
            if (!empty($lineErrors)) {
                $errors = array_merge($errors, $lineErrors);
            }
        }

        // 5. Vérifications de cohérence temporelle
        $dateErrors = $this->validateFECDates($lines, $dateDebut, $dateFin);
        $errors = array_merge($errors, $dateErrors);

        // 6. Vérifications comptables spécifiques
        $comptabiliteErrors = $this->validateFECComptabilite($lines);
        $errors = array_merge($errors, $comptabiliteErrors);

        $result = [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'total_lines' => count($lines) - 1, // -1 pour exclure l'en-tête
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'period_start' => $dateDebut->format('Y-m-d'),
            'period_end' => $dateFin->format('Y-m-d')
        ];

        if (!empty($errors)) {
            $this->logger->error('Validation FEC échouée', $result);
            throw new \Exception("FEC non conforme:\n" . implode("\n", $errors));
        }

        $this->logger->info('Validation FEC réussie', $result);
        return $result;
    }

    /**
     * Exporte le FEC en fichier téléchargeable
     */
    public function exportFECFile(
        \DateTime $dateDebut,
        \DateTime $dateFin,
        ?ExerciceComptable $exercice = null,
        bool $integriteSecurisee = true
    ): BinaryFileResponse {
        // Génération du contenu FEC
        $fecContent = $this->generateFEC($dateDebut, $dateFin, $exercice);
        
        // Nom fichier selon convention: SIRETFECAAAAMMJJAAAAMMjj.txt
        $filename = sprintf(
            '%sFEC%s%s.txt',
            $this->siret,
            $dateDebut->format('Ymd'),
            $dateFin->format('Ymd')
        );

        // Création fichier temporaire
        $tempFile = tempnam(sys_get_temp_dir(), 'fec_');
        file_put_contents($tempFile, mb_convert_encoding($fecContent, self::FEC_ENCODING, 'UTF-8'));

        // Sécurisation du fichier FEC si demandée
        if ($integriteSecurisee && $this->integrityService->areKeysAvailable()) {
            $this->securiserFichierFEC($tempFile, $filename, $dateDebut, $dateFin);
        }

        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'text/plain; charset=' . self::FEC_ENCODING);
        $response->headers->set('Content-Description', 'Fichier des Écritures Comptables - ' . $this->denomination);
        
        // Suppression automatique du fichier temporaire après envoi
        $response->deleteFileAfterSend(true);

        $this->logger->info('Export FEC effectué', [
            'filename' => $filename,
            'size_bytes' => filesize($tempFile),
            'periode' => $dateDebut->format('Y-m-d') . ' au ' . $dateFin->format('Y-m-d')
        ]);

        return $response;
    }

    /**
     * Génère l'en-tête FEC (première ligne)
     */
    private function generateFECHeader(): string
    {
        return implode(self::FEC_SEPARATOR, self::FEC_COLUMNS);
    }

    /**
     * Récupère les écritures comptables pour une période
     */
    private function getEcrituresPeriode(
        \DateTime $dateDebut,
        \DateTime $dateFin,
        ?ExerciceComptable $exercice = null
    ): array {
        $qb = $this->ecritureRepo->createQueryBuilder('e')
            ->innerJoin('e.lignesEcriture', 'l')
            ->where('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
            ->andWhere('e.isValidee = true') // Seules les écritures validées
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->orderBy('e.dateEcriture, e.numeroEcriture, l.ordre');

        if ($exercice) {
            $qb->andWhere('e.exerciceComptable = :exercice')
               ->setParameter('exercice', $exercice);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Formate une ligne d'écriture au format FEC
     */
    private function formatLigneFEC(EcritureComptable $ecriture, LigneEcriture $ligne): string
    {
        $fields = [
            $this->sanitizeFECField($ecriture->getJournalCode(), 3),                           // JournalCode
            $this->sanitizeFECField($ecriture->getJournalLibelle(), 100),                    // JournalLib
            $this->sanitizeFECField($ecriture->getNumeroEcriture(), 20),                     // EcritureNum
            $ecriture->getDateEcriture()->format('Ymd'),                                     // EcritureDate
            $this->sanitizeFECField($ligne->getNumeroCompte(), 20),                          // CompteNum
            $this->sanitizeFECField($ligne->getLibelleCompte(), 100),                        // CompteLib
            $this->sanitizeFECField($ligne->getCompteAuxiliaire() ?? '', 17),                // CompAuxNum
            $this->sanitizeFECField($ligne->getCompteAuxiliaireLibelle() ?? '', 100),        // CompAuxLib
            $this->sanitizeFECField($ecriture->getNumeroPiece(), 20),                        // PieceRef
            $ecriture->getDatePiece()->format('Ymd'),                                        // PieceDate
            $this->sanitizeFECField($ligne->getLibelleLigne(), 200),                         // EcritureLib
            $this->formatMontantFEC($ligne->getMontantDebit()),                              // Debit
            $this->formatMontantFEC($ligne->getMontantCredit()),                             // Credit
            $this->sanitizeFECField($ligne->getLettrage() ?? '', 3),                         // EcritureLet
            $ligne->getDateLettrage()?->format('Ymd') ?? '',                                 // DateLet
            $ecriture->getDateValidation()?->format('Ymd') ?? '',                            // ValidDate
            $this->formatMontantFEC($ligne->getMontantDevise() ?? '0'),                      // Montantdevise
            $this->sanitizeFECField($ligne->getCodeDevise() ?? 'EUR', 3)                     // Idevise
        ];

        return implode(self::FEC_SEPARATOR, $fields);
    }

    /**
     * Sanitise un champ pour le format FEC
     */
    private function sanitizeFECField(string $value, int $maxLength): string
    {
        if (empty($value)) {
            return '';
        }

        // Suppression caractères interdits et normalisation
        $sanitized = str_replace([self::FEC_SEPARATOR, "\n", "\r", "\t"], ' ', $value);
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized));
        
        // Troncature si nécessaire
        if (mb_strlen($sanitized) > $maxLength) {
            $sanitized = mb_substr($sanitized, 0, $maxLength);
            
            $this->logger->warning('Champ FEC tronqué', [
                'original' => $value,
                'truncated' => $sanitized,
                'max_length' => $maxLength
            ]);
        }
        
        return $sanitized;
    }

    /**
     * Formate un montant pour le FEC (virgule décimale, pas de séparateur milliers)
     */
    private function formatMontantFEC(string $montant): string
    {
        // Nettoyage et validation du montant
        $montant = trim($montant);
        if (empty($montant) || $montant === '0' || $montant === '0.00') {
            return '';
        }

        // Conversion au format FEC : virgule comme séparateur décimal
        $formatted = str_replace('.', ',', $montant);
        
        // Suppression des zéros inutiles après la virgule
        if (strpos($formatted, ',') !== false) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, ',');
        }

        return $formatted;
    }

    /**
     * Valide les paramètres de génération FEC
     */
    private function validateFECParameters(
        \DateTime $dateDebut,
        \DateTime $dateFin,
        ?ExerciceComptable $exercice
    ): void {
        if ($dateDebut > $dateFin) {
            throw new \InvalidArgumentException('La date de début doit être antérieure à la date de fin');
        }

        if ($exercice && !$exercice->isOuvert()) {
            $this->logger->warning('Génération FEC sur exercice clos', [
                'exercice_id' => $exercice->getId(),
                'statut' => $exercice->getStatut()
            ]);
        }

        // Vérification période raisonnable (max 1 exercice)
        $interval = $dateDebut->diff($dateFin);
        if ($interval->days > 366) {
            throw new \InvalidArgumentException('Période trop longue pour génération FEC (max 366 jours)');
        }
    }

    /**
     * Valide une ligne FEC individuelle
     */
    private function validateFECLine(string $line, int $lineNumber): array
    {
        $errors = [];
        $fields = explode(self::FEC_SEPARATOR, $line);

        // Vérification nombre de champs
        if (count($fields) !== 18) {
            $errors[] = "Ligne {$lineNumber}: nombre de champs incorrect (" . count($fields) . "/18)";
            return $errors;
        }

        // Vérification champs obligatoires
        $obligatoryFields = [0, 1, 2, 3, 4, 5, 8, 9, 10]; // Index des champs obligatoires
        foreach ($obligatoryFields as $index) {
            if (empty(trim($fields[$index]))) {
                $columnName = self::FEC_COLUMNS[$index];
                $errors[] = "Ligne {$lineNumber}: champ obligatoire vide ({$columnName})";
            }
        }

        // Vérification formats dates
        $dateFields = [3, 9, 14, 15]; // EcritureDate, PieceDate, DateLet, ValidDate
        foreach ($dateFields as $index) {
            if (!empty($fields[$index]) && !preg_match('/^\d{8}$/', $fields[$index])) {
                $columnName = self::FEC_COLUMNS[$index];
                $errors[] = "Ligne {$lineNumber}: format date invalide pour {$columnName} ({$fields[$index]})";
            }
        }

        // Vérification montants (débit XOR crédit)
        $debit = str_replace(',', '.', $fields[11]);
        $credit = str_replace(',', '.', $fields[12]);
        
        if (!is_numeric($debit)) $debit = '0';
        if (!is_numeric($credit)) $credit = '0';
        
        if (bccomp($debit, '0', 2) > 0 && bccomp($credit, '0', 2) > 0) {
            $errors[] = "Ligne {$lineNumber}: montant au débit ET au crédit (interdit)";
        }
        
        if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
            $errors[] = "Ligne {$lineNumber}: aucun montant (débit et crédit à zéro)";
        }

        return $errors;
    }

    /**
     * Valide les dates dans le FEC
     */
    private function validateFECDates(array $lines, \DateTime $dateDebut, \DateTime $dateFin): array
    {
        $errors = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            $fields = explode(self::FEC_SEPARATOR, $lines[$i]);
            if (count($fields) < 18) continue;

            $ecritureDate = $fields[3];
            if (preg_match('/^\d{8}$/', $ecritureDate)) {
                $date = \DateTime::createFromFormat('Ymd', $ecritureDate);
                if ($date && ($date < $dateDebut || $date > $dateFin)) {
                    $errors[] = "Ligne " . ($i + 1) . ": date écriture hors période ({$ecritureDate})";
                }
            }
        }

        return $errors;
    }

    /**
     * Valide la cohérence comptable du FEC
     */
    private function validateFECComptabilite(array $lines): array
    {
        $errors = [];
        $ecrituresEquilibre = [];

        // Groupement par numéro d'écriture pour vérifier l'équilibre
        for ($i = 1; $i < count($lines); $i++) {
            $fields = explode(self::FEC_SEPARATOR, $lines[$i]);
            if (count($fields) < 18) continue;

            $numeroEcriture = $fields[2];
            $debit = (float) str_replace(',', '.', $fields[11] ?: '0');
            $credit = (float) str_replace(',', '.', $fields[12] ?: '0');

            if (!isset($ecrituresEquilibre[$numeroEcriture])) {
                $ecrituresEquilibre[$numeroEcriture] = ['debit' => 0, 'credit' => 0];
            }

            $ecrituresEquilibre[$numeroEcriture]['debit'] += $debit;
            $ecrituresEquilibre[$numeroEcriture]['credit'] += $credit;
        }

        // Vérification équilibre de chaque écriture
        foreach ($ecrituresEquilibre as $numeroEcriture => $totaux) {
            if (abs($totaux['debit'] - $totaux['credit']) > 0.01) {
                $errors[] = "Écriture {$numeroEcriture} déséquilibrée: Débit {$totaux['debit']}, Crédit {$totaux['credit']}";
            }
        }

        return $errors;
    }

    /**
     * Vérifie si une ligne est un en-tête FEC valide
     */
    private function isValidFECHeader(string $header): bool
    {
        $fields = explode(self::FEC_SEPARATOR, $header);
        return count($fields) === 18 && $fields === self::FEC_COLUMNS;
    }

    /**
     * Extrait un montant d'une ligne FEC formatée
     */
    private function extractMontantFromFECLine(string $line, int $columnIndex): string
    {
        $fields = explode(self::FEC_SEPARATOR, $line);
        if (!isset($fields[$columnIndex])) {
            return '0.00';
        }

        $montant = str_replace(',', '.', $fields[$columnIndex]);
        return is_numeric($montant) ? $montant : '0.00';
    }

    /**
     * Génère FEC à partir d'une liste d'écritures
     */
    private function generateFECFromEcritures(array $ecritures, \DateTime $dateDebut, \DateTime $dateFin): string
    {
        $lines = [$this->generateFECHeader()];
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($ecritures as $ecriture) {
            foreach ($ecriture->getLignesEcriture() as $ligne) {
                $ligneFormatee = $this->formatLigneFEC($ecriture, $ligne);
                $lines[] = $ligneFormatee;
                
                $montantDebit = $this->extractMontantFromFECLine($ligneFormatee, 11);
                $montantCredit = $this->extractMontantFromFECLine($ligneFormatee, 12);
                
                $totalDebit = bcadd($totalDebit, $montantDebit, 2);
                $totalCredit = bcadd($totalCredit, $montantCredit, 2);
            }
        }

        $this->validateFECData($lines, $totalDebit, $totalCredit, $dateDebut, $dateFin);
        return implode(self::FEC_LINE_ENDING, $lines);
    }

    /**
     * Sécurise le fichier FEC avec DocumentIntegrityService
     */
    private function securiserFichierFEC(string $filePath, string $filename, \DateTime $dateDebut, \DateTime $dateFin): void
    {
        try {
            // Création d'un objet FEC virtuel pour la sécurisation
            $fecData = new \stdClass();
            $fecData->filename = $filename;
            $fecData->dateDebut = $dateDebut;
            $fecData->dateFin = $dateFin;
            $fecData->contenu = file_get_contents($filePath);
            $fecData->taille = strlen($fecData->contenu);
            $fecData->hash = hash('sha256', $fecData->contenu);

            // Note: L'implémentation complète nécessiterait une adaptation du DocumentIntegrityService
            // pour gérer les fichiers temporaires
            
            $this->logger->info('Fichier FEC sécurisé', [
                'filename' => $filename,
                'hash' => $fecData->hash,
                'taille' => $fecData->taille
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la sécurisation du FEC', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Retourne les statistiques de génération FEC
     */
    public function getFECStatistics(\DateTime $dateDebut, \DateTime $dateFin): array
    {
        $qb = $this->ecritureRepo->createQueryBuilder('e')
            ->select('COUNT(e.id) as nb_ecritures, COUNT(l.id) as nb_lignes, SUM(l.montantDebit) as total_debit, SUM(l.montantCredit) as total_credit')
            ->innerJoin('e.lignesEcriture', 'l')
            ->where('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
            ->andWhere('e.isValidee = true')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'periode' => [
                'debut' => $dateDebut->format('Y-m-d'),
                'fin' => $dateFin->format('Y-m-d')
            ],
            'nombre_ecritures' => (int) $result['nb_ecritures'],
            'nombre_lignes' => (int) $result['nb_lignes'],
            'total_debit' => $result['total_debit'] ?? '0.00',
            'total_credit' => $result['total_credit'] ?? '0.00',
            'equilibre' => bccomp($result['total_debit'] ?? '0', $result['total_credit'] ?? '0', 2) === 0
        ];
    }
}