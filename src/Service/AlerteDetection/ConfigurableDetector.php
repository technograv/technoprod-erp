<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Détecteur configurable permettant de créer des alertes automatiques
 * sans écrire de code PHP, basé sur des règles JSON
 *
 * Format de configuration attendu dans AlerteType :
 * {
 *   "entity": "App\\Entity\\Client",
 *   "conditions": [
 *     {
 *       "type": "field_empty",
 *       "field": "email"
 *     },
 *     {
 *       "type": "relation_empty",
 *       "relation": "contacts"
 *     }
 *   ],
 *   "message_template": "Le {entity_name} n'a pas de {field}",
 *   "name_field": "nomEntreprise"
 * }
 */
class ConfigurableDetector extends AbstractAlerteDetector
{
    private ?array $config = null;
    private ?string $entityClass = null;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager);
    }

    /**
     * Configure le détecteur avec une configuration JSON
     */
    public function configure(array $config): void
    {
        $this->config = $config;
        $this->entityClass = $config['entity'] ?? null;
    }

    public function detect(AlerteType $alerteType): array
    {
        // Récupérer la configuration depuis AlerteType
        $config = $alerteType->getConfiguration();
        if (!$config || !isset($config['entity'])) {
            return [];
        }

        $this->configure($config);

        $entityClass = $config['entity'];
        $conditions = $config['conditions'] ?? [];

        if (empty($conditions)) {
            return [];
        }

        // Vérifier si c'est une détection de doublons
        $hasDuplicateCondition = false;
        $duplicateField = null;
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') === 'field_duplicate') {
                $hasDuplicateCondition = true;
                $duplicateField = $condition['field'];
                break;
            }
        }

        // Si c'est une détection de doublons, utiliser une logique spéciale
        if ($hasDuplicateCondition && $duplicateField) {
            return $this->detectDuplicates($alerteType, $entityClass, $duplicateField, $config);
        }

        // Sinon, logique normale
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($entityClass, 'e');

        // Pour les clients, filtrer automatiquement les clients inactifs et archivés
        if ($entityClass === 'App\Entity\Client') {
            $qb->innerJoin('e.contacts', 'active_contacts')
               ->andWhere('active_contacts.actif = true')
               ->andWhere('e.statut != :statutArchive')
               ->setParameter('statutArchive', 'archivé')
               ->groupBy('e.id')
               ->having('COUNT(active_contacts.id) > 0');
        }

        $joinIndex = 0;
        foreach ($conditions as $condition) {
            $this->applyCondition($qb, $condition, $joinIndex);
        }

        try {
            $entities = $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            // Log l'erreur et retourne un tableau vide
            error_log("ConfigurableDetector error: " . $e->getMessage());
            return [];
        }

        $instances = [];
        $currentEntityIds = [];

        foreach ($entities as $entity) {
            $entityId = $entity->getId();
            $currentEntityIds[] = $entityId;

            if (!$this->instanceExists($alerteType, $entityId)) {
                // Extraire le nom de l'entité
                $nameField = $config['name_field'] ?? 'id';
                $entityName = $this->extractEntityName($entity, $nameField);

                $instances[] = $this->createInstance($alerteType, $entityId, [
                    'entity_name' => $entityName,
                    'entity_class' => $entityClass,
                    'conditions' => $conditions
                ]);
            }
        }

        // Résoudre automatiquement les alertes obsolètes
        $this->resolveObsoleteInstances($alerteType, $currentEntityIds);

        return $instances;
    }

    /**
     * Détecte les entités en doublon sur un champ donné
     */
    private function detectDuplicates(AlerteType $alerteType, string $entityClass, string $field, array $config): array
    {
        try {
            // Construire la requête de base pour les doublons
            $qb = $this->entityManager->createQueryBuilder()
                ->select("e.{$field}")
                ->from($entityClass, 'e')
                ->where("e.{$field} IS NOT NULL")
                ->andWhere("e.{$field} != ''");

            // Pour les clients, filtrer les archivés ET ceux sans contacts actifs
            if ($entityClass === 'App\Entity\Client') {
                $qb->innerJoin('e.contacts', 'active_contacts')
                   ->andWhere('active_contacts.actif = true')
                   ->andWhere('e.statut != :statutArchive')
                   ->setParameter('statutArchive', 'archivé');
            }

            $qb->groupBy("e.{$field}");

            // Pour les clients, ajouter le filtre HAVING sur les contacts actifs
            // IMPORTANT: Utiliser COUNT(DISTINCT e.id) car un client avec plusieurs contacts serait compté plusieurs fois
            if ($entityClass === 'App\Entity\Client') {
                $qb->having("COUNT(DISTINCT e.id) > 1 AND COUNT(active_contacts.id) > 0");
            } else {
                $qb->having("COUNT(DISTINCT e.id) > 1");
            }

            // Trouver les valeurs en doublon
            $duplicateValues = $qb->getQuery()->getResult();

            if (empty($duplicateValues)) {
                return [];
            }

            // Extraire les valeurs
            $values = array_column($duplicateValues, $field);

            // Récupérer toutes les entités avec ces valeurs
            $qb2 = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from($entityClass, 'e')
                ->where("e.{$field} IN (:values)")
                ->setParameter('values', $values);

            // Pour les clients, filtrer les archivés ET ceux sans contacts actifs
            if ($entityClass === 'App\Entity\Client') {
                $qb2->innerJoin('e.contacts', 'active_contacts2')
                    ->andWhere('active_contacts2.actif = true')
                    ->andWhere('e.statut != :statutArchive2')
                    ->setParameter('statutArchive2', 'archivé')
                    ->groupBy('e.id')
                    ->having('COUNT(active_contacts2.id) > 0');
            }

            $entities = $qb2->orderBy("e.{$field}", 'ASC')
                ->addOrderBy('e.id', 'ASC')
                ->getQuery()
                ->getResult();

            $instances = [];
            $currentEntityIds = [];
            $nameField = $config['name_field'] ?? 'id';

            foreach ($entities as $entity) {
                $entityId = $entity->getId();
                $currentEntityIds[] = $entityId;

                if (!$this->instanceExists($alerteType, $entityId)) {
                    $entityName = $this->extractEntityName($entity, $nameField);
                    $fieldValue = $this->extractEntityName($entity, $field);

                    $instances[] = $this->createInstance($alerteType, $entityId, [
                        'entity_name' => $entityName,
                        'duplicate_field' => $field,
                        'duplicate_value' => $fieldValue,
                        'entity_class' => $entityClass
                    ]);
                }
            }

            // Résoudre automatiquement les alertes obsolètes
            $this->resolveObsoleteInstances($alerteType, $currentEntityIds);

            return $instances;

        } catch (\Exception $e) {
            error_log("ConfigurableDetector duplicate detection error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Applique une condition à la requête QueryBuilder
     */
    private function applyCondition($qb, array $condition, int &$joinIndex): void
    {
        $type = $condition['type'] ?? null;

        switch ($type) {
            case 'field_empty':
                // Champ vide ou null
                $field = $condition['field'];
                $qb->andWhere("e.{$field} IS NULL OR e.{$field} = :empty_{$field}")
                   ->setParameter("empty_{$field}", '');
                break;

            case 'field_not_empty':
                // Champ non vide
                $field = $condition['field'];
                $qb->andWhere("e.{$field} IS NOT NULL AND e.{$field} != ''");
                break;

            case 'relation_empty':
                // Relation vide (ex: client sans contacts)
                $relation = $condition['relation'];
                $alias = 'rel_' . $joinIndex++;

                $qb->leftJoin("e.{$relation}", $alias)
                   ->groupBy('e.id')
                   ->having("COUNT({$alias}.id) = 0");
                break;

            case 'relation_not_empty':
                // Relation non vide
                $relation = $condition['relation'];
                $alias = 'rel_' . $joinIndex++;

                $qb->innerJoin("e.{$relation}", $alias)
                   ->groupBy('e.id')
                   ->having("COUNT({$alias}.id) > 0");
                break;

            case 'field_equals':
                // Champ égal à une valeur
                $field = $condition['field'];
                $value = $condition['value'];
                $qb->andWhere("e.{$field} = :val_{$field}")
                   ->setParameter("val_{$field}", $value);
                break;

            case 'field_not_equals':
                // Champ différent d'une valeur
                $field = $condition['field'];
                $value = $condition['value'];
                $qb->andWhere("e.{$field} != :val_{$field}")
                   ->setParameter("val_{$field}", $value);
                break;

            case 'date_before':
                // Date antérieure à maintenant
                $field = $condition['field'];
                $qb->andWhere("e.{$field} < :now_{$field}")
                   ->setParameter("now_{$field}", new \DateTime());
                break;

            case 'date_after':
                // Date postérieure à maintenant
                $field = $condition['field'];
                $qb->andWhere("e.{$field} > :now_{$field}")
                   ->setParameter("now_{$field}", new \DateTime());
                break;

            case 'number_less_than':
                // Nombre inférieur à
                $field = $condition['field'];
                $value = $condition['value'];
                $qb->andWhere("e.{$field} < :val_{$field}")
                   ->setParameter("val_{$field}", $value);
                break;

            case 'number_greater_than':
                // Nombre supérieur à
                $field = $condition['field'];
                $value = $condition['value'];
                $qb->andWhere("e.{$field} > :val_{$field}")
                   ->setParameter("val_{$field}", $value);
                break;
        }
    }

    /**
     * Extrait le nom de l'entité selon le champ configuré
     */
    private function extractEntityName($entity, string $nameField): string
    {
        if ($nameField === 'id') {
            return '#' . $entity->getId();
        }

        $getter = 'get' . ucfirst($nameField);
        if (method_exists($entity, $getter)) {
            $value = $entity->$getter();
            return $value ?: '#' . $entity->getId();
        }

        return '#' . $entity->getId();
    }

    public function getEntityType(): string
    {
        return $this->entityClass ?? 'App\Entity\Generic';
    }

    protected function generateMessage(int $entityId, array $metadata): string
    {
        $template = $this->config['message_template'] ?? 'Alerte détectée pour {entity_name}';
        $entityName = $metadata['entity_name'] ?? '#' . $entityId;

        // Remplacer les variables dans le template
        $message = str_replace('{entity_name}', $entityName, $template);
        $message = str_replace('{entity_id}', $entityId, $message);

        // Si c'est un doublon, ajouter l'information
        if (isset($metadata['duplicate_field']) && isset($metadata['duplicate_value'])) {
            $fieldLabel = ucfirst($metadata['duplicate_field']);
            $message .= sprintf(' (doublon sur %s: "%s")', $fieldLabel, $metadata['duplicate_value']);
        }

        return $message;
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'Détecteur configurable';
    }

    public function getDescription(): string
    {
        return $this->config['description'] ?? 'Détecteur basé sur des règles configurables';
    }
}
