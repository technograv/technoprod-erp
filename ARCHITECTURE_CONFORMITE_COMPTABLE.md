# 🏛️ ARCHITECTURE CONFORMITÉ COMPTABLE - TechnoProd
## Solution Hybride Classique + Blockchain Optionnelle

**Version :** 1.0  
**Date :** 23 Juillet 2025  
**Objectif :** Conformité réglementaire française assurée  

---

## 🎯 1. VISION ARCHITECTURALE

### **1.1 Principes de Base**
```php
PRIORITÉS:
1. CONFORMITÉ LÉGALE GARANTIE (NF203, NF525, PCG, FEC)
2. SÉCURITÉ CRYPTOGRAPHIQUE ÉPROUVÉE (SHA-256, RSA)
3. PERFORMANCE OPTIMISÉE (< 100ms par opération)
4. ÉVOLUTIVITÉ BLOCKCHAIN (préparation future)
5. MAINTENANCE SIMPLIFIÉE (technologies standards)
```

### **1.2 Architecture Hybride**
```
┌─────────────────────────────────────────────────────────────┐
│                    COUCHE PRÉSENTATION                     │
│  Templates Twig + Bootstrap + Dashboard Compliance         │
└─────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────┐
│                    COUCHE MÉTIER SYMFONY                   │
│    Controllers + Services + Forms + Validators             │
└─────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────┐
│                 COUCHE CONFORMITÉ (NOUVELLE)               │
│  ComplianceService + AuditService + IntegrityService       │
└─────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
┌───────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  POSTGRESQL   │    │ SYSTÈME AUDIT   │    │ BLOCKCHAIN OPT. │
│   (Données)   │    │  (Intégrité)    │    │  (Ancrage)      │
└───────────────┘    └─────────────────┘    └─────────────────┘
```

---

## 🔒 2. SYSTÈME D'INALTÉRABILITÉ (NF203)

### **2.1 Nouvelles Entités de Sécurité**

#### **DocumentIntegrity - Table Principale**
```php
#[ORM\Entity]
class DocumentIntegrity
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    // IDENTIFICATION DOCUMENT
    #[ORM\Column(length: 50)]
    private string $documentType; // 'facture', 'devis', 'avoir', etc.

    #[ORM\Column]
    private int $documentId;

    #[ORM\Column(length: 20)]
    private string $documentNumber; // Numéro métier (FACT-2025-0001)

    // INTÉGRITÉ CRYPTOGRAPHIQUE
    #[ORM\Column(length: 10)]
    private string $hashAlgorithm = 'SHA256'; // Algorithme utilisé

    #[ORM\Column(length: 64)]
    private string $documentHash; // Hash SHA-256 du document

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousHash = null; // Hash document précédent (chaînage)

    #[ORM\Column(type: Types::TEXT)]
    private string $signatureData; // Signature RSA du hash

    // HORODATAGE SÉCURISÉ
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $timestampCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $timestampModification = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $qualifiedTimestamp = null; // TSA si requis

    // UTILISATEUR ET CONTEXTE
    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $createdBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $modifiedBy = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress; // IP de création

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null; // Navigateur utilisé

    // STATUT ET VALIDATION
    #[ORM\Column(length: 20)]
    private string $status = 'valid'; // 'valid', 'compromised', 'archived'

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastVerification = null;

    #[ORM\Column(nullable: true)]
    private ?bool $integrityValid = null; // Résultat dernière vérification

    // BLOCKCHAIN (OPTIONNEL)
    #[ORM\Column(length: 66, nullable: true)]
    private ?string $blockchainTxHash = null; // Hash transaction blockchain

    #[ORM\Column(nullable: true)]
    private ?int $blockchainBlockNumber = null; // Numéro de bloc

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $blockchainTimestamp = null;

    // MÉTADONNÉES CONFORMITÉ
    #[ORM\Column(type: Types::JSON)]
    private array $complianceMetadata = []; // Données spécifiques NF203/525

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $archivalReference = null; // Référence archivage légal
}
```

#### **AuditTrail - Traçabilité Complète**
```php
#[ORM\Entity]
class AuditTrail
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    // IDENTIFICATION
    #[ORM\Column(length: 50)]
    private string $entityType; // Classe de l'entité

    #[ORM\Column]
    private int $entityId; // ID de l'entité

    #[ORM\Column(length: 20)]
    private string $action; // 'CREATE', 'UPDATE', 'DELETE', 'VIEW'

    // CHANGEMENTS
    #[ORM\Column(type: Types::JSON)]
    private array $oldValues = []; // Valeurs avant modification

    #[ORM\Column(type: Types::JSON)]
    private array $newValues = []; // Valeurs après modification

    #[ORM\Column(type: Types::JSON)]
    private array $changedFields = []; // Liste des champs modifiés

    // CONTEXTE
    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user; // Utilisateur responsable

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $timestamp; // Date/heure précise

    #[ORM\Column(length: 45)]
    private string $ipAddress; // Adresse IP

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null; // Navigateur

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sessionId = null; // ID de session

    // JUSTIFICATION
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $justification = null; // Motif de la modification

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $approvedBy = null; // Approbateur si requis

    // INTÉGRITÉ
    #[ORM\Column(length: 64)]
    private string $recordHash; // Hash de cet enregistrement

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousRecordHash = null; // Hash enregistrement précédent
}
```

### **2.2 Services de Sécurité**

