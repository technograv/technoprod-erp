<?php

namespace App\Service;

use App\Entity\AlerteType;
use App\Entity\AlerteInstance;
use App\Entity\User;
use App\Service\AlerteDetection\AbstractAlerteDetector;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AlerteManager
{
    private array $detectors = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function registerDetector(AbstractAlerteDetector $detector): void
    {
        $this->detectors[$detector::class] = $detector;
    }

    public function getDetectors(): array
    {
        return $this->detectors;
    }

    public function getDetector(string $className): ?AbstractAlerteDetector
    {
        return $this->detectors[$className] ?? null;
    }

    public function runDetection(?AlerteType $alerteType = null): array
    {
        $results = [];

        if ($alerteType) {
            $alerteTypes = [$alerteType];
        } else {
            $alerteTypes = $this->entityManager
                ->getRepository(AlerteType::class)
                ->findBy(['actif' => true]);
        }

        foreach ($alerteTypes as $type) {
            $detector = $this->getDetector($type->getClasseDetection());

            if (!$detector) {
                $this->logger->warning('Détecteur non trouvé', [
                    'alerte_type' => $type->getId(),
                    'classe' => $type->getClasseDetection()
                ]);
                continue;
            }

            try {
                $instances = $detector->detect($type);

                foreach ($instances as $instance) {
                    $this->entityManager->persist($instance);
                }

                $results[$type->getId()] = count($instances);

                $this->logger->info('Détection exécutée', [
                    'alerte_type' => $type->getId(),
                    'instances_créées' => count($instances)
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la détection', [
                    'alerte_type' => $type->getId(),
                    'error' => $e->getMessage()
                ]);
                $results[$type->getId()] = 'error: ' . $e->getMessage();
            }
        }

        $this->entityManager->flush();
        return $results;
    }

    public function getAlertsForUser(User $user): array
    {
        $userRoles = $user->getRoles();
        $userSociete = $user->getSociete();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('ai', 'at')
            ->from(AlerteInstance::class, 'ai')
            ->join('ai.alerteType', 'at')
            ->where('ai.resolved = false')
            ->andWhere('at.actif = true')
            ->orderBy('ai.dateDetection', 'DESC');

        $instances = $qb->getQuery()->getResult();

        $filteredInstances = [];
        foreach ($instances as $instance) {
            $alerteType = $instance->getAlerteType();

            $hasRoleAccess = empty($alerteType->getRolesCibles()) ||
                array_intersect($userRoles, $alerteType->getRolesCibles());

            $hasSocieteAccess = empty($alerteType->getSocietesCibles()) ||
                ($userSociete && in_array($userSociete->getId(), $alerteType->getSocietesCibles())) ||
                (!$instance->getSociete());

            if ($hasRoleAccess && $hasSocieteAccess) {
                $filteredInstances[] = $instance;
            }
        }

        return $filteredInstances;
    }

    public function resolveInstance(AlerteInstance $instance, User $user, string $commentaire = null): void
    {
        $instance->resolve($user, $commentaire);
        $this->entityManager->flush();

        $this->logger->info('Instance résolue', [
            'instance_id' => $instance->getId(),
            'user_id' => $user->getId(),
            'commentaire' => $commentaire
        ]);
    }

    public function getStatistics(): array
    {
        $stats = [
            'types_actifs' => $this->entityManager->getRepository(AlerteType::class)
                ->count(['actif' => true]),
            'instances_non_resolues' => $this->entityManager->getRepository(AlerteInstance::class)
                ->count(['resolved' => false]),
            'instances_resolues_aujourd_hui' => $this->entityManager->createQueryBuilder()
                ->select('COUNT(ai.id)')
                ->from(AlerteInstance::class, 'ai')
                ->where('ai.resolved = true')
                ->andWhere('ai.dateResolution >= :today')
                ->setParameter('today', new \DateTime('today'))
                ->getQuery()
                ->getSingleScalarResult()
        ];

        $typeStats = $this->entityManager->createQueryBuilder()
            ->select('at.nom, COUNT(ai.id) as nb_instances')
            ->from(AlerteType::class, 'at')
            ->leftJoin('at.instances', 'ai', 'WITH', 'ai.resolved = false')
            ->where('at.actif = true')
            ->groupBy('at.id', 'at.nom')
            ->getQuery()
            ->getResult();

        $stats['par_type'] = $typeStats;

        return $stats;
    }
}