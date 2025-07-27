<?php

namespace App\Service;

use App\Entity\DocumentIntegrity;
use App\Entity\Devis;
use App\Entity\User;
use App\Repository\DocumentIntegrityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class DocumentIntegrityService
{
    private EntityManagerInterface $em;
    private DocumentIntegrityRepository $integrityRepo;
    private Security $security;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private string $privateKeyPath;
    private string $publicKeyPath;
    private bool $blockchainEnabled;

    public function __construct(
        EntityManagerInterface $em,
        DocumentIntegrityRepository $integrityRepo,
        Security $security,
        RequestStack $requestStack,
        LoggerInterface $logger,
        string $cryptoKeysPath = '/var/crypto',
        bool $blockchainEnabled = false
    ) {
        $this->em = $em;
        $this->integrityRepo = $integrityRepo;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->privateKeyPath = $cryptoKeysPath . '/private_key.pem';
        $this->publicKeyPath = $cryptoKeysPath . '/public_key.pem';
        $this->blockchainEnabled = $blockchainEnabled;
    }

    /**
     * Sécurise un document selon NF203
     */
    public function secureDocument(object $document, ?User $user = null, ?string $ipAddress = null): DocumentIntegrity
    {
        if (!$user) {
            $user = $this->security->getUser();
        }
        
        if (!$ipAddress) {
            $request = $this->requestStack->getCurrentRequest();
            $ipAddress = $request?->getClientIp() ?? '127.0.0.1';
        }

        // Vérification de l'ID du document
        if (!$this->getEntityId($document)) {
            throw new \InvalidArgumentException('Le document doit avoir un ID pour être sécurisé');
        }

        // 1. Calcul hash du document
        $documentHash = $this->calculateDocumentHash($document);
        
        // 2. Récupération hash précédent pour chaînage
        $documentType = $this->getDocumentType($document);
        $previousHash = $this->integrityRepo->findLastHashForDocumentType($documentType);
        
        // 3. Signature cryptographique
        $signatureData = $this->signHash($documentHash, $previousHash);
        
        // 4. Création enregistrement intégrité
        $integrity = new DocumentIntegrity();
        $integrity->setDocumentType($documentType);
        $integrity->setDocumentId($this->getEntityId($document));
        $integrity->setDocumentNumber($this->getDocumentNumber($document));
        $integrity->setDocumentHash($documentHash);
        $integrity->setPreviousHash($previousHash);
        $integrity->setSignatureData($signatureData);
        $integrity->setTimestampCreation(new \DateTime());
        $integrity->setCreatedBy($user);
        $integrity->setIpAddress($ipAddress);
        
        $request = $this->requestStack->getCurrentRequest();
        $integrity->setUserAgent($request?->headers->get('User-Agent'));
        
        // 5. Métadonnées conformité
        $integrity->setComplianceMetadata([
            'nf203_version' => '2014',
            'hash_algorithm' => 'SHA256',
            'signature_algorithm' => 'RSA-2048',
            'document_version' => $this->getDocumentVersion($document),
            'business_rules_version' => '1.0',
            'creation_timestamp' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail()
        ]);
        
        // 6. Sauvegarde
        $this->em->persist($integrity);
        $this->em->flush();
        
        // 7. Ancrage blockchain optionnel
        if ($this->blockchainEnabled) {
            $this->anchorToBlockchain($integrity);
        }
        
        $this->logger->info('Document secured successfully', [
            'document_type' => $documentType,
            'document_id' => $this->getEntityId($document),
            'document_number' => $this->getDocumentNumber($document),
            'hash' => $documentHash,
            'user_id' => $user->getId(),
            'ip_address' => $ipAddress
        ]);
        
        return $integrity;
    }

    /**
     * Vérifie l'intégrité d'un document
     */
    public function verifyDocumentIntegrity(object $document): array
    {
        $documentType = $this->getDocumentType($document);
        $documentId = $this->getEntityId($document);
        
        // Récupération enregistrement intégrité
        $integrity = $this->integrityRepo->findByDocument($documentType, $documentId);
            
        if (!$integrity) {
            return [
                'valid' => false,
                'error' => 'Aucun enregistrement d\'intégrité trouvé',
                'risk_level' => 'HIGH',
                'checks' => []
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
        $integrity->setLastVerification(new \DateTime());
        $integrity->setIntegrityValid($allValid);
        
        if (!$allValid) {
            $integrity->setStatus('compromised');
        }
        
        $this->em->flush();
        
        $result = [
            'valid' => $allValid,
            'checks' => $checks,
            'integrity_record' => $integrity,
            'risk_level' => $allValid ? 'LOW' : 'HIGH',
            'verification_timestamp' => new \DateTime()
        ];
        
        $this->logger->info('Document integrity verified', [
            'document_type' => $documentType,
            'document_id' => $documentId,
            'result' => $allValid ? 'VALID' : 'COMPROMISED',
            'checks' => array_map(fn($check) => $check['valid'], $checks)
        ]);
        
        return $result;
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
        
        // Extraction données métier critiques selon le type
        if ($document instanceof Devis) {
            $data = [
                'type' => 'devis',
                'numero' => $document->getNumeroDevis(),
                'date_creation' => $document->getDateCreation()->format('Y-m-d'),
                'date_validite' => $document->getDateValidite()->format('Y-m-d'),
                'client_id' => $document->getClient()->getId(),
                'commercial_id' => $document->getCommercial()->getId(),
                'statut' => $document->getStatut(),
                'montant_ht' => $document->getTotalHt(),
                'montant_tva' => $document->getTotalTva(),
                'montant_ttc' => $document->getTotalTtc(),
                'items' => $this->hashDevisItems($document->getDevisItems()),
                'acompte_percent' => $document->getAcomptePercent(),
                'acompte_montant' => $document->getAcompteMontant()
            ];
        }
        // TODO: Ajouter autres types (Facture, Commande, Avoir)
        else {
            // Fallback générique
            $data = [
                'type' => get_class($document),
                'id' => $this->getEntityId($document),
                'created_at' => method_exists($document, 'getCreatedAt') ? 
                    $document->getCreatedAt()->format('Y-m-d H:i:s') : 'unknown',
                'updated_at' => method_exists($document, 'getUpdatedAt') ? 
                    $document->getUpdatedAt()->format('Y-m-d H:i:s') : 'unknown'
            ];
        }
        
        // Sérialisation JSON normalisée
        return json_encode($data, \JSON_UNESCAPED_UNICODE | 64); // 64 = JSON_SORT_KEYS
    }

    /**
     * Hash des items d'un devis
     */
    private function hashDevisItems($items): array
    {
        $itemsData = [];
        
        foreach ($items as $item) {
            $itemsData[] = [
                'designation' => $item->getDesignation(),
                'description' => $item->getDescription(),
                'quantite' => $item->getQuantite(),
                'prix_unitaire_ht' => $item->getPrixUnitaireHt(),
                'tva_percent' => $item->getTvaPercent(),
                'total_ligne_ht' => $item->getTotalLigneHt(),
                'ordre_affichage' => $item->getOrdreAffichage()
            ];
        }
        
        return $itemsData;
    }

    /**
     * Signature RSA du hash
     */
    private function signHash(string $documentHash, ?string $previousHash): string
    {
        $dataToSign = $documentHash . ($previousHash ?? '');
        
        // Vérification existence clé privée
        if (!file_exists($this->privateKeyPath)) {
            throw new \RuntimeException('Clé privée RSA non trouvée: ' . $this->privateKeyPath);
        }
        
        // Chargement clé privée avec mot de passe
        $privateKey = openssl_pkey_get_private(
            file_get_contents($this->privateKeyPath),
            'TechnoProd2025' // TODO: Mettre en variable d'environnement
        );
        
        if (!$privateKey) {
            throw new \RuntimeException('Impossible de charger la clé privée RSA - vérifiez le mot de passe');
        }
        
        $signature = '';
        if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Erreur lors de la signature RSA');
        }
        
        return base64_encode($signature);
    }

    /**
     * Vérification signature RSA
     */
    private function verifySignature(DocumentIntegrity $integrity): array
    {
        try {
            $dataToVerify = $integrity->getDocumentHash() . ($integrity->getPreviousHash() ?? '');
            $signature = base64_decode($integrity->getSignatureData());
            
            if (!file_exists($this->publicKeyPath)) {
                return [
                    'valid' => false,
                    'error' => 'Clé publique RSA non trouvée',
                    'algorithm' => 'RSA-SHA256'
                ];
            }
            
            $publicKey = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
            
            if (!$publicKey) {
                return [
                    'valid' => false,
                    'error' => 'Clé publique RSA invalide',
                    'algorithm' => 'RSA-SHA256'
                ];
            }
            
            $valid = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
            
            return [
                'valid' => $valid,
                'algorithm' => 'RSA-SHA256',
                'verified_at' => new \DateTime(),
                'error' => $valid ? null : 'Signature RSA invalide'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Erreur lors de la vérification signature: ' . $e->getMessage(),
                'algorithm' => 'RSA-SHA256'
            ];
        }
    }

    /**
     * Vérifie le hash actuel du document
     */
    private function verifyDocumentHash(object $document, DocumentIntegrity $integrity): array
    {
        try {
            $currentHash = $this->calculateDocumentHash($document);
            $storedHash = $integrity->getDocumentHash();
            
            $valid = $currentHash === $storedHash;
            
            return [
                'valid' => $valid,
                'current_hash' => $currentHash,
                'stored_hash' => $storedHash,
                'algorithm' => 'SHA256',
                'error' => $valid ? null : 'Hash du document modifié'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Erreur lors du calcul de hash: ' . $e->getMessage(),
                'algorithm' => 'SHA256'
            ];
        }
    }

    /**
     * Vérifie la chaîne d'intégrité
     */
    private function verifyChainIntegrity(DocumentIntegrity $integrity): array
    {
        try {
            $documentType = $integrity->getDocumentType();
            
            // Trouve le document précédent dans la chaîne
            $previousIntegrity = $this->em->getRepository(DocumentIntegrity::class)
                ->createQueryBuilder('di')
                ->where('di.documentType = :documentType')
                ->andWhere('di.timestampCreation < :timestamp')
                ->setParameter('documentType', $documentType)
                ->setParameter('timestamp', $integrity->getTimestampCreation())
                ->orderBy('di.timestampCreation', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
                
            if (!$previousIntegrity) {
                // Premier document de la chaîne
                $valid = $integrity->getPreviousHash() === null;
                return [
                    'valid' => $valid,
                    'position' => 'first',
                    'error' => $valid ? null : 'Premier document ne devrait pas avoir de hash précédent'
                ];
            }
            
            $expectedPreviousHash = $previousIntegrity->getDocumentHash();
            $actualPreviousHash = $integrity->getPreviousHash();
            
            $valid = $expectedPreviousHash === $actualPreviousHash;
            
            return [
                'valid' => $valid,
                'position' => 'chained',
                'expected_previous_hash' => $expectedPreviousHash,
                'actual_previous_hash' => $actualPreviousHash,
                'previous_document_id' => $previousIntegrity->getId(),
                'error' => $valid ? null : 'Rupture de chaînage détectée'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Erreur lors de la vérification chaînage: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie la validité du timestamp
     */
    private function verifyTimestamp(DocumentIntegrity $integrity): array
    {
        try {
            $timestamp = $integrity->getTimestampCreation();
            $now = new \DateTime();
            
            // Vérifications de cohérence temporelle
            $checks = [
                'not_future' => $timestamp <= $now,
                'not_too_old' => $timestamp >= (clone $now)->modify('-10 years'),
                'format_valid' => true // Déjà validé par Doctrine
            ];
            
            $valid = array_reduce($checks, fn($carry, $check) => $carry && $check, true);
            
            return [
                'valid' => $valid,
                'timestamp' => $timestamp,
                'checks' => $checks,
                'error' => $valid ? null : 'Timestamp invalide ou incohérent'
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Erreur lors de la vérification timestamp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie l'ancrage blockchain (placeholder)
     */
    private function verifyBlockchainAnchor(DocumentIntegrity $integrity): array
    {
        // TODO: Implémentation future si blockchain activée
        return [
            'valid' => true,
            'blockchain_tx' => $integrity->getBlockchainTxHash(),
            'block_number' => $integrity->getBlockchainBlockNumber(),
            'note' => 'Vérification blockchain non implémentée'
        ];
    }

    /**
     * Ancrage blockchain optionnel (placeholder)
     */
    private function anchorToBlockchain(DocumentIntegrity $integrity): void
    {
        // TODO: Implémentation future
        $this->logger->info('Blockchain anchoring requested but not implemented', [
            'integrity_id' => $integrity->getId()
        ]);
    }

    // MÉTHODES UTILITAIRES

    private function getDocumentType(object $document): string
    {
        $className = get_class($document);
        return strtolower(basename(str_replace('\\', '/', $className)));
    }

    private function getEntityId(object $document): ?int
    {
        if (method_exists($document, 'getId')) {
            return $document->getId();
        }
        
        throw new \InvalidArgumentException('Document must have getId() method');
    }

    private function getDocumentNumber(object $document): string
    {
        // Essaie différentes méthodes selon le type
        $methods = ['getNumeroDevis', 'getNumeroFacture', 'getNumeroCommande', 'getNumeroAvoir', 'getNumero'];
        
        foreach ($methods as $method) {
            if (method_exists($document, $method)) {
                return $document->$method() ?? 'N/A';
            }
        }
        
        return 'DOC-' . $this->getEntityId($document);
    }

    private function getDocumentVersion(object $document): string
    {
        if (method_exists($document, 'getUpdatedAt')) {
            return $document->getUpdatedAt()->format('Y-m-d H:i:s');
        }
        
        return '1.0';
    }

    /**
     * Vérifie si les clés cryptographiques existent
     */
    public function areKeysAvailable(): bool
    {
        return file_exists($this->privateKeyPath) && file_exists($this->publicKeyPath);
    }

    /**
     * Retourne les statistiques d'intégrité
     */
    public function getIntegrityStatistics(): array
    {
        return $this->integrityRepo->getIntegrityStatsByPeriod(
            new \DateTime('-30 days'),
            new \DateTime()
        );
    }
}