#### **DocumentIntegrityService - Cœur du Système**
```php
<?php

namespace App\Service;

class DocumentIntegrityService
{
    private EntityManagerInterface $em;
    private string $privateKeyPath;
    private string $publicKeyPath;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        string $privateKeyPath,
        string $publicKeyPath,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->privateKeyPath = $privateKeyPath;
        $this->publicKeyPath = $publicKeyPath;
        $this->logger = $logger;
    }

    /**
     * Sécurise un document selon NF203
     */
    public function secureDocument(object $document, User $user, string $ipAddress): DocumentIntegrity
    {
        // 1. Calcul hash du document
        $documentHash = $this->calculateDocumentHash($document);
        
        // 2. Récupération hash précédent pour chaînage
        $previousHash = $this->getLastDocumentHash(get_class($document));
        
        // 3. Signature cryptographique
        $signatureData = $this->signHash($documentHash, $previousHash);
        
        // 4. Création enregistrement intégrité
        $integrity = new DocumentIntegrity();
        $integrity->setDocumentType($this->getDocumentType($document));
        $integrity->setDocumentId($document->getId());
        $integrity->setDocumentNumber($this->getDocumentNumber($document));
        $integrity->setDocumentHash($documentHash);
        $integrity->setPreviousHash($previousHash);
        $integrity->setSignatureData($signatureData);
        $integrity->setTimestampCreation(new DateTime());
        $integrity->setCreatedBy($user);
        $integrity->setIpAddress($ipAddress);
        $integrity->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);
        
        // 5. Métadonnées conformité
        $integrity->setComplianceMetadata([
            'nf203_version' => '2014',
            'hash_algorithm' => 'SHA256',
            'signature_algorithm' => 'RSA-2048',
            'document_version' => $this->getDocumentVersion($document),
            'business_rules' => $this->getBusinessRulesVersion()
        ]);
        
        // 6. Sauvegarde
        $this->em->persist($integrity);
        $this->em->flush();
        
        // 7. Ancrage blockchain optionnel
        if ($this->isBlockchainEnabled()) {
            $this->anchorToBlockchain($integrity);
        }
        
        $this->logger->info('Document secured', [
            'document_type' => get_class($document),
            'document_id' => $document->getId(),
            'hash' => $documentHash
        ]);
        
        return $integrity;
    }

    /**
     * Vérifie l'intégrité d'un document
     */
    public function verifyDocumentIntegrity(object $document): array
    {
        $documentType = $this->getDocumentType($document);
        $documentId = $document->getId();
        
        // Récupération enregistrement intégrité
        $integrity = $this->em->getRepository(DocumentIntegrity::class)
            ->findOneBy([
                'documentType' => $documentType,
                'documentId' => $documentId
            ]);
            
        if (!$integrity) {
            return [
                'valid' => false,
                'error' => 'Aucun enregistrement d\'intégrité trouvé',
                'risk_level' => 'HIGH'
            ];
        }
        
        // Vérifications multiples
        $checks = [
            'hash_integrity' => $this->verifyDocumentHash($document, $integrity),
            'signature_integrity' => $this->verifySignature($integrity),
            'chain_integrity' => $this->verifyChainIntegrity($integrity),
            'timestamp_validity' => $this->verifyTimestamp($integrity)
        ];
        
        // Vérification blockchain si disponible
        if ($integrity->getBlockchainTxHash()) {
            $checks['blockchain_integrity'] = $this->verifyBlockchainAnchor($integrity);
        }
        
        $allValid = array_reduce($checks, fn($carry, $check) => $carry && $check['valid'], true);
        
        // Mise à jour statut
        $integrity->setLastVerification(new DateTime());
        $integrity->setIntegrityValid($allValid);
        $this->em->flush();
        
        return [
            'valid' => $allValid,
            'checks' => $checks,
            'integrity_record' => $integrity,
            'risk_level' => $allValid ? 'LOW' : 'HIGH'
        ];
    }

    /**
     * Calcule le hash SHA-256 d'un document
     */
    private function calculateDocumentHash(object $document): string
    {
        // Sérialisation normalisée du document
        $documentData = $this->normalizeDocumentForHashing($document);
        
        // Hash SHA-256
        return hash('sha256', $documentData);
    }

    /**
     * Normalise un document pour le hachage
     */
    private function normalizeDocumentForHashing(object $document): string
    {
        $data = [];
        
        // Extraction données métier critiques
        if ($document instanceof Facture) {
            $data = [
                'numero' => $document->getNumeroFacture(),
                'date' => $document->getDateFacture()->format('Y-m-d'),
                'prospect_id' => $document->getProspect()->getId(),
                'montant_ht' => $document->getTotalHt(),
                'montant_tva' => $document->getTotalTva(),
                'montant_ttc' => $document->getTotalTtc(),
                'items' => $this->hashFactureItems($document->getFactureItems())
            ];
        } elseif ($document instanceof Devis) {
            $data = [
                'numero' => $document->getNumeroDevis(),
                'date' => $document->getDateCreation()->format('Y-m-d'),
                'prospect_id' => $document->getProspect()->getId(),
                'montant_ht' => $document->getTotalHt(),
                'montant_tva' => $document->getTotalTva(),
                'montant_ttc' => $document->getTotalTtc(),
                'items' => $this->hashDevisItems($document->getDevisItems())
            ];
        }
        // ... autres types de documents
        
        // Sérialisation JSON normalisée
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS);
    }

    /**
     * Signature RSA du hash
     */
    private function signHash(string $documentHash, ?string $previousHash): string
    {
        $dataToSign = $documentHash . ($previousHash ?? '');
        
        $privateKey = openssl_pkey_get_private(file_get_contents($this->privateKeyPath));
        
        if (!$privateKey) {
            throw new \Exception('Impossible de charger la clé privée');
        }
        
        $signature = '';
        if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Erreur lors de la signature');
        }
        
        return base64_encode($signature);
    }

    /**
     * Vérification signature RSA
     */
    private function verifySignature(DocumentIntegrity $integrity): array
    {
        $dataToVerify = $integrity->getDocumentHash() . ($integrity->getPreviousHash() ?? '');
        $signature = base64_decode($integrity->getSignatureData());
        
        $publicKey = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
        
        if (!$publicKey) {
            return ['valid' => false, 'error' => 'Clé publique invalide'];
        }
        
        $valid = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        
        return [
            'valid' => $valid,
            'algorithm' => 'RSA-SHA256',
            'verified_at' => new DateTime()
        ];
    }

    /**
     * Récupère le hash du dernier document pour chaînage
     */
    private function getLastDocumentHash(string $documentType): ?string
    {
        $lastIntegrity = $this->em->getRepository(DocumentIntegrity::class)
            ->findOneBy(
                ['documentType' => $documentType],
                ['timestampCreation' => 'DESC']
            );
            
        return $lastIntegrity?->getDocumentHash();
    }

    /**
     * Ancrage blockchain optionnel
     */
    private function anchorToBlockchain(DocumentIntegrity $integrity): void
    {
        // TODO: Implémentation future
        // - Connexion réseau blockchain privé
        // - Création transaction avec hash
        // - Mise à jour références blockchain
    }
}
```

