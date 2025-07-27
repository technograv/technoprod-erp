<?php

namespace App\Service;

use App\Entity\ComptePCG;
use App\Entity\EcritureComptable;
use App\Entity\JournalComptable;
use App\Entity\User;
use App\Repository\ComptePCGRepository;
use App\Repository\EcritureComptableRepository;
use App\Repository\JournalComptableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service de gestion des journaux comptables
 * Implémente les fonctionnalités de création, configuration et gestion
 * des journaux selon les normes comptables françaises
 */
class JournalService
{
    private EntityManagerInterface $em;
    private JournalComptableRepository $journalRepo;
    private ComptePCGRepository $compteRepo;
    private EcritureComptableRepository $ecritureRepo;
    private AuditService $auditService;
    private Security $security;
    private LoggerInterface $logger;

    // Journaux obligatoires selon la réglementation française
    private const JOURNAUX_OBLIGATOIRES = [
        'VTE' => [
            'libelle' => 'Journal des ventes',
            'type' => 'VENTE',
            'obligatoire' => true,
            'format_numero' => 'VTE{YYYY}{0000}',
            'controle_numero' => true
        ],
        'ACH' => [
            'libelle' => 'Journal des achats',
            'type' => 'ACHAT',
            'obligatoire' => true,
            'format_numero' => 'ACH{YYYY}{0000}',
            'controle_numero' => true
        ],
        'BAN' => [
            'libelle' => 'Journal de banque',
            'type' => 'BANQUE',
            'obligatoire' => true,
            'format_numero' => 'BAN{YYYY}{0000}',
            'controle_numero' => true
        ],
        'CAI' => [
            'libelle' => 'Journal de caisse',
            'type' => 'CAISSE',
            'obligatoire' => true,
            'format_numero' => 'CAI{YYYY}{0000}',
            'controle_numero' => true
        ],
        'OD' => [
            'libelle' => 'Journal d\'opérations diverses',
            'type' => 'OD_GENERALES',
            'obligatoire' => true,
            'format_numero' => 'OD{YYYY}{0000}',
            'controle_numero' => true
        ],
        'AN' => [
            'libelle' => 'Journal à nouveaux',
            'type' => 'A_NOUVEAUX',
            'obligatoire' => false,
            'format_numero' => 'AN{YYYY}{0000}',
            'controle_numero' => false
        ]
    ];

    public function __construct(
        EntityManagerInterface $em,
        JournalComptableRepository $journalRepo,
        ComptePCGRepository $compteRepo,
        EcritureComptableRepository $ecritureRepo,
        AuditService $auditService,
        Security $security,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->journalRepo = $journalRepo;
        $this->compteRepo = $compteRepo;
        $this->ecritureRepo = $ecritureRepo;
        $this->auditService = $auditService;
        $this->security = $security;
        $this->logger = $logger;
    }

    /**
     * Initialise les journaux comptables obligatoires
     */
    public function initialiserJournauxObligatoires(): array
    {
        try {
            $this->em->beginTransaction();
            
            $journauxCrees = [];
            $journauxExistants = [];
            
            foreach (self::JOURNAUX_OBLIGATOIRES as $code => $config) {
                // Vérifier si le journal existe déjà
                $journalExistant = $this->journalRepo->findOneBy(['code' => $code]);
                
                if ($journalExistant) {
                    $journauxExistants[] = $code;
                    continue;
                }
                
                // Créer le nouveau journal
                $journal = new JournalComptable();
                $journal->setCode($code);
                $journal->setLibelle($config['libelle']);
                $journal->setType($config['type']);
                $journal->setIsActif(true);
                $journal->setIsObligatoire($config['obligatoire']);
                $journal->setFormatNumeroEcriture($config['format_numero']);
                $journal->setIsControleNumeroEcriture($config['controle_numero']);
                $journal->setCreatedBy($this->security->getUser());
                
                $this->em->persist($journal);
                $journauxCrees[] = $code;
                
                // Audit
                $this->auditService->logEntityChange(
                    $journal,
                    'CREATE',
                    [],
                    [
                        'code' => $code,
                        'libelle' => $config['libelle'],
                        'type' => $config['type'],
                        'obligatoire' => $config['obligatoire']
                    ],
                    'Initialisation des journaux obligatoires'
                );
            }
            
            $this->em->flush();
            $this->em->commit();
            
            $this->logger->info('Journaux obligatoires initialisés', [
                'journaux_crees' => count($journauxCrees),
                'journaux_existants' => count($journauxExistants)
            ]);
            
            return [
                'success' => true,
                'journaux_crees' => $journauxCrees,
                'journaux_existants' => $journauxExistants,
                'total_journaux' => count(self::JOURNAUX_OBLIGATOIRES),
                'message' => sprintf(
                    'Journaux initialisés : %d créés, %d existants',
                    count($journauxCrees),
                    count($journauxExistants)
                )
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur initialisation journaux', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erreur lors de l\'initialisation des journaux'
            ];
        }
    }

