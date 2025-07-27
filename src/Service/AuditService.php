<?php

namespace App\Service;

use App\Entity\AuditTrail;
use App\Entity\User;
use App\Repository\AuditTrailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class AuditService
{
    private EntityManagerInterface $em;
    private AuditTrailRepository $auditRepo;
    private Security $security;
    private RequestStack $requestStack;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        AuditTrailRepository $auditRepo,
        Security $security,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->auditRepo = $auditRepo;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Enregistre automatiquement toute modification
     */
    public function logEntityChange(
        object $entity,
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?string $justification = null,
        ?User $user = null
    ): AuditTrail {
        
        $request = $this->requestStack->getCurrentRequest();
        $currentUser = $user ?? $this->security->getUser();
        
        if (!$currentUser instanceof User) {
            throw new \InvalidArgumentException('Utilisateur requis pour l\'audit');
        }
        
        $audit = new AuditTrail();
        $audit->setEntityType(get_class($entity));
        $audit->setEntityId($this->getEntityId($entity));
        $audit->setAction($action);
        $audit->setOldValues($this->sanitizeValues($oldValues));
        $audit->setNewValues($this->sanitizeValues($newValues));
        $audit->setChangedFields($this->calculateChangedFields($oldValues, $newValues));
        $audit->setUser($currentUser);
        $audit->setTimestamp(new \DateTime());
        $audit->setIpAddress($request?->getClientIp() ?? '127.0.0.1');
        $audit->setUserAgent($request?->headers->get('User-Agent'));
        $audit->setSessionId($request?->getSession()?->getId());
        $audit->setJustification($justification);
        
        // Métadonnées contextuelles
        $metadata = [
            'php_version' => PHP_VERSION,
            'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'request_method' => $request?->getMethod(),
            'request_uri' => $request?->getRequestUri(),
            'user_roles' => $currentUser->getRoles(),
            'entity_class_short' => $this->getShortClassName($entity)
        ];
        
        $audit->setMetadata($metadata);
        
        // Chaînage avec l'enregistrement précédent (avant persist pour éviter l'auto-référencement)
        $previousHash = $this->auditRepo->findLastAuditHash();
        $audit->setPreviousRecordHash($previousHash);
        
        // Hash de l'enregistrement audit pour chaînage (inclut le previousHash)
        $recordHash = $this->calculateAuditHash($audit);
        $audit->setRecordHash($recordHash);
        
        $this->em->persist($audit);
        $this->em->flush();
        
        // Log applicatif pour surveillance
        $this->logger->info('Entity change audited', [
            'entity_type' => get_class($entity),
            'entity_id' => $this->getEntityId($entity),
            'action' => $action,
            'user_id' => $currentUser->getId(),
            'user_email' => $currentUser->getEmail(),
            'changed_fields_count' => count($audit->getChangedFields()),
            'ip_address' => $audit->getIpAddress(),
            'audit_id' => $audit->getId()
        ]);
        
        return $audit;
    }

    /**
     * Enregistre une action sans modification de données
     */
    public function logAction(
        object $entity,
        string $action,
        ?string $details = null,
        array $metadata = []
    ): AuditTrail {
        return $this->logEntityChange(
            $entity,
            $action,
            [], // Pas d'anciennes valeurs
            [], // Pas de nouvelles valeurs
            $details,
            null
        );
    }

    /**
     * Enregistre une consultation de document
     */
    public function logView(object $entity, ?string $details = null): AuditTrail
    {
        return $this->logAction($entity, 'VIEW', $details);
    }

    /**
     * Enregistre un export de données
     */
    public function logExport(string $exportType, array $criteria = [], ?int $recordCount = null): AuditTrail
    {
        // Création d'un objet fictif pour l'audit d'export
        $exportEntity = new class {
            public function getId() { return 0; }
        };
        
        $metadata = [
            'export_type' => $exportType,
            'criteria' => $criteria,
            'record_count' => $recordCount,
            'export_timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        $audit = $this->logEntityChange(
            $exportEntity,
            'EXPORT',
            [],
            ['export_type' => $exportType, 'criteria' => $criteria]
        );
        
        $audit->addMetadata('export_details', $metadata);
        $this->em->flush();
        
        return $audit;
    }

    /**
     * Vérifie la chaîne d'audit
     */
    public function verifyAuditChain(int $limit = 1000): array
    {
        $result = $this->auditRepo->verifyAuditChain($limit);
        
        if (!$result['valid']) {
            $this->logger->critical('Audit chain integrity compromised', [
                'total_records' => $result['total_records'],
                'errors_count' => count($result['errors']),
                'errors' => $result['errors']
            ]);
        }
        
        return $result;
    }

    /**
     * Recherche d'activités suspectes
     */
    public function detectSuspiciousActivities(): array
    {
        $suspiciousActivities = $this->auditRepo->findSuspiciousActivities();
        
        if (!empty($suspiciousActivities['out_of_hours']) || !empty($suspiciousActivities['bulk_deletes'])) {
            $this->logger->warning('Suspicious activities detected', [
                'out_of_hours_count' => count($suspiciousActivities['out_of_hours']),
                'bulk_deletes_count' => count($suspiciousActivities['bulk_deletes'])
            ]);
        }
        
        return $suspiciousActivities;
    }

    /**
     * Génère un rapport d'audit pour une entité
     */
    public function generateEntityAuditReport(object $entity): array
    {
        $entityType = get_class($entity);
        $entityId = $this->getEntityId($entity);
        
        $audits = $this->auditRepo->findByEntity($entityType, $entityId);
        
        $report = [
            'entity' => [
                'type' => $entityType,
                'id' => $entityId,
                'short_name' => $this->getShortClassName($entity)
            ],
            'audit_summary' => [
                'total_actions' => count($audits),
                'first_action' => $audits ? end($audits)->getTimestamp() : null,
                'last_action' => $audits ? $audits[0]->getTimestamp() : null,
                'unique_users' => count(array_unique(array_map(fn($a) => $a->getUser()->getId(), $audits)))
            ],
            'actions_breakdown' => [],
            'users_involved' => [],
            'timeline' => []
        ];
        
        // Répartition par action
        $actionCounts = [];
        $userCounts = [];
        
        foreach ($audits as $audit) {
            $action = $audit->getAction();
            $userId = $audit->getUser()->getId();
            
            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
            $userCounts[$userId] = ($userCounts[$userId] ?? 0) + 1;
            
            $report['timeline'][] = [
                'timestamp' => $audit->getTimestamp(),
                'action' => $audit->getActionDescription(),
                'user' => $audit->getUser()->getPrenom() . ' ' . $audit->getUser()->getNom(),
                'changed_fields' => $audit->getChangedFieldsCount(),
                'criticality' => $audit->getCriticalityLevel()
            ];
        }
        
        $report['actions_breakdown'] = $actionCounts;
        
        // Utilisateurs impliqués
        foreach ($userCounts as $userId => $count) {
            $user = $this->em->getRepository(User::class)->find($userId);
            if ($user) {
                $report['users_involved'][] = [
                    'user_id' => $userId,
                    'name' => $user->getPrenom() . ' ' . $user->getNom(),
                    'email' => $user->getEmail(),
                    'actions_count' => $count
                ];
            }
        }
        
        return $report;
    }

    /**
     * Archive les anciens audits (pour performance)
     */
    public function archiveOldAudits(\DateTime $beforeDate): int
    {
        // Cette méthode devrait idéalement déplacer vers une table d'archive
        // Pour l'instant, on se contente de marquer comme archivés
        
        $qb = $this->em->createQueryBuilder();
        $count = $qb->update(AuditTrail::class, 'at')
            ->set('at.metadata', 'JSON_SET(at.metadata, \'$.archived\', true)')
            ->where('at.timestamp < :beforeDate')
            ->setParameter('beforeDate', $beforeDate)
            ->getQuery()
            ->execute();
            
        $this->logger->info('Audit records archived', [
            'count' => $count,
            'before_date' => $beforeDate->format('Y-m-d')
        ]);
        
        return $count;
    }

    // MÉTHODES PRIVÉES

    /**
     * Calcule le hash d'un enregistrement audit
     */
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
            'ip_address' => $audit->getIpAddress(),
            'previous_hash' => $audit->getPreviousRecordHash() // Inclure le hash précédent pour le chaînage
        ];
        
        return hash('sha256', json_encode($data, 64 | \JSON_UNESCAPED_UNICODE)); // 64 = JSON_SORT_KEYS
    }

    /**
     * Calcule les champs modifiés
     */
    private function calculateChangedFields(array $oldValues, array $newValues): array
    {
        $changed = [];
        
        // Vérification des nouvelles valeurs
        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changed[] = $field;
            }
        }
        
        // Vérification des valeurs supprimées
        foreach ($oldValues as $field => $oldValue) {
            if (!array_key_exists($field, $newValues)) {
                $changed[] = $field;
            }
        }
        
        return array_unique($changed);
    }

    /**
     * Nettoie les valeurs pour éviter la fuite de données sensibles
     */
    private function sanitizeValues(array $values): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'hash'];
        $sanitized = [];
        
        foreach ($values as $field => $value) {
            $fieldLower = strtolower($field);
            
            // Masquer les champs sensibles
            $isSensitive = false;
            foreach ($sensitiveFields as $sensitive) {
                if (strpos($fieldLower, $sensitive) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$field] = '[MASKED]';
            } else {
                // Limiter la taille des valeurs
                if (is_string($value) && strlen($value) > 1000) {
                    $sanitized[$field] = substr($value, 0, 997) . '...';
                } else {
                    $sanitized[$field] = $value;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Récupère l'ID d'une entité
     */
    private function getEntityId(object $entity): string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            // Convertir en string pour supporter les identifiants de différents types
            return $id !== null ? (string)$id : '0';
        }
        
        throw new \InvalidArgumentException('Entity must have getId() method');
    }

    /**
     * Récupère le nom court d'une classe
     */
    private function getShortClassName(object $entity): string
    {
        $className = get_class($entity);
        return basename(str_replace('\\', '/', $className));
    }

    /**
     * Retourne les statistiques d'audit
     */
    public function getAuditStatistics(\DateTime $dateFrom, \DateTime $dateTo): array
    {
        return [
            'actions_stats' => $this->auditRepo->getAuditStatsByPeriod($dateFrom, $dateTo),
            'users_stats' => $this->auditRepo->getUserActivityStats($dateFrom, $dateTo),
            'chain_integrity' => $this->verifyAuditChain(100),
            'suspicious_activities' => $this->detectSuspiciousActivities()
        ];
    }
}