#### **AuditService - Traçabilité Automatique**
```php
<?php

namespace App\Service;

class AuditService
{
    private EntityManagerInterface $em;
    private Security $security;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    /**
     * Enregistre automatiquement toute modification
     */
    public function logEntityChange(
        object $entity,
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?string $justification = null
    ): AuditTrail {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();
        
        $audit = new AuditTrail();
        $audit->setEntityType(get_class($entity));
        $audit->setEntityId($this->getEntityId($entity));
        $audit->setAction($action);
        $audit->setOldValues($oldValues);
        $audit->setNewValues($newValues);
        $audit->setChangedFields(array_keys(array_diff_assoc($newValues, $oldValues)));
        $audit->setUser($user);
        $audit->setTimestamp(new DateTime());
        $audit->setIpAddress($request?->getClientIp() ?? '127.0.0.1');
        $audit->setUserAgent($request?->headers->get('User-Agent'));
        $audit->setSessionId($request?->getSession()?->getId());
        $audit->setJustification($justification);
        
        // Hash de l'enregistrement audit
        $recordHash = $this->calculateAuditHash($audit);
        $audit->setRecordHash($recordHash);
        
        // Chaînage avec l'enregistrement précédent
        $previousHash = $this->getLastAuditHash();
        $audit->setPreviousRecordHash($previousHash);
        
        $this->em->persist($audit);
        $this->em->flush();
        
        return $audit;
    }

    /**
     * Vérifie la chaîne d'audit
     */
    public function verifyAuditChain(): array
    {
        $audits = $this->em->getRepository(AuditTrail::class)
            ->findBy([], ['timestamp' => 'ASC']);
            
        $errors = [];
        $previousHash = null;
        
        foreach ($audits as $audit) {
            // Vérification hash de l'enregistrement
            $calculatedHash = $this->calculateAuditHash($audit);
            if ($calculatedHash !== $audit->getRecordHash()) {
                $errors[] = [
                    'audit_id' => $audit->getId(),
                    'error' => 'Hash de l\'enregistrement invalide',
                    'severity' => 'HIGH'
                ];
            }
            
            // Vérification chaînage
            if ($previousHash !== $audit->getPreviousRecordHash()) {
                $errors[] = [
                    'audit_id' => $audit->getId(),
                    'error' => 'Rupture de chaînage détectée',
                    'severity' => 'CRITICAL'
                ];
            }
            
            $previousHash = $audit->getRecordHash();
        }
        
        return [
            'valid' => empty($errors),
            'total_records' => count($audits),
            'errors' => $errors
        ];
    }

    private function calculateAuditHash(AuditTrail $audit): string
    {
        $data = [
            'entity_type' => $audit->getEntityType(),
            'entity_id' => $audit->getEntityId(),
            'action' => $audit->getAction(),
            'old_values' => $audit->getOldValues(),
            'new_values' => $audit->getNewValues(),
            'user_id' => $audit->getUser()->getId(),
            'timestamp' => $audit->getTimestamp()->format('Y-m-d H:i:s.u'),
            'ip_address' => $audit->getIpAddress()
        ];
        
        return hash('sha256', json_encode($data, JSON_SORT_KEYS));
    }
}
```

---

## 📊 3. STRUCTURE COMPTABLE (PCG)

### **3.1 Entités Comptables**

#### **CompteComptable - Plan Comptable Français**
```php
#[ORM\Entity]
class CompteComptable
{
    #[ORM\Id]
    #[ORM\Column(length: 10)]
    private string $numeroCompte; // 411000, 701000, etc.

    #[ORM\Column(length: 255)]
    private string $libelle; // "Clients", "Ventes de produits finis"

    #[ORM\Column(length: 1)]
    private string $classe; // 1-8 (classes du PCG)

    #[ORM\Column(length: 20)]
    private string $nature; // ACTIF, PASSIF, CHARGE, PRODUIT

    #[ORM\Column(length: 20)]
    private string $type; // GENERAL, TIERS, ANALYTIQUE

    #[ORM\Column]
    private bool $isActif = true;

    #[ORM\Column]
    private bool $isPourLettrage = false; // Comptes clients/fournisseurs

    #[ORM\Column]
    private bool $isPourAnalytique = false;

    // Comptes de regroupement
    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?CompteComptable $compteParent = null;

    #[ORM\OneToMany(mappedBy: 'compteParent', targetEntity: self::class)]
    private Collection $sousComptes;

    // Soldes
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $soldeDebiteur = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $soldeCrediteur = '0.00';

    // Métadonnées
    #[ORM\Column(type: Types::JSON)]
    private array $parametresComptables = [];

    public function __construct()
    {
        $this->sousComptes = new ArrayCollection();
    }
    
    // ... getters/setters
}
```