    /**
     * Crée un nouveau journal comptable
     */
    public function creerJournal(
        string $code,
        string $libelle,
        string $type,
        array $options = []
    ): array {
        try {
            // Validation du code journal
            if (!$this->validerCodeJournal($code)) {
                return [
                    'success' => false,
                    'error' => 'Code journal invalide (3 caractères maximum, lettres uniquement)'
                ];
            }
            
            // Vérifier l'unicité
            if ($this->journalRepo->findOneBy(['code' => $code])) {
                return [
                    'success' => false,
                    'error' => "Le journal {$code} existe déjà"
                ];
            }
            
            // Validation du type
            if (!$this->validerTypeJournal($type)) {
                return [
                    'success' => false,
                    'error' => 'Type de journal invalide'
                ];
            }
            
            $this->em->beginTransaction();
            
            $journal = new JournalComptable();
            $journal->setCode(strtoupper($code));
            $journal->setLibelle($libelle);
            $journal->setType($type);
            $journal->setIsActif($options['isActif'] ?? true);
            $journal->setIsObligatoire($options['isObligatoire'] ?? false);
            $journal->setIsControleNumeroEcriture($options['controleNumero'] ?? true);
            $journal->setFormatNumeroEcriture($options['formatNumero'] ?? $this->genererFormatDefaut($code));
            $journal->setCreatedBy($this->security->getUser());
            
            // Compte de contrepartie par défaut
            if (isset($options['compteContrepartieDefaut'])) {
                $compte = $this->compteRepo->find($options['compteContrepartieDefaut']);
                if ($compte) {
                    $journal->setCompteContrepartieDefaut($compte);
                }
            }
            
            // Paramètres additionnels
            if (isset($options['parametres'])) {
                $journal->setParametres($options['parametres']);
            }
            
            $this->em->persist($journal);
            $this->em->flush();
            
            // Audit
            $this->auditService->logEntityChange(
                $journal,
                'CREATE',
                [],
                [
                    'code' => $code,
                    'libelle' => $libelle,
                    'type' => $type,
                    'actif' => $journal->isIsActif()
                ],
                'Création d\'un nouveau journal comptable'
            );
            
            $this->em->commit();
            
            $this->logger->info('Journal comptable créé', [
                'code' => $code,
                'libelle' => $libelle,
                'type' => $type
            ]);
            
            return [
                'success' => true,
                'journal' => $journal,
                'message' => "Journal {$code} créé avec succès"
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur création journal', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Modifie un journal existant
     */
    public function modifierJournal(string $code, array $modifications): array
    {
        try {
            $journal = $this->journalRepo->findOneBy(['code' => $code]);
            
            if (!$journal) {
                return [
                    'success' => false,
                    'error' => "Journal {$code} introuvable"
                ];
            }
            
            // Vérifier si le journal peut être modifié
            if ($journal->isIsObligatoire() && isset($modifications['isActif']) && !$modifications['isActif']) {
                return [
                    'success' => false,
                    'error' => "Impossible de désactiver un journal obligatoire"
                ];
            }
            
            // Sauvegarder les anciennes valeurs pour l'audit
            $anciennesValeurs = [
                'libelle' => $journal->getLibelle(),
                'type' => $journal->getType(),
                'isActif' => $journal->isIsActif(),
                'formatNumeroEcriture' => $journal->getFormatNumeroEcriture(),
                'isControleNumeroEcriture' => $journal->isIsControleNumeroEcriture()
            ];
            
            $this->em->beginTransaction();
            
            // Appliquer les modifications
            foreach ($modifications as $propriete => $valeur) {
                switch ($propriete) {
                    case 'libelle':
                        $journal->setLibelle($valeur);
                        break;
                    case 'type':
                        if ($this->validerTypeJournal($valeur)) {
                            $journal->setType($valeur);
                        }
                        break;
                    case 'isActif':
                        $journal->setIsActif((bool)$valeur);
                        break;
                    case 'formatNumeroEcriture':
                        $journal->setFormatNumeroEcriture($valeur);
                        break;
                    case 'isControleNumeroEcriture':
                        $journal->setIsControleNumeroEcriture((bool)$valeur);
                        break;
                    case 'compteContrepartieDefaut':
                        $compte = $this->compteRepo->find($valeur);
                        $journal->setCompteContrepartieDefaut($compte);
                        break;
                    case 'parametres':
                        $journal->setParametres($valeur);
                        break;
                }
            }
            
            $journal->setUpdatedBy($this->security->getUser());
            $this->em->flush();
            
            // Audit
            $nouvellesValeurs = [
                'libelle' => $journal->getLibelle(),
                'type' => $journal->getType(),
                'isActif' => $journal->isIsActif(),
                'formatNumeroEcriture' => $journal->getFormatNumeroEcriture(),
                'isControleNumeroEcriture' => $journal->isIsControleNumeroEcriture()
            ];
            
            $this->auditService->logEntityChange(
                $journal,
                'UPDATE',
                $anciennesValeurs,
                $nouvellesValeurs,
                'Modification du journal comptable'
            );
            
            $this->em->commit();
            
            $this->logger->info('Journal comptable modifié', [
                'code' => $code,
                'modifications' => array_keys($modifications)
            ]);
            
            return [
                'success' => true,
                'journal' => $journal,
                'message' => "Journal {$code} modifié avec succès"
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur modification journal', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Génère le prochain numéro d'écriture pour un journal
     */
    public function genererProchainNumeroEcriture(string $codeJournal): array
    {
        try {
            $journal = $this->journalRepo->findOneBy(['code' => $codeJournal]);
            
            if (!$journal) {
                return [
                    'success' => false,
                    'error' => "Journal {$codeJournal} introuvable"
                ];
            }
            
            if (!$journal->isIsActif()) {
                return [
                    'success' => false,
                    'error' => "Journal {$codeJournal} inactif"
                ];
            }
            
            $this->em->beginTransaction();
            
            $numeroEcriture = $journal->generateNextNumeroEcriture();
            
            $this->em->flush(); // Sauvegarde du nouveau dernier numéro
            $this->em->commit();
            
            $this->logger->info('Numéro d\'écriture généré', [
                'journal' => $codeJournal,
                'numero' => $numeroEcriture
            ]);
            
            return [
                'success' => true,
                'numero_ecriture' => $numeroEcriture,
                'journal' => $journal
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur génération numéro écriture', [
                'journal' => $codeJournal,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Réinitialise la numérotation d'un journal (nouvelle année par exemple)
     */
    public function reinitialiserNumerotation(string $codeJournal, string $motif = null): array
    {
        try {
            $journal = $this->journalRepo->findOneBy(['code' => $codeJournal]);
            
            if (!$journal) {
                return [
                    'success' => false,
                    'error' => "Journal {$codeJournal} introuvable"
                ];
            }
            
            $this->em->beginTransaction();
            
            $ancienNumero = $journal->getDernierNumeroEcriture();
            $journal->setDernierNumeroEcriture(0);
            $journal->setUpdatedBy($this->security->getUser());
            
            $this->em->flush();
            
            // Audit
            $this->auditService->logEntityChange(
                $journal,
                'UPDATE',
                ['dernierNumeroEcriture' => $ancienNumero],
                ['dernierNumeroEcriture' => 0],
                $motif ?: "Réinitialisation numérotation journal {$codeJournal}"
            );
            
            $this->em->commit();
            
            $this->logger->info('Numérotation journal réinitialisée', [
                'journal' => $codeJournal,
                'ancien_numero' => $ancienNumero,
                'motif' => $motif
            ]);
            
            return [
                'success' => true,
                'message' => "Numérotation du journal {$codeJournal} réinitialisée",
                'ancien_numero' => $ancienNumero
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie la séquence de numérotation d'un journal
     */
    public function verifierSequenceNumerotation(string $codeJournal): array
    {
        try {
            $journal = $this->journalRepo->findOneBy(['code' => $codeJournal]);
            
            if (!$journal) {
                return [
                    'success' => false,
                    'error' => "Journal {$codeJournal} introuvable"
                ];
            }
            
            // Récupérer toutes les écritures du journal ordonnées par numéro
            $ecritures = $this->ecritureRepo->createQueryBuilder('e')
                ->where('e.journal = :journal')
                ->setParameter('journal', $journal)
                ->orderBy('e.numeroEcriture', 'ASC')
                ->getQuery()
                ->getResult();
            
            $numerosManquants = [];
            $doublons = [];
            $numerosInvalides = [];
            $numeroPrecedent = null;
            $numerosTraites = [];
            
            foreach ($ecritures as $ecriture) {
                $numeroEcriture = $ecriture->getNumeroEcriture();
                
                // Vérifier le format
                if (!$this->verifierFormatNumero($numeroEcriture, $journal->getFormatNumeroEcriture())) {
                    $numerosInvalides[] = $numeroEcriture;
                }
                
                // Vérifier les doublons
                if (in_array($numeroEcriture, $numerosTraites)) {
                    $doublons[] = $numeroEcriture;
                } else {
                    $numerosTraites[] = $numeroEcriture;
                }
                
                // Vérifier la séquence (si contrôle activé)
                if ($journal->isIsControleNumeroEcriture() && $numeroPrecedent !== null) {
                    $numeroActuelInt = $this->extraireNumeroSequence($numeroEcriture);
                    $numeroPrecedentInt = $this->extraireNumeroSequence($numeroPrecedent);
                    
                    if ($numeroActuelInt && $numeroPrecedentInt) {
                        for ($i = $numeroPrecedentInt + 1; $i < $numeroActuelInt; $i++) {
                            $numeroManquant = $this->construireNumeroFromSequence($i, $journal->getFormatNumeroEcriture());
                            $numerosManquants[] = $numeroManquant;
                        }
                    }
                }
                
                $numeroPrecedent = $numeroEcriture;
            }
            
            $estValide = empty($numerosManquants) && empty($doublons) && empty($numerosInvalides);
            
            return [
                'success' => true,
                'journal' => $codeJournal,
                'valide' => $estValide,
                'total_ecritures' => count($ecritures),
                'anomalies' => [
                    'numeros_manquants' => $numerosManquants,
                    'doublons' => $doublons,
                    'numeros_invalides' => $numerosInvalides
                ],
                'dernier_numero_utilise' => $numeroPrecedent,
                'prochain_numero' => $journal->getDernierNumeroEcriture() + 1,
                'verifie_le' => new \DateTime()
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur vérification séquence', [
                'journal' => $codeJournal,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Retourne les statistiques d'un journal
     */
    public function getStatistiquesJournal(string $codeJournal, ?\DateTimeInterface $dateDebut = null, ?\DateTimeInterface $dateFin = null): array
    {
        try {
            $journal = $this->journalRepo->findOneBy(['code' => $codeJournal]);
            
            if (!$journal) {
                return [
                    'success' => false,
                    'error' => "Journal {$codeJournal} introuvable"
                ];
            }
            
            // Période par défaut : année courante
            $dateDebut = $dateDebut ?? new \DateTime('first day of january this year');
            $dateFin = $dateFin ?? new \DateTime('last day of december this year');
            
            $qb = $this->em->createQueryBuilder();
            
            // Nombre d'écritures
            $nombreEcritures = $qb->select('COUNT(e.id)')
                ->from(EcritureComptable::class, 'e')
                ->where('e.journal = :journal')
                ->andWhere('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
                ->setParameter('journal', $journal)
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin)
                ->getQuery()
                ->getSingleScalarResult();
            
            // Statistiques des lignes d'écriture
            $statsLignes = $qb->select([
                'COUNT(l.id) as nombre_lignes',
                'COALESCE(SUM(l.montantDebit), 0) as total_debit',
                'COALESCE(SUM(l.montantCredit), 0) as total_credit'
            ])
                ->from('App\Entity\LigneEcriture', 'l')
                ->join('l.ecriture', 'e')
                ->where('e.journal = :journal')
                ->andWhere('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
                ->setParameter('journal', $journal)
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin)
                ->getQuery()
                ->getSingleResult();
            
            // Écritures validées vs non validées
            $ecrituresValidees = $qb->select('COUNT(e.id)')
                ->from(EcritureComptable::class, 'e')
                ->where('e.journal = :journal')
                ->andWhere('e.dateEcriture BETWEEN :dateDebut AND :dateFin')
                ->andWhere('e.isValidee = true')
                ->setParameter('journal', $journal)
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin)
                ->getQuery()
                ->getSingleScalarResult();
            
            $equilibre = bccomp($statsLignes['total_debit'], $statsLignes['total_credit'], 2) === 0;
            
            return [
                'success' => true,
                'journal' => [
                    'code' => $journal->getCode(),
                    'libelle' => $journal->getLibelle(),
                    'type' => $journal->getType(),
                    'actif' => $journal->isIsActif()
                ],
                'periode' => [
                    'debut' => $dateDebut,
                    'fin' => $dateFin
                ],
                'statistiques' => [
                    'nombre_ecritures' => $nombreEcritures,
                    'nombre_lignes' => $statsLignes['nombre_lignes'],
                    'ecritures_validees' => $ecrituresValidees,
                    'ecritures_non_validees' => $nombreEcritures - $ecrituresValidees,
                    'total_debit' => $statsLignes['total_debit'],
                    'total_credit' => $statsLignes['total_credit'],
                    'equilibre' => $equilibre,
                    'dernier_numero_utilise' => $journal->getDernierNumeroEcriture()
                ],
                'genere_le' => new \DateTime()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Liste tous les journaux avec leurs informations
     */
    public function listerJournaux(bool $actifsUniquement = true): array
    {
        $criteres = $actifsUniquement ? ['isActif' => true] : [];
        $journaux = $this->journalRepo->findBy($criteres, ['code' => 'ASC']);
        
        $listeJournaux = [];
        
        foreach ($journaux as $journal) {
            $stats = $journal->getStatistiques();
            
            $listeJournaux[] = [
                'code' => $journal->getCode(),
                'libelle' => $journal->getLibelle(),
                'type' => $journal->getType(),
                'actif' => $journal->isIsActif(),
                'obligatoire' => $journal->isIsObligatoire(),
                'controle_numero' => $journal->isIsControleNumeroEcriture(),
                'format_numero' => $journal->getFormatNumeroEcriture(),
                'dernier_numero' => $journal->getDernierNumeroEcriture(),
                'compte_contrepartie' => $journal->getCompteContrepartieDefaut()?->getNumeroCompte(),
                'statistiques' => $stats,
                'cree_le' => $journal->getCreatedAt(),
                'modifie_le' => $journal->getUpdatedAt()
            ];
        }
        
        return [
            'success' => true,
            'journaux' => $listeJournaux,
            'total' => count($listeJournaux)
        ];
    }

    /**
     * Valide le code d'un journal
     */
    private function validerCodeJournal(string $code): bool
    {
        return preg_match('/^[A-Z]{1,3}$/', $code) === 1;
    }

    /**
     * Valide le type d'un journal
     */
    private function validerTypeJournal(string $type): bool
    {
        $typesValides = [
            'VENTE', 'ACHAT', 'BANQUE', 'CAISSE', 'OD_GENERALES', 
            'A_NOUVEAUX', 'PAIE', 'IMMOBILISATIONS'
        ];
        
        return in_array($type, $typesValides);
    }

    /**
     * Génère un format de numérotation par défaut
     */
    private function genererFormatDefaut(string $code): string
    {
        return $code . '{YYYY}{0000}';
    }

    /**
     * Vérifie si un numéro respecte le format du journal
     */
    private function verifierFormatNumero(string $numero, ?string $format): bool
    {
        if (!$format) {
            return true; // Pas de contrôle de format
        }
        
        // Convertir le format en regex
        $pattern = str_replace(
            ['{YYYY}', '{0000}', '{000}'],
            ['\\d{4}', '\\d{4}', '\\d{3}'],
            preg_quote($format, '/')
        );
        
        return preg_match('/^' . $pattern . '$/', $numero) === 1;
    }

    /**
     * Extrait le numéro de séquence d'un numéro d'écriture
     */
    private function extraireNumeroSequence(string $numeroEcriture): ?int
    {
        // Extrait les derniers chiffres du numéro
        if (preg_match('/(\d+)$/', $numeroEcriture, $matches)) {
            return (int)$matches[1];
        }
        
        return null;
    }

    /**
     * Construit un numéro d'écriture à partir d'un numéro de séquence
     */
    private function construireNumeroFromSequence(int $sequence, ?string $format): string
    {
        if (!$format) {
            return (string)$sequence;
        }
        
        $numero = str_replace('{YYYY}', date('Y'), $format);
        $numero = preg_replace_callback('/\{(0+)\}/', function($matches) use ($sequence) {
            $length = strlen($matches[1]);
            return str_pad($sequence, $length, '0', STR_PAD_LEFT);
        }, $numero);
        
        return $numero;
    }
}