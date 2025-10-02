<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\Alerte;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Classe abstraite pour les détecteurs d'alertes automatiques
 *
 * Système unifié créant directement des entités Alerte configurées
 * à partir des AlerteType avec assignation rôles et sociétés.
 *
 * @version 2.0.0 - Système unifié
 */
abstract class AbstractAlerteDetector
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {}

    /**
     * Détecte les alertes pour un type donné
     *
     * @param AlerteType $alerteType Type d'alerte à détecter
     * @return array<int, Alerte> Liste des alertes détectées
     */
    abstract public function detect(AlerteType $alerteType): array;

    /**
     * Retourne le type d'entité géré par ce détecteur
     *
     * @return string Nom complet de la classe (ex: App\Entity\Client)
     */
    abstract public function getEntityType(): string;

    /**
     * Génère le message d'alerte en fonction de l'entité et des métadonnées
     *
     * @param int $entityId ID de l'entité concernée
     * @param array $metadata Métadonnées contextuelles
     * @return string Message d'alerte généré
     */
    abstract protected function generateMessage(int $entityId, array $metadata): string;

    /**
     * Crée une instance d'alerte unifiée depuis un type d'alerte
     *
     * @param AlerteType $alerteType Configuration du type d'alerte
     * @param int $entityId ID de l'entité concernée
     * @param array $metadata Métadonnées additionnelles
     * @return Alerte Instance d'alerte configurée
     */
    protected function createInstance(AlerteType $alerteType, int $entityId, array $metadata = []): Alerte
    {
        $alerte = new Alerte();

        // Configuration depuis AlerteType
        $alerte->setTitre($alerteType->getNom());
        $alerte->setMessage($this->generateMessage($entityId, $metadata));

        // Mapping severity → type Bootstrap
        $type = match($alerteType->getSeverity()) {
            'info' => 'info',
            'warning' => 'warning',
            'error' => 'danger',
            'success' => 'success',
            default => 'warning'
        };
        $alerte->setType($type);

        // Configuration alerte automatique
        $alerte->setDetectorClass(static::class);
        $alerte->setEntityType($this->getEntityType());
        $alerte->setEntityId($entityId);
        $alerte->setMetadata($metadata);

        // Copie de la configuration de ciblage
        $alerte->setCibles($alerteType->getRolesCibles() ?? []);
        $alerte->setSocietesCibles($alerteType->getSocietesCibles() ?? []);

        // Configuration commune
        $alerte->setIsActive($alerteType->isActif());
        $alerte->setOrdre(0); // L'ordre sera récupéré depuis AlerteType, ne pas le copier
        $alerte->setDismissible(false); // Alertes automatiques non-fermables
        $alerte->setResolved(false);

        return $alerte;
    }

    /**
     * Vérifie si une alerte non résolue existe déjà pour cette entité
     *
     * @param AlerteType $alerteType Type d'alerte à vérifier
     * @param int $entityId ID de l'entité concernée
     * @return bool True si une alerte active existe déjà
     */
    protected function instanceExists(AlerteType $alerteType, int $entityId): bool
    {
        return $this->entityManager->getRepository(Alerte::class)
            ->findOneBy([
                'detectorClass' => static::class,
                'entityType' => $this->getEntityType(),
                'entityId' => $entityId,
                'resolved' => false
            ]) !== null;
    }

    /**
     * Résout automatiquement les alertes obsolètes (entités devenues inactives ou problèmes résolus)
     *
     * @param AlerteType $alerteType Type d'alerte
     * @param array<int> $currentEntityIds IDs des entités actuellement détectées
     * @return int Nombre d'alertes résolues
     */
    protected function resolveObsoleteInstances(AlerteType $alerteType, array $currentEntityIds): int
    {
        // Récupérer toutes les alertes non résolues pour ce détecteur
        $existingAlertes = $this->entityManager->getRepository(Alerte::class)
            ->findBy([
                'detectorClass' => static::class,
                'entityType' => $this->getEntityType(),
                'resolved' => false
            ]);

        $resolvedCount = 0;

        foreach ($existingAlertes as $alerte) {
            // Si l'entité n'est plus dans la liste des détections actuelles, résoudre l'alerte
            if (!in_array($alerte->getEntityId(), $currentEntityIds)) {
                $alerte->setResolved(true);
                $alerte->setDateResolution(new \DateTimeImmutable());
                $alerte->setResolvedBy(null); // Résolution automatique
                $this->entityManager->persist($alerte);
                $resolvedCount++;
            }
        }

        if ($resolvedCount > 0) {
            $this->entityManager->flush();
        }

        return $resolvedCount;
    }

    public function getName(): string
    {
        $parts = explode('\\', static::class);
        return str_replace('Detector', '', end($parts));
    }

    public function getDescription(): string
    {
        return 'Détecteur pour ' . $this->getName();
    }
}