#### **EcritureComptable - Écritures selon PCG**
```php
#[ORM\Entity]
class EcritureComptable
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    // IDENTIFICATION FEC
    #[ORM\Column(length: 3)]
    private string $journalCode; // VTE, ACH, BAN, OD

    #[ORM\Column(length: 100)]
    private string $journalLibelle; // "Journal des ventes"

    #[ORM\Column(length: 20)]
    private string $numeroEcriture; // VTE20250001

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $dateEcriture;

    // COMPTE ET MONTANTS
    #[ORM\ManyToOne(targetEntity: CompteComptable::class)]
    private CompteComptable $compteComptable;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $montantDebit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $montantCredit = '0.00';

    // LIBELLÉ ET RÉFÉRENCES
    #[ORM\Column(length: 255)]
    private string $libelleEcriture;

    #[ORM\Column(length: 20)]
    private string $numeroPiece; // Numéro facture, etc.

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $datePiece;

    // TIERS (si applicable)
    #[ORM\Column(length: 17, nullable: true)]
    private ?string $compteAuxiliaire = null; // Code client/fournisseur

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $compteAuxiliaireLibelle = null;

    // LETTRAGE ET ÉCHÉANCE
    #[ORM\Column(length: 3, nullable: true)]
    private ?string $lettrage = null; // Pour rapprochement

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateLettrage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateEcheance = null;

    // VALIDATION
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateValidation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $validePar = null;

    // DEVISES (si multi-devises)
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $montantDevise = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $codeDevise = null; // EUR, USD, etc.

    // LIENS DOCUMENTS MÉTIER
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $documentType = null; // facture, devis, avoir

    #[ORM\Column(nullable: true)]
    private ?int $documentId = null;

    // EXERCICE COMPTABLE
    #[ORM\ManyToOne(targetEntity: ExerciceComptable::class)]
    private ExerciceComptable $exerciceComptable;

    // INTÉGRITÉ (liens avec système sécurité)
    #[ORM\OneToOne(targetEntity: DocumentIntegrity::class)]
    private ?DocumentIntegrity $integrite = null;

    // ... getters/setters
}
```

#### **ExerciceComptable - Gestion Exercices**
```php
#[ORM\Entity]
class ExerciceComptable
{
    #[ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column]
    private int $anneeExercice; // 2025

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $dateDebut; // 01/01/2025

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private DateTimeInterface $dateFin; // 31/12/2025

    #[ORM\Column(length: 20)]
    private string $statut = 'ouvert'; // ouvert, cloture, archive

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateCloture = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'exerciceComptable', targetEntity: EcritureComptable::class)]
    private Collection $ecrituresComptables;

    // Totaux de contrôle
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalDebit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $totalCredit = '0.00';

    #[ORM\Column]
    private int $nombreEcritures = 0;

    public function __construct()
    {
        $this->ecrituresComptables = new ArrayCollection();
    }
    
    // ... getters/setters
}
```

### **3.2 Service de Comptabilisation Automatique**

