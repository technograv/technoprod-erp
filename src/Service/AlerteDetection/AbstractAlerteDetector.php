<?php

namespace App\Service\AlerteDetection;

use App\Entity\AlerteType;
use App\Entity\AlerteInstance;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractAlerteDetector
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {}

    abstract public function detect(AlerteType $alerteType): array;

    abstract public function getEntityType(): string;

    protected function createInstance(AlerteType $alerteType, int $entityId, array $metadata = []): AlerteInstance
    {
        $instance = new AlerteInstance();
        $instance->setAlerteType($alerteType);
        $instance->setEntityType($this->getEntityType());
        $instance->setEntityId($entityId);
        $instance->setMetadata($metadata);

        $entity = $this->entityManager->find($this->getEntityType(), $entityId);
        if ($entity && method_exists($entity, 'getSociete')) {
            $instance->setSociete($entity->getSociete());
        }

        return $instance;
    }

    protected function instanceExists(AlerteType $alerteType, int $entityId): bool
    {
        return $this->entityManager->getRepository(AlerteInstance::class)
            ->findOneBy([
                'alerteType' => $alerteType,
                'entityType' => $this->getEntityType(),
                'entityId' => $entityId,
                'resolved' => false
            ]) !== null;
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