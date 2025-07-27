<?php

namespace App\Service;

use App\Entity\ComptePCG;
use App\Entity\User;
use App\Repository\ComptePCGRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service de gestion du plan comptable général français
 * Implémente les fonctionnalités de création, modification et gestion
 * des comptes selon la nomenclature PCG
 */
class PCGService
{
    private EntityManagerInterface $em;
    private ComptePCGRepository $compteRepo;
    private AuditService $auditService;
    private Security $security;
    private LoggerInterface $logger;

    // Plan comptable français standard
    private const PLAN_COMPTABLE_BASE = [
        // CLASSE 1 - COMPTES DE CAPITAUX
        '101000' => ['libelle' => 'Capital social', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '106000' => ['libelle' => 'Réserves', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '108000' => ['libelle' => 'Compte de l\'exploitant', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '120000' => ['libelle' => 'Résultat de l\'exercice', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '164000' => ['libelle' => 'Emprunts auprès des établissements de crédit', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        
        // CLASSE 2 - COMPTES D'IMMOBILISATIONS
        '201000' => ['libelle' => 'Frais d\'établissement', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '205000' => ['libelle' => 'Concessions et droits similaires', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '207000' => ['libelle' => 'Fonds commercial', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '213000' => ['libelle' => 'Constructions', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '215000' => ['libelle' => 'Installations techniques', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '218000' => ['libelle' => 'Autres immobilisations corporelles', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '254000' => ['libelle' => 'Matériel de transport', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '275000' => ['libelle' => 'Dépôts et cautionnements versés', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '281000' => ['libelle' => 'Amortissements des immobilisations incorporelles', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '283000' => ['libelle' => 'Amortissements des immobilisations corporelles', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        
        // CLASSE 3 - COMPTES DE STOCKS
        '310000' => ['libelle' => 'Matières premières', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '320000' => ['libelle' => 'Autres approvisionnements', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '355000' => ['libelle' => 'Produits finis', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '370000' => ['libelle' => 'Stocks de marchandises', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '391000' => ['libelle' => 'Dépréciations des matières premières', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '397000' => ['libelle' => 'Dépréciations des stocks de marchandises', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        
        // CLASSE 4 - COMPTES DE TIERS
        '401000' => ['libelle' => 'Fournisseurs', 'nature' => 'PASSIF', 'type' => 'TIERS', 'lettrage' => true],
        '403000' => ['libelle' => 'Fournisseurs - Effets à payer', 'nature' => 'PASSIF', 'type' => 'TIERS'],
        '408000' => ['libelle' => 'Fournisseurs - Factures non parvenues', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '411000' => ['libelle' => 'Clients', 'nature' => 'ACTIF', 'type' => 'TIERS', 'lettrage' => true],
        '413000' => ['libelle' => 'Clients - Effets à recevoir', 'nature' => 'ACTIF', 'type' => 'TIERS'],
        '416000' => ['libelle' => 'Clients - Créances douteuses', 'nature' => 'ACTIF', 'type' => 'TIERS'],
        '418000' => ['libelle' => 'Clients - Produits non encore facturés', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '421000' => ['libelle' => 'Personnel - Rémunérations dues', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '431000' => ['libelle' => 'Sécurité sociale', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '437000' => ['libelle' => 'Autres organismes sociaux', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '441000' => ['libelle' => 'État - Subventions à recevoir', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '442000' => ['libelle' => 'État - Impôts et taxes recouvrables', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '444000' => ['libelle' => 'État - Impôt sur les bénéfices', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '445500' => ['libelle' => 'TVA à décaisser', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '445620' => ['libelle' => 'TVA sur immobilisations', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '445660' => ['libelle' => 'TVA déductible sur autres biens et services', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '445710' => ['libelle' => 'TVA collectée', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '445711' => ['libelle' => 'TVA collectée 20%', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '445712' => ['libelle' => 'TVA collectée 10%', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '445713' => ['libelle' => 'TVA collectée 5,5%', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '445714' => ['libelle' => 'TVA collectée 2,1%', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '447000' => ['libelle' => 'Autres impôts, taxes et versements assimilés', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        '455000' => ['libelle' => 'Associés - Comptes courants', 'nature' => 'PASSIF', 'type' => 'TIERS'],
        '467000' => ['libelle' => 'Autres comptes débiteurs ou créditeurs', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '471000' => ['libelle' => 'Comptes d\'attente', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '486000' => ['libelle' => 'Charges constatées d\'avance', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '487000' => ['libelle' => 'Produits constatés d\'avance', 'nature' => 'PASSIF', 'type' => 'GENERAL'],
        
        // CLASSE 5 - COMPTES FINANCIERS
        '512000' => ['libelle' => 'Banques', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '530000' => ['libelle' => 'Caisse', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '531000' => ['libelle' => 'Chèques postaux', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        '580000' => ['libelle' => 'Virements internes', 'nature' => 'ACTIF', 'type' => 'GENERAL'],
        
        // CLASSE 6 - COMPTES DE CHARGES
        '601000' => ['libelle' => 'Achats de matières premières', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '607000' => ['libelle' => 'Achats de marchandises', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '613000' => ['libelle' => 'Locations', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '621000' => ['libelle' => 'Personnel extérieur', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '622000' => ['libelle' => 'Rémunérations d\'intermédiaires', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '623000' => ['libelle' => 'Publicité, publications', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '624000' => ['libelle' => 'Transports de biens et transport collectif du personnel', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '625000' => ['libelle' => 'Déplacements, missions et réceptions', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '626000' => ['libelle' => 'Frais postaux et de télécommunications', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '641000' => ['libelle' => 'Rémunérations du personnel', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '645000' => ['libelle' => 'Charges de sécurité sociale et de prévoyance', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '661000' => ['libelle' => 'Charges d\'intérêts', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '668000' => ['libelle' => 'Autres charges financières', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '681000' => ['libelle' => 'Dotations aux amortissements', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        '687000' => ['libelle' => 'Dotations aux dépréciations', 'nature' => 'CHARGE', 'type' => 'GENERAL'],
        
        // CLASSE 7 - COMPTES DE PRODUITS
        '701000' => ['libelle' => 'Ventes de produits finis', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '701200' => ['libelle' => 'Ventes de produits finis exonérées', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '706000' => ['libelle' => 'Prestations de services', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '707000' => ['libelle' => 'Ventes de marchandises', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '713000' => ['libelle' => 'Variation des stocks', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '740000' => ['libelle' => 'Subventions d\'exploitation', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '758000' => ['libelle' => 'Produits divers de gestion courante', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '761000' => ['libelle' => 'Produits financiers', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '781000' => ['libelle' => 'Reprises sur amortissements', 'nature' => 'PRODUIT', 'type' => 'GENERAL'],
        '787000' => ['libelle' => 'Reprises sur dépréciations', 'nature' => 'PRODUIT', 'type' => 'GENERAL']
    ];

    public function __construct(
        EntityManagerInterface $em,
        ComptePCGRepository $compteRepo,
        AuditService $auditService,
        Security $security,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->compteRepo = $compteRepo;
        $this->auditService = $auditService;
        $this->security = $security;
        $this->logger = $logger;
    }

    /**
     * Initialise le plan comptable français standard
     */
    public function initialiserPlanComptable(): array
    {
        try {
            $this->em->beginTransaction();
            
            $comptesCreees = [];
            $comptesExistants = [];
            
            foreach (self::PLAN_COMPTABLE_BASE as $numeroCompte => $config) {
                // Vérifier si le compte existe déjà
                $compteExistant = $this->compteRepo->find($numeroCompte);
                
                if ($compteExistant) {
                    $comptesExistants[] = $numeroCompte;
                    continue;
                }
                
                // Créer le nouveau compte
                $compte = new ComptePCG();
                $compte->setNumeroCompte($numeroCompte);
                $compte->setLibelle($config['libelle']);
                $compte->setNature($config['nature']);
                $compte->setType($config['type']);
                $compte->setIsActif(true);
                
                // Configuration du lettrage si spécifié
                if (isset($config['lettrage']) && $config['lettrage']) {
                    $compte->setIsPourLettrage(true);
                }
                
                $this->em->persist($compte);
                $comptesCreees[] = $numeroCompte;
                
                // Audit
                $this->auditService->logEntityChange(
                    $compte,
                    'CREATE',
                    [],
                    [
                        'numeroCompte' => $numeroCompte,
                        'libelle' => $config['libelle'],
                        'nature' => $config['nature']
                    ],
                    'Initialisation du plan comptable'
                );
            }
            
            $this->em->flush();
            $this->em->commit();
            
            $this->logger->info('Plan comptable initialisé', [
                'comptes_crees' => count($comptesCreees),
                'comptes_existants' => count($comptesExistants)
            ]);
            
            return [
                'success' => true,
                'comptes_crees' => $comptesCreees,
                'comptes_existants' => $comptesExistants,
                'total_comptes' => count(self::PLAN_COMPTABLE_BASE),
                'message' => sprintf(
                    'Plan comptable initialisé : %d comptes créés, %d existants',
                    count($comptesCreees),
                    count($comptesExistants)
                )
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur initialisation plan comptable', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Erreur lors de l\'initialisation du plan comptable'
            ];
        }
    }

    /**
     * Crée un nouveau compte comptable
     */
    public function creerCompte(
        string $numeroCompte,
        string $libelle,
        string $nature,
        string $type = 'GENERAL',
        array $options = []
    ): array {
        try {
            // Validation du numéro de compte
            if (!$this->validerNumeroCompte($numeroCompte)) {
                return [
                    'success' => false,
                    'error' => 'Numéro de compte invalide (doit contenir 6 chiffres minimum)'
                ];
            }
            
            // Vérifier l'unicité
            if ($this->compteRepo->find($numeroCompte)) {
                return [
                    'success' => false,
                    'error' => "Le compte {$numeroCompte} existe déjà"
                ];
            }
            
            // Validation de la nature
            if (!$this->validerNatureCompte($nature)) {
                return [
                    'success' => false,
                    'error' => 'Nature de compte invalide (ACTIF, PASSIF, CHARGE, PRODUIT)'
                ];
            }
            
            $this->em->beginTransaction();
            
            $compte = new ComptePCG();
            $compte->setNumeroCompte($numeroCompte);
            $compte->setLibelle($libelle);
            $compte->setNature($nature);
            $compte->setType($type);
            $compte->setIsActif($options['isActif'] ?? true);
            $compte->setIsPourLettrage($options['isPourLettrage'] ?? false);
            $compte->setIsPourAnalytique($options['isPourAnalytique'] ?? false);
            
            // Gestion du compte parent
            if (isset($options['compteParent'])) {
                $compteParent = $this->compteRepo->find($options['compteParent']);
                if ($compteParent) {
                    $compte->setCompteParent($compteParent);
                }
            }
            
            // Paramètres comptables additionnels
            if (isset($options['parametresComptables'])) {
                $compte->setParametresComptables($options['parametresComptables']);
            }
            
            $this->em->persist($compte);
            $this->em->flush();
            
            // Audit
            $this->auditService->logEntityChange(
                $compte,
                'CREATE',
                [],
                [
                    'numeroCompte' => $numeroCompte,
                    'libelle' => $libelle,
                    'nature' => $nature,
                    'type' => $type
                ],
                'Création d\'un nouveau compte comptable'
            );
            
            $this->em->commit();
            
            $this->logger->info('Compte comptable créé', [
                'numero_compte' => $numeroCompte,
                'libelle' => $libelle,
                'nature' => $nature
            ]);
            
            return [
                'success' => true,
                'compte' => $compte,
                'message' => "Compte {$numeroCompte} créé avec succès"
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur création compte', [
                'numero_compte' => $numeroCompte,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Modifie un compte existant
     */
    public function modifierCompte(string $numeroCompte, array $modifications): array
    {
        try {
            $compte = $this->compteRepo->find($numeroCompte);
            
            if (!$compte) {
                return [
                    'success' => false,
                    'error' => "Compte {$numeroCompte} introuvable"
                ];
            }
            
            // Sauvegarder les anciennes valeurs pour l'audit
            $anciennesValeurs = [
                'libelle' => $compte->getLibelle(),
                'nature' => $compte->getNature(),
                'type' => $compte->getType(),
                'isActif' => $compte->isIsActif(),
                'isPourLettrage' => $compte->isIsPourLettrage(),
                'isPourAnalytique' => $compte->isIsPourAnalytique()
            ];
            
            $this->em->beginTransaction();
            
            // Appliquer les modifications
            foreach ($modifications as $propriete => $valeur) {
                switch ($propriete) {
                    case 'libelle':
                        $compte->setLibelle($valeur);
                        break;
                    case 'nature':
                        if ($this->validerNatureCompte($valeur)) {
                            $compte->setNature($valeur);
                        }
                        break;
                    case 'type':
                        $compte->setType($valeur);
                        break;
                    case 'isActif':
                        $compte->setIsActif((bool)$valeur);
                        break;
                    case 'isPourLettrage':
                        $compte->setIsPourLettrage((bool)$valeur);
                        break;
                    case 'isPourAnalytique':
                        $compte->setIsPourAnalytique((bool)$valeur);
                        break;
                    case 'parametresComptables':
                        $compte->setParametresComptables($valeur);
                        break;
                }
            }
            
            $this->em->flush();
            
            // Audit
            $nouvellesValeurs = [
                'libelle' => $compte->getLibelle(),
                'nature' => $compte->getNature(),
                'type' => $compte->getType(),
                'isActif' => $compte->isIsActif(),
                'isPourLettrage' => $compte->isIsPourLettrage(),
                'isPourAnalytique' => $compte->isIsPourAnalytique()
            ];
            
            $this->auditService->logEntityChange(
                $compte,
                'UPDATE',
                $anciennesValeurs,
                $nouvellesValeurs,
                'Modification du compte comptable'
            );
            
            $this->em->commit();
            
            $this->logger->info('Compte comptable modifié', [
                'numero_compte' => $numeroCompte,
                'modifications' => array_keys($modifications)
            ]);
            
            return [
                'success' => true,
                'compte' => $compte,
                'message' => "Compte {$numeroCompte} modifié avec succès"
            ];
            
        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erreur modification compte', [
                'numero_compte' => $numeroCompte,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Désactive un compte (ne le supprime pas pour préserver l'historique)
     */
    public function desactiverCompte(string $numeroCompte, string $motif = null): array
    {
        try {
            $compte = $this->compteRepo->find($numeroCompte);
            
            if (!$compte) {
                return [
                    'success' => false,
                    'error' => "Compte {$numeroCompte} introuvable"
                ];
            }
            
            if (!$compte->isIsActif()) {
                return [
                    'success' => false,
                    'error' => "Le compte {$numeroCompte} est déjà désactivé"
                ];
            }
            
            // Vérifier s'il y a des mouvements non lettrés
            if ($this->hasEcrituresNonLettrees($compte)) {
                return [
                    'success' => false,
                    'error' => "Impossible de désactiver le compte {$numeroCompte} : écritures non lettrées existantes"
                ];
            }
            
            $this->em->beginTransaction();
            
            $compte->setIsActif(false);
            $this->em->flush();
            
            // Audit
            $this->auditService->logEntityChange(
                $compte,
                'UPDATE',
                ['isActif' => true],
                ['isActif' => false],
                $motif ?: "Désactivation du compte {$numeroCompte}"
            );
            
            $this->em->commit();
            
            $this->logger->info('Compte comptable désactivé', [
                'numero_compte' => $numeroCompte,
                'motif' => $motif
            ]);
            
            return [
                'success' => true,
                'message' => "Compte {$numeroCompte} désactivé avec succès"
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
     * Recherche des comptes selon différents critères
     */
    public function rechercherComptes(array $criteres): array
    {
        $qb = $this->compteRepo->createQueryBuilder('c');
        
        // Numéro de compte (recherche partielle)
        if (isset($criteres['numero']) && !empty($criteres['numero'])) {
            $qb->andWhere('c.numeroCompte LIKE :numero')
               ->setParameter('numero', '%' . $criteres['numero'] . '%');
        }
        
        // Libellé (recherche partielle)
        if (isset($criteres['libelle']) && !empty($criteres['libelle'])) {
            $qb->andWhere('c.libelle LIKE :libelle')
               ->setParameter('libelle', '%' . $criteres['libelle'] . '%');
        }
        
        // Classe comptable
        if (isset($criteres['classe']) && !empty($criteres['classe'])) {
            if (is_array($criteres['classe'])) {
                $qb->andWhere('c.classe IN (:classes)')
                   ->setParameter('classes', $criteres['classe']);
            } else {
                $qb->andWhere('c.classe = :classe')
                   ->setParameter('classe', $criteres['classe']);
            }
        }
        
        // Nature
        if (isset($criteres['nature']) && !empty($criteres['nature'])) {
            $qb->andWhere('c.nature = :nature')
               ->setParameter('nature', $criteres['nature']);
        }
        
        // Type
        if (isset($criteres['type']) && !empty($criteres['type'])) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $criteres['type']);
        }
        
        // Actif/Inactif
        if (isset($criteres['actif'])) {
            $qb->andWhere('c.isActif = :actif')
               ->setParameter('actif', (bool)$criteres['actif']);
        }
        
        // Comptes pour lettrage
        if (isset($criteres['lettrage']) && $criteres['lettrage']) {
            $qb->andWhere('c.isPourLettrage = true');
        }
        
        // Ordre de tri
        $orderBy = $criteres['orderBy'] ?? 'numeroCompte';
        $orderDirection = $criteres['orderDirection'] ?? 'ASC';
        $qb->orderBy('c.' . $orderBy, $orderDirection);
        
        // Limite
        if (isset($criteres['limit'])) {
            $qb->setMaxResults($criteres['limit']);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne la structure hiérarchique du plan comptable
     */
    public function getStructureHierarchique(): array
    {
        $comptes = $this->compteRepo->findBy(['isActif' => true], ['numeroCompte' => 'ASC']);
        
        $structure = [];
        
        foreach (range(1, 8) as $classe) {
            $structure[$classe] = [
                'classe' => $classe,
                'libelle' => $this->getLibelleClasse($classe),
                'comptes' => []
            ];
        }
        
        foreach ($comptes as $compte) {
            $classe = (int)$compte->getClasse();
            if (isset($structure[$classe])) {
                $structure[$classe]['comptes'][] = [
                    'numero' => $compte->getNumeroCompte(),
                    'libelle' => $compte->getLibelle(),
                    'nature' => $compte->getNature(),
                    'type' => $compte->getType(),
                    'lettrage' => $compte->isIsPourLettrage(),
                    'solde_debiteur' => $compte->getSoldeDebiteur(),
                    'solde_crediteur' => $compte->getSoldeCrediteur()
                ];
            }
        }
        
        return $structure;
    }

    /**
     * Valide un numéro de compte selon les règles PCG
     */
    private function validerNumeroCompte(string $numeroCompte): bool
    {
        // Le numéro doit contenir au minimum 6 chiffres
        return preg_match('/^\d{6,}$/', $numeroCompte) === 1;
    }

    /**
     * Valide la nature d'un compte
     */
    private function validerNatureCompte(string $nature): bool
    {
        return in_array($nature, ['ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT']);
    }

    /**
     * Vérifie si un compte a des écritures non lettrées
     */
    private function hasEcrituresNonLettrees(ComptePCG $compte): bool
    {
        $qb = $this->em->createQueryBuilder();
        $count = $qb->select('COUNT(l.id)')
                    ->from('App\Entity\LigneEcriture', 'l')
                    ->where('l.compte = :compte')
                    ->andWhere('l.lettrage IS NULL')
                    ->setParameter('compte', $compte)
                    ->getQuery()
                    ->getSingleScalarResult();
        
        return $count > 0;
    }

    /**
     * Retourne le libellé d'une classe comptable
     */
    private function getLibelleClasse(int $classe): string
    {
        return match($classe) {
            1 => 'Comptes de capitaux',
            2 => 'Comptes d\'immobilisations',
            3 => 'Comptes de stocks et en-cours',
            4 => 'Comptes de tiers',
            5 => 'Comptes financiers',
            6 => 'Comptes de charges',
            7 => 'Comptes de produits',
            8 => 'Comptes spéciaux',
            default => 'Classe inconnue'
        };
    }

    /**
     * Exporte le plan comptable au format CSV
     */
    public function exporterPlanComptableCSV(): string
    {
        $comptes = $this->compteRepo->findBy(['isActif' => true], ['numeroCompte' => 'ASC']);
        
        $csv = "Numero;Libelle;Classe;Nature;Type;Lettrage;Solde Debiteur;Solde Crediteur\n";
        
        foreach ($comptes as $compte) {
            $csv .= sprintf(
                "%s;%s;%s;%s;%s;%s;%s;%s\n",
                $compte->getNumeroCompte(),
                str_replace(';', ',', $compte->getLibelle()),
                $compte->getClasse(),
                $compte->getNature(),
                $compte->getType(),
                $compte->isIsPourLettrage() ? 'OUI' : 'NON',
                $compte->getSoldeDebiteur(),
                $compte->getSoldeCrediteur()
            );
        }
        
        return $csv;
    }

    /**
     * Retourne les statistiques du plan comptable
     */
    public function getStatistiques(): array
    {
        // Total des comptes par statut
        $qbActifs = $this->em->createQueryBuilder();
        $totalActifs = $qbActifs->select('COUNT(c.numeroCompte)')
                                ->from(ComptePCG::class, 'c')
                                ->where('c.isActif = true')
                                ->getQuery()
                                ->getSingleScalarResult();
        
        $qbInactifs = $this->em->createQueryBuilder();
        $totalInactifs = $qbInactifs->select('COUNT(c.numeroCompte)')
                                   ->from(ComptePCG::class, 'c')
                                   ->where('c.isActif = false')
                                   ->getQuery()
                                   ->getSingleScalarResult();
        
        // Répartition par classe
        $repartitionClasse = [];
        for ($classe = 1; $classe <= 8; $classe++) {
            $qbClasse = $this->em->createQueryBuilder();
            $count = $qbClasse->select('COUNT(c.numeroCompte)')
                             ->from(ComptePCG::class, 'c')
                             ->where('c.classe = :classe')
                             ->andWhere('c.isActif = true')
                             ->setParameter('classe', (string)$classe)
                             ->getQuery()
                             ->getSingleScalarResult();
            
            $repartitionClasse[$classe] = [
                'classe' => $classe,
                'libelle' => $this->getLibelleClasse($classe),
                'nombre_comptes' => $count
            ];
        }
        
        return [
            'total_comptes_actifs' => $totalActifs,
            'total_comptes_inactifs' => $totalInactifs,
            'total_comptes' => $totalActifs + $totalInactifs,
            'repartition_par_classe' => $repartitionClasse,
            'derniere_maj' => new \DateTime()
        ];
    }
}