```php
<?php

namespace App\Service;

class ComptabilisationService
{
    private EntityManagerInterface $em;
    private CompteComptableRepository $compteRepo;
    private DocumentIntegrityService $integrityService;

    public function __construct(
        EntityManagerInterface $em,
        CompteComptableRepository $compteRepo,
        DocumentIntegrityService $integrityService
    ) {
        $this->em = $em;
        $this->compteRepo = $compteRepo;
        $this->integrityService = $integrityService;
    }

    /**
     * Comptabilise automatiquement une facture
     */
    public function comptabiliserFacture(Facture $facture): array
    {
        $exercice = $this->getExerciceActuel();
        $numeroEcriture = $this->genererNumeroEcriture('VTE', $exercice);
        $ecritures = [];

        // 1. DÉBIT - Compte client (411xxx)
        $compteClient = $this->compteRepo->findOneBy(['numeroCompte' => '411000']);
        $ecritures[] = $this->creerEcriture([
            'journalCode' => 'VTE',
            'journalLibelle' => 'Journal des ventes',
            'numeroEcriture' => $numeroEcriture,
            'dateEcriture' => $facture->getDateFacture(),
            'compteComptable' => $compteClient,
            'montantDebit' => $facture->getTotalTtc(),
            'montantCredit' => '0.00',
            'libelleEcriture' => 'Fact. ' . $facture->getNumeroFacture() . ' - ' . $facture->getProspect()->getNomComplet(),
            'numeroPiece' => $facture->getNumeroFacture(),
            'datePiece' => $facture->getDateFacture(),
            'compteAuxiliaire' => 'C' . str_pad($facture->getProspect()->getId(), 8, '0', STR_PAD_LEFT),
            'compteAuxiliaireLibelle' => $facture->getProspect()->getNomComplet(),
            'dateEcheance' => $facture->getDateEcheance(),
            'exerciceComptable' => $exercice,
            'documentType' => 'facture',
            'documentId' => $facture->getId()
        ]);

        // 2. CRÉDIT - Comptes de vente par taux TVA
        $ventilationTVA = $this->calculerVentilationTVA($facture);
        
        foreach ($ventilationTVA as $tauxTva => $montants) {
            // Compte de vente (701xxx)
            $compteVente = $this->determinerCompteVente($facture, $tauxTva);
            $ecritures[] = $this->creerEcriture([
                'journalCode' => 'VTE',
                'journalLibelle' => 'Journal des ventes',
                'numeroEcriture' => $numeroEcriture,
                'dateEcriture' => $facture->getDateFacture(),
                'compteComptable' => $compteVente,
                'montantDebit' => '0.00',
                'montantCredit' => $montants['ht'],
                'libelleEcriture' => 'Fact. ' . $facture->getNumeroFacture() . ' - Vente HT ' . $tauxTva . '%',
                'numeroPiece' => $facture->getNumeroFacture(),
                'datePiece' => $facture->getDateFacture(),
                'exerciceComptable' => $exercice,
                'documentType' => 'facture',
                'documentId' => $facture->getId()
            ]);

            // Compte TVA collectée (44571x)
            if ($montants['tva'] > 0) {
                $compteTva = $this->determinerCompteTVA($tauxTva);
                $ecritures[] = $this->creerEcriture([
                    'journalCode' => 'VTE',
                    'journalLibelle' => 'Journal des ventes',
                    'numeroEcriture' => $numeroEcriture,
                    'dateEcriture' => $facture->getDateFacture(),
                    'compteComptable' => $compteTva,
                    'montantDebit' => '0.00',
                    'montantCredit' => $montants['tva'],
                    'libelleEcriture' => 'Fact. ' . $facture->getNumeroFacture() . ' - TVA ' . $tauxTva . '%',
                    'numeroPiece' => $facture->getNumeroFacture(),
                    'datePiece' => $facture->getDateFacture(),
                    'exerciceComptable' => $exercice,
                    'documentType' => 'facture',
                    'documentId' => $facture->getId()
                ]);
            }
        }

        // 3. Sauvegarde et sécurisation
        foreach ($ecritures as $ecriture) {
            $this->em->persist($ecriture);
            
            // Sécurisation selon NF203
            $this->integrityService->secureDocument(
                $ecriture,
                $this->security->getUser(),
                $this->requestStack->getCurrentRequest()?->getClientIp() ?? '127.0.0.1'
            );
        }

        $this->em->flush();

        // 4. Vérification équilibre
        $this->verifierEquilibreEcriture($numeroEcriture);

        return $ecritures;
    }

    /**
     * Comptabilise un paiement de facture
     */
    public function comptabiliserPaiement(Facture $facture, Paiement $paiement): array
    {
        $exercice = $this->getExerciceActuel();
        $numeroEcriture = $this->genererNumeroEcriture('BAN', $exercice);
        $ecritures = [];

        // DÉBIT - Compte de trésorerie
        $compteTresorerie = $this->determinerCompteTresorerie($paiement->getMode());
        $ecritures[] = $this->creerEcriture([
            'journalCode' => 'BAN',
            'journalLibelle' => 'Journal de banque',
            'numeroEcriture' => $numeroEcriture,
            'dateEcriture' => $paiement->getDatePaiement(),
            'compteComptable' => $compteTresorerie,
            'montantDebit' => $paiement->getMontant(),
            'montantCredit' => '0.00',
            'libelleEcriture' => 'Paiement fact. ' . $facture->getNumeroFacture(),
            'numeroPiece' => $paiement->getNumeroPaiement(),
            'datePiece' => $paiement->getDatePaiement(),
            'exerciceComptable' => $exercice,
            'documentType' => 'paiement',
            'documentId' => $paiement->getId()
        ]);

        // CRÉDIT - Compte client
        $compteClient = $this->compteRepo->findOneBy(['numeroCompte' => '411000']);
        $ecritures[] = $this->creerEcriture([
            'journalCode' => 'BAN',
            'journalLibelle' => 'Journal de banque',
            'numeroEcriture' => $numeroEcriture,
            'dateEcriture' => $paiement->getDatePaiement(),
            'compteComptable' => $compteClient,
            'montantDebit' => '0.00',
            'montantCredit' => $paiement->getMontant(),
            'libelleEcriture' => 'Paiement fact. ' . $facture->getNumeroFacture(),
            'numeroPiece' => $paiement->getNumeroPaiement(),
            'datePiece' => $paiement->getDatePaiement(),
            'compteAuxiliaire' => 'C' . str_pad($facture->getProspect()->getId(), 8, '0', STR_PAD_LEFT),
            'compteAuxiliaireLibelle' => $facture->getProspect()->getNomComplet(),
            'exerciceComptable' => $exercice,
            'documentType' => 'paiement',
            'documentId' => $paiement->getId()
        ]);

        // Sauvegarde
        foreach ($ecritures as $ecriture) {
            $this->em->persist($ecriture);
            $this->integrityService->secureDocument($ecriture, $this->security->getUser(), '127.0.0.1');
        }

        $this->em->flush();

        return $ecritures;
    }

    private function creerEcriture(array $data): EcritureComptable
    {
        $ecriture = new EcritureComptable();
        
        foreach ($data as $property => $value) {
            $setter = 'set' . ucfirst($property);
            if (method_exists($ecriture, $setter)) {
                $ecriture->$setter($value);
            }
        }
        
        return $ecriture;
    }

    private function verifierEquilibreEcriture(string $numeroEcriture): void
    {
        $result = $this->em->createQuery(
            'SELECT SUM(e.montantDebit) as totalDebit, SUM(e.montantCredit) as totalCredit 
             FROM App\Entity\EcritureComptable e 
             WHERE e.numeroEcriture = :numero'
        )->setParameter('numero', $numeroEcriture)->getSingleResult();

        if ($result['totalDebit'] !== $result['totalCredit']) {
            throw new \Exception("Écriture déséquilibrée: {$numeroEcriture}");
        }
    }
}
```

---

## 📄 4. GÉNÉRATION FEC (FICHIER ÉCRITURES COMPTABLES)

