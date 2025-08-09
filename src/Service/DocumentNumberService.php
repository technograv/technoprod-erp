<?php

namespace App\Service;

use App\Entity\Societe;
use Doctrine\ORM\EntityManagerInterface;

class DocumentNumberService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantService $tenantService
    ) {
    }

    /**
     * Génère un numéro de document selon le type et la société active
     */
    public function generateDocumentNumber(string $documentType): string
    {
        $societe = $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            throw new \RuntimeException('Aucune société sélectionnée pour générer un numéro de document');
        }

        // Récupérer le préfixe selon le type de document et la société
        $prefix = $this->getDocumentPrefix($documentType, $societe);
        
        // Récupérer le prochain numéro pour cette société et ce type
        $nextNumber = $this->getNextNumber($documentType, $societe);
        
        // Formater le numéro final
        return $this->formatDocumentNumber($prefix, $nextNumber, $documentType);
    }

    /**
     * Récupère le préfixe de document selon le type et la société
     */
    private function getDocumentPrefix(string $documentType, Societe $societe): string
    {
        // Récupérer le préfixe personnalisé depuis les paramètres de la société
        $paramKey = strtolower($documentType) . '_prefix';
        $customPrefix = $societe->getParametreCustom($paramKey);
        
        if ($customPrefix) {
            return $customPrefix;
        }

        // Héritage depuis société mère si c'est une fille
        if ($societe->isFille() && $societe->getSocieteParent()) {
            $parentPrefix = $societe->getSocieteParent()->getParametreCustom($paramKey);
            if ($parentPrefix) {
                return $parentPrefix;
            }
        }

        // Préfixes par défaut selon le type
        $defaultPrefixes = [
            'devis' => 'DEVIS-',
            'facture' => 'FACT-',
            'commande' => 'CMD-',
            'avoir' => 'AVOIR-',
        ];

        return $defaultPrefixes[strtolower($documentType)] ?? strtoupper($documentType) . '-';
    }

    /**
     * Récupère le prochain numéro pour un type de document et une société
     */
    private function getNextNumber(string $documentType, Societe $societe): int
    {
        // Pour l'instant, on utilise un compteur global par société mère (pas par fille)
        // car les sociétés filles partagent les mêmes numéros
        $societeMere = $societe->isFille() ? $societe->getSocieteParent() : $societe;
        
        $tableName = $this->getDocumentTableName($documentType);
        
        if (!$tableName) {
            // Si pas de table spécifique, utiliser un compteur générique
            return $this->getGenericCounter($documentType, $societeMere);
        }

        // Récupérer le dernier numéro utilisé dans la table
        $sql = "SELECT MAX(CAST(REGEXP_REPLACE(numero_{$documentType}, '[^0-9]', '', 'g') AS INTEGER)) 
                FROM {$tableName} 
                WHERE numero_{$documentType} IS NOT NULL 
                AND numero_{$documentType} ~ '[0-9]+'";
        
        try {
            $result = $this->entityManager->getConnection()->executeQuery($sql)->fetchOne();
            $lastNumber = $result ? (int) $result : 0;
        } catch (\Exception $e) {
            // En cas d'erreur SQL, utiliser un compteur générique
            $lastNumber = $this->getGenericCounter($documentType, $societeMere);
        }

        return $lastNumber + 1;
    }

    /**
     * Récupère le nom de table selon le type de document
     */
    private function getDocumentTableName(string $documentType): ?string
    {
        $tableMapping = [
            'devis' => 'devis',
            'facture' => 'facture',
            'commande' => 'commande',
            'avoir' => 'avoir',
        ];

        return $tableMapping[strtolower($documentType)] ?? null;
    }

    /**
     * Utilise un compteur générique stocké dans les paramètres de société
     */
    private function getGenericCounter(string $documentType, Societe $societeMere): int
    {
        $counterKey = 'counter_' . strtolower($documentType);
        $currentCounter = $societeMere->getParametreCustom($counterKey, 0);
        
        $newCounter = $currentCounter + 1;
        
        // Mettre à jour le compteur dans la société
        $societeMere->setParametreCustom($counterKey, $newCounter);
        $this->entityManager->flush();
        
        return $newCounter;
    }

    /**
     * Formate le numéro final avec préfixe et padding
     */
    private function formatDocumentNumber(string $prefix, int $number, string $documentType): string
    {
        // Récupérer la longueur de padding depuis les paramètres
        $societe = $this->tenantService->getCurrentSociete();
        $paddingLength = $societe?->getParametreCustom('number_padding', 4) ?? 4;
        
        // Ajouter la date pour certains types de documents
        $includeDate = in_array(strtolower($documentType), ['facture', 'avoir']);
        
        if ($includeDate) {
            $dateString = date('Y');
            return sprintf('%s%s-%s', $prefix, $dateString, str_pad($number, $paddingLength, '0', STR_PAD_LEFT));
        }

        return sprintf('%s%s', $prefix, str_pad($number, $paddingLength, '0', STR_PAD_LEFT));
    }

    /**
     * Valide qu'un numéro de document n'existe pas déjà
     */
    public function isDocumentNumberUnique(string $documentNumber, string $documentType, ?int $excludeId = null): bool
    {
        $tableName = $this->getDocumentTableName($documentType);
        
        if (!$tableName) {
            return true; // Si pas de table, on considère que c'est unique
        }

        $sql = "SELECT COUNT(*) FROM {$tableName} WHERE numero_{$documentType} = :numero";
        $params = ['numero' => $documentNumber];
        
        if ($excludeId) {
            $sql .= " AND id != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        try {
            $count = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchOne();
            return (int) $count === 0;
        } catch (\Exception $e) {
            return true; // En cas d'erreur, on considère comme unique
        }
    }

    /**
     * Récupère les statistiques de numérotation pour une société
     */
    public function getNumberingStatistics(?Societe $societe = null): array
    {
        $societe = $societe ?: $this->tenantService->getCurrentSociete();
        
        if (!$societe) {
            return [];
        }

        $societeMere = $societe->isFille() ? $societe->getSocieteParent() : $societe;
        $stats = [];

        $documentTypes = ['devis', 'facture', 'commande', 'avoir'];
        
        foreach ($documentTypes as $type) {
            $prefix = $this->getDocumentPrefix($type, $societe);
            $currentCounter = $societeMere->getParametreCustom('counter_' . $type, 0);
            
            $stats[$type] = [
                'prefix' => $prefix,
                'current_counter' => $currentCounter,
                'next_number' => $currentCounter + 1,
                'example' => $this->formatDocumentNumber($prefix, $currentCounter + 1, $type)
            ];
        }

        return $stats;
    }

    /**
     * Configure les préfixes de numérotation pour une société
     */
    public function configureNumberingForSociete(Societe $societe, array $prefixes): void
    {
        foreach ($prefixes as $documentType => $prefix) {
            $paramKey = strtolower($documentType) . '_prefix';
            $societe->setParametreCustom($paramKey, $prefix);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Réinitialise les compteurs pour une société (attention !)
     */
    public function resetCountersForSociete(Societe $societe, array $documentTypes = []): void
    {
        $societeMere = $societe->isFille() ? $societe->getSocieteParent() : $societe;
        $typesToReset = $documentTypes ?: ['devis', 'facture', 'commande', 'avoir'];
        
        foreach ($typesToReset as $type) {
            $counterKey = 'counter_' . strtolower($type);
            $societeMere->setParametreCustom($counterKey, 0);
        }
        
        $this->entityManager->flush();
    }

    /**
     * Prévisualise un numéro de document sans l'incrémenter
     */
    public function previewDocumentNumber(string $documentType, ?Societe $societe = null): string
    {
        $currentSociete = $this->tenantService->getCurrentSociete();
        
        // Temporairement changer de société si nécessaire
        if ($societe && $societe !== $currentSociete) {
            $this->tenantService->setCurrentSociete($societe);
        }

        try {
            $prefix = $this->getDocumentPrefix($documentType, $societe ?: $currentSociete);
            $nextNumber = $this->getNextNumber($documentType, $societe ?: $currentSociete);
            
            return $this->formatDocumentNumber($prefix, $nextNumber, $documentType);
        } finally {
            // Restaurer la société originale
            if ($societe && $currentSociete) {
                $this->tenantService->setCurrentSociete($currentSociete);
            }
        }
    }
}