### **4.1 Service FECGenerator**

```php
<?php

namespace App\Service;

class FECGenerator
{
    private EntityManagerInterface $em;
    private string $siret;
    private string $denomination;

    public function __construct(EntityManagerInterface $em, string $siret, string $denomination)
    {
        $this->em = $em;
        $this->siret = $siret;
        $this->denomination = $denomination;
    }

    /**
     * Génère un fichier FEC conforme
     */
    public function generateFEC(
        DateTime $dateDebut,
        DateTime $dateFin,
        ?ExerciceComptable $exercice = null
    ): string {
        // En-tête FEC obligatoire
        $header = implode('|', [
            'JournalCode',
            'JournalLib',
            'EcritureNum',
            'EcritureDate',
            'CompteNum',
            'CompteLib',
            'CompAuxNum',
            'CompAuxLib',
            'PieceRef',
            'PieceDate',
            'EcritureLib',
            'Debit',
            'Credit',
            'EcritureLet',
            'DateLet',
            'ValidDate',
            'Montantdevise',
            'Idevise'
        ]);

        $lines = [$header];

        // Récupération écritures de la période
        $ecritures = $this->getEcrituresPeriode($dateDebut, $dateFin, $exercice);

        foreach ($ecritures as $ecriture) {
            $lines[] = $this->formatLigneFEC($ecriture);
        }

        // Validation FEC
        $this->validateFEC($lines, $dateDebut, $dateFin);

        return implode("\n", $lines);
    }

    /**
     * Formate une ligne d'écriture au format FEC
     */
    private function formatLigneFEC(EcritureComptable $ecriture): string
    {
        return implode('|', [
            $ecriture->getJournalCode(),                                    // JournalCode
            $this->sanitizeFECField($ecriture->getJournalLibelle(), 100),  // JournalLib
            $ecriture->getNumeroEcriture(),                                 // EcritureNum
            $ecriture->getDateEcriture()->format('Ymd'),                   // EcritureDate (AAAAMMJJ)
            $ecriture->getCompteComptable()->getNumeroCompte(),            // CompteNum
            $this->sanitizeFECField($ecriture->getCompteComptable()->getLibelle(), 100), // CompteLib
            $ecriture->getCompteAuxiliaire() ?? '',                        // CompAuxNum
            $this->sanitizeFECField($ecriture->getCompteAuxiliaireLibelle() ?? '', 100), // CompAuxLib
            $ecriture->getNumeroPiece(),                                    // PieceRef
            $ecriture->getDatePiece()->format('Ymd'),                     // PieceDate
            $this->sanitizeFECField($ecriture->getLibelleEcriture(), 200), // EcritureLib
            $this->formatMontantFEC($ecriture->getMontantDebit()),         // Debit
            $this->formatMontantFEC($ecriture->getMontantCredit()),        // Credit
            $ecriture->getLettrage() ?? '',                                // EcritureLet
            $ecriture->getDateLettrage()?->format('Ymd') ?? '',           // DateLet
            $ecriture->getDateValidation()?->format('Ymd') ?? '',         // ValidDate
            $this->formatMontantFEC($ecriture->getMontantDevise() ?? '0'), // Montantdevise
            $ecriture->getCodeDevise() ?? 'EUR'                           // Idevise
        ]);
    }

    /**
     * Valide la conformité du FEC généré
     */
    private function validateFEC(array $lines, DateTime $dateDebut, DateTime $dateFin): void
    {
        $errors = [];

        // Contrôles obligatoires FEC
        if (count($lines) < 2) {
            $errors[] = "FEC vide (aucune écriture)";
        }

        // Vérification équilibre global
        $totalDebit = 0;
        $totalCredit = 0;

        for ($i = 1; $i < count($lines); $i++) { // Skip header
            $fields = explode('|', $lines[$i]);
            
            if (count($fields) !== 18) {
                $errors[] = "Ligne {$i}: nombre de champs incorrect (" . count($fields) . "/18)";
                continue;
            }

            $debit = (float) str_replace(',', '.', $fields[11]);
            $credit = (float) str_replace(',', '.', $fields[12]);
            
            $totalDebit += $debit;
            $totalCredit += $credit;

            // Contrôles format date
            if (!preg_match('/^\d{8}$/', $fields[3])) {
                $errors[] = "Ligne {$i}: format date écriture invalide";
            }
        }

        // Équilibre débit/crédit
        if (abs($totalDebit - $totalCredit) > 0.01) {
            $errors[] = "FEC déséquilibré: Débit {$totalDebit} ≠ Crédit {$totalCredit}";
        }

        if (!empty($errors)) {
            throw new \Exception("FEC non conforme:\n" . implode("\n", $errors));
        }
    }

    /**
     * Export FEC en fichier téléchargeable
     */
    public function exportFECFile(
        DateTime $dateDebut,
        DateTime $dateFin,
        ?ExerciceComptable $exercice = null
    ): BinaryFileResponse {
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
        file_put_contents($tempFile, $fecContent);

        $response = new BinaryFileResponse($tempFile);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'text/plain; charset=windows-1252');

        // Suppression automatique du fichier temporaire
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function sanitizeFECField(string $value, int $maxLength): string
    {
        // Suppression caractères interdits FEC
        $sanitized = str_replace(['|', "\n", "\r", "\t"], ' ', $value);
        
        // Conversion Windows-1252 pour compatibilité
        $sanitized = mb_convert_encoding($sanitized, 'Windows-1252', 'UTF-8');
        
        // Troncature si nécessaire
        return mb_substr($sanitized, 0, $maxLength);
    }

    private function formatMontantFEC(string $montant): string
    {
        // Format FEC: virgule comme séparateur décimal, pas de séparateur milliers
        return str_replace('.', ',', $montant);
    }
}
```

---

## 📧 5. PRÉPARATION FACTUR-X (LOI 2026)

### **5.1 Service FacturXGenerator**

```php
<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

class FacturXGenerator
{
    private DocumentIntegrityService $integrityService;
    private string $certificatePath;
    private string $privateKeyPath;

    public function __construct(
        DocumentIntegrityService $integrityService,
        string $certificatePath,
        string $privateKeyPath
    ) {
        $this->integrityService = $integrityService;
        $this->certificatePath = $certificatePath;
        $this->privateKeyPath = $privateKeyPath;
    }

    /**
     * Génère une facture Factur-X (PDF/A-3 + XML CII)
     */
    public function generateFacturX(Facture $facture): string
    {
        // 1. Génération XML CII (Cross Industry Invoice)
        $xmlCII = $this->generateXMLCII($facture);
        
        // 2. Validation XML selon schéma
        $this->validateXMLCII($xmlCII);
        
        // 3. Génération PDF/A-3
        $pdfContent = $this->generatePDFA3($facture);
        
        // 4. Intégration XML dans PDF (fichier attaché)
        $facturXContent = $this->embedXMLIntoPDF($pdfContent, $xmlCII);
        
        // 5. Signature numérique qualifiée
        $signedFacturX = $this->signFacturX($facturXContent);
        
        // 6. Sécurisation intégrité
        $this->integrityService->secureDocument($facture, $this->security->getUser(), '127.0.0.1');
        
        return $signedFacturX;
    }

    /**
     * Génère XML CII conforme au standard Factur-X
     */
    private function generateXMLCII(Facture $facture): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Élément racine selon norme UN/CEFACT CII
        $root = $xml->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $xml->appendChild($root);

        // 1. Contexte du document
        $context = $xml->createElement('rsm:ExchangedDocumentContext');
        $context->appendChild($this->createTextElement($xml, 'ram:GuidelineSpecifiedDocumentContextParameter', 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic'));
        $root->appendChild($context);

        // 2. En-tête du document
        $header = $xml->createElement('rsm:ExchangedDocument');
        $header->appendChild($this->createTextElement($xml, 'ram:ID', $facture->getNumeroFacture()));
        $header->appendChild($this->createTextElement($xml, 'ram:TypeCode', '380')); // Facture commerciale
        $header->appendChild($this->createDateTimeElement($xml, 'ram:IssueDateTime', $facture->getDateFacture()));
        $root->appendChild($header);

        // 3. Transaction commerciale
        $transaction = $xml->createElement('rsm:SupplyChainTradeTransaction');

        // 3.1 Parties
        $agreement = $xml->createElement('ram:ApplicableHeaderTradeAgreement');
        
        // Vendeur
        $seller = $xml->createElement('ram:SellerTradeParty');
        $seller->appendChild($this->createTextElement($xml, 'ram:Name', 'TechnoProd'));
        // TODO: Ajouter adresse, SIRET, etc.
        $agreement->appendChild($seller);

        // Acheteur
        $buyer = $xml->createElement('ram:BuyerTradeParty');
        $buyer->appendChild($this->createTextElement($xml, 'ram:Name', $facture->getProspect()->getNomComplet()));
        // TODO: Ajouter adresse acheteur
        $agreement->appendChild($buyer);

        $transaction->appendChild($agreement);

        // 3.2 Livraison
        $delivery = $xml->createElement('ram:ApplicableHeaderTradeDelivery');
        if ($facture->getCommandeOrigine()?->getDateLivraisonReelle()) {
            $delivery->appendChild($this->createDateTimeElement($xml, 'ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime', $facture->getCommandeOrigine()->getDateLivraisonReelle()));
        }
        $transaction->appendChild($delivery);

        // 3.3 Règlement
        $settlement = $xml->createElement('ram:ApplicableHeaderTradeSettlement');
        $settlement->appendChild($this->createTextElement($xml, 'ram:InvoiceCurrencyCode', 'EUR'));

        // Totaux par taux de TVA
        foreach ($this->calculerTotauxTVA($facture) as $tauxTva => $montants) {
            $taxTotal = $xml->createElement('ram:ApplicableTradeTax');
            $taxTotal->appendChild($this->createAmountElement($xml, 'ram:CalculatedAmount', $montants['tva']));
            $taxTotal->appendChild($this->createTextElement($xml, 'ram:TypeCode', 'VAT'));
            $taxTotal->appendChild($this->createAmountElement($xml, 'ram:BasisAmount', $montants['ht']));
            $taxTotal->appendChild($this->createTextElement($xml, 'ram:RateApplicablePercent', $tauxTva));
            $settlement->appendChild($taxTotal);
        }

        // Montants globaux
        $summation = $xml->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $summation->appendChild($this->createAmountElement($xml, 'ram:LineTotalAmount', $facture->getTotalHt()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:TaxBasisTotalAmount', $facture->getTotalHt()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:TaxTotalAmount', $facture->getTotalTva()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:GrandTotalAmount', $facture->getTotalTtc()));
        $summation->appendChild($this->createAmountElement($xml, 'ram:DuePayableAmount', $facture->getSoldeRestant()));
        $settlement->appendChild($summation);

        $transaction->appendChild($settlement);

        $root->appendChild($transaction);

        return $xml->saveXML();
    }

    /**
     * Génère PDF/A-3 avec DomPDF
     */
    private function generatePDFA3(Facture $facture): string
    {
        // Configuration DomPDF pour PDF/A-3
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // Police compatible PDF/A
        
        $dompdf = new Dompdf($options);
        
        // Template PDF optimisé pour PDF/A-3
        $html = $this->renderView('facture/pdf_facturx.html.twig', [
            'facture' => $facture,
            'metadata' => [
                'title' => 'Facture ' . $facture->getNumeroFacture(),
                'author' => 'TechnoProd',
                'subject' => 'Facture électronique Factur-X',
                'keywords' => 'Factur-X, facture, électronique'
            ]
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Intègre XML CII dans PDF comme fichier attaché
     */
    private function embedXMLIntoPDF(string $pdfContent, string $xmlContent): string
    {
        // Utilisation de TCPDF ou similaire pour intégration XML
        // TODO: Implémentation complète avec TCPDF
        
        // Pour l'instant, concaténation simple (à remplacer)
        return $pdfContent . "\n%XML_CII_EMBEDDED\n" . base64_encode($xmlContent);
    }

    /**
     * Signature numérique qualifiée du Factur-X
     */
    private function signFacturX(string $facturXContent): string
    {
        // Signature avec certificat qualifié
        $cert = file_get_contents($this->certificatePath);
        $privateKey = file_get_contents($this->privateKeyPath);
        
        // TODO: Implémentation signature PDF/A-3 complète
        // Utilisation d'une bibliothèque dédiée comme tcpdf ou setasign/fpdi
        
        return $facturXContent; // Placeholder
    }

    private function createTextElement(\DOMDocument $xml, string $name, string $value): \DOMElement
    {
        $element = $xml->createElement($name);
        $element->appendChild($xml->createTextNode($value));
        return $element;
    }

    private function createAmountElement(\DOMDocument $xml, string $name, string $amount): \DOMElement
    {
        $element = $xml->createElement($name);
        $element->setAttribute('currencyID', 'EUR');
        $element->appendChild($xml->createTextNode($amount));
        return $element;
    }
}
```

---

## 🎛️ 6. TABLEAU DE BORD CONFORMITÉ

### **6.1 Dashboard de Monitoring**

```php
<?php

namespace App\Controller;

class ComplianceDashboardController extends AbstractController
{
    #[Route('/admin/compliance', name: 'compliance_dashboard')]
    public function dashboard(
        DocumentIntegrityService $integrityService,
        AuditService $auditService,
        EntityManagerInterface $em
    ): Response {
        // Statistiques d'intégrité
        $integrityStats = $this->getIntegrityStatistics($em);
        
        // Vérifications récentes
        $recentVerifications = $this->getRecentVerifications($em);
        
        // Alertes de sécurité
        $securityAlerts = $this->getSecurityAlerts($em);
        
        // Conformité FEC
        $fecCompliance = $this->checkFECCompliance($em);

        return $this->render('admin/compliance_dashboard.html.twig', [
            'integrity_stats' => $integrityStats,
            'recent_verifications' => $recentVerifications,
            'security_alerts' => $securityAlerts,
            'fec_compliance' => $fecCompliance
        ]);
    }

    private function getIntegrityStatistics(EntityManagerInterface $em): array
    {
        $qb = $em->createQueryBuilder();
        
        return [
            'total_documents' => $qb->select('COUNT(di.id)')
                ->from(DocumentIntegrity::class, 'di')
                ->getQuery()->getSingleScalarResult(),
                
            'documents_verified_today' => $qb->select('COUNT(di.id)')
                ->from(DocumentIntegrity::class, 'di')
                ->where('DATE(di.lastVerification) = CURRENT_DATE()')
                ->getQuery()->getSingleScalarResult(),
                
            'integrity_violations' => $qb->select('COUNT(di.id)')
                ->from(DocumentIntegrity::class, 'di')
                ->where('di.integrityValid = false')
                ->getQuery()->getSingleScalarResult(),
                
            'blockchain_anchored' => $qb->select('COUNT(di.id)')
                ->from(DocumentIntegrity::class, 'di')
                ->where('di.blockchainTxHash IS NOT NULL')
                ->getQuery()->getSingleScalarResult()
        ];
    }
}
```

---

## 🚀 7. PLAN D'IMPLÉMENTATION

### **Phase 1 : Fondations Sécurité (3 semaines)**
```bash
Semaine 1:
- Création entités DocumentIntegrity et AuditTrail
- Service DocumentIntegrityService de base
- Tests unitaires sécurité cryptographique

Semaine 2:
- Service AuditService avec traçabilité automatique
- Intégration dans contrôleurs existants
- Middleware d'audit automatique

Semaine 3:
- Dashboard de monitoring conformité
- Commandes de vérification intégrité
- Documentation sécurité
```

### **Phase 2 : Comptabilité (4 semaines)**
```bash
Semaine 4-5:
- Entités comptables (CompteComptable, EcritureComptable, ExerciceComptable)
- Plan comptable français de base
- Service ComptabilisationService

Semaine 6-7:
- Automatisation comptabilisation factures/paiements
- Interface gestion plan comptable
- États comptables de base (balance, grand livre)
```

### **Phase 3 : FEC et Conformité (2 semaines)**
```bash
Semaine 8:
- Service FECGenerator complet
- Validation conformité FEC
- Export automatisé

Semaine 9:
- Préparation Factur-X (base)
- Tests de conformité
- Documentation utilisateur
```

## ✅ **VALIDATION FINALE**

Cette architecture garantit :
- ✅ **Conformité NF203** : Inaltérabilité cryptographique
- ✅ **Structure PCG** : Plan comptable et écritures automatiques  
- ✅ **Export FEC** : Conforme administration fiscale
- ✅ **Préparation Factur-X** : Base pour 2026
- ✅ **Évolutivité** : Architecture extensible
- ✅ **Performance** : Optimisée pour production

**Souhaitez-vous que je commence l'implémentation de cette architecture ?**