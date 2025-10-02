<?php

namespace App\Service;

use App\Entity\Alerte;
use App\Entity\AlerteType;
use App\Entity\User;
use App\Service\AlerteDetection\AbstractAlerteDetector;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des alertes (manuelles et automatiques)
 *
 * Orchestre la détection automatique d'anomalies métier et la génération
 * d'alertes configurables selon les rôles utilisateur et sociétés.
 * Système unifié gérant à la fois les alertes manuelles et automatiques.
 *
 * @package App\Service
 * @author  Équipe TechnoProd
 * @since   2.0.0 - Système unifié
 * @version 2.0.0
 */
class AlerteManager
{
    private array $detectors = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Enregistre un détecteur d'alertes dans le système
     *
     * @param AbstractAlerteDetector $detector Le détecteur à enregistrer
     * @return void
     */
    public function registerDetector(AbstractAlerteDetector $detector): void
    {
        $this->detectors[$detector::class] = $detector;
    }

    /**
     * Retourne tous les détecteurs enregistrés
     *
     * @return array<string, AbstractAlerteDetector> Liste des détecteurs indexés par classe
     */
    public function getDetectors(): array
    {
        return $this->detectors;
    }

    /**
     * Récupère un détecteur spécifique par son nom de classe
     *
     * @param string $className Nom complet de la classe du détecteur
     * @return AbstractAlerteDetector|null Le détecteur ou null si non trouvé
     */
    public function getDetector(string $className): ?AbstractAlerteDetector
    {
        return $this->detectors[$className] ?? null;
    }

    /**
     * Lance la détection d'alertes pour un type spécifique ou tous les types actifs
     *
     * Exécute les détecteurs automatiques configurés et crée les alertes unifiées
     * correspondantes. Prend en compte les filtres par rôles et sociétés.
     *
     * @param AlerteType|null $alerteType Type d'alerte à détecter (null = tous actifs)
     * @return array<int, int> Nombre d'alertes créées par type d'alerte
     * @throws \Exception Si erreur lors de la détection
     *
     * @example
     * $results = $alerteManager->runDetection();
     * // Retourne [1 => 5, 2 => 3] (5 alertes type 1, 3 alertes type 2)
     */
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
                $alertes = $detector->detect($type);

                foreach ($alertes as $alerte) {
                    $this->entityManager->persist($alerte);
                }

                $results[$type->getId()] = count($alertes);

                $this->logger->info('Détection exécutée', [
                    'alerte_type' => $type->getId(),
                    'alertes_créées' => count($alertes)
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

    /**
     * Récupère les alertes non résolues visibles pour un utilisateur
     *
     * Applique les filtres de sécurité basés sur les rôles utilisateur
     * et les sociétés autorisées. Retourne à la fois les alertes manuelles
     * et automatiques selon les permissions de l'utilisateur.
     *
     * @param User $user L'utilisateur pour lequel récupérer les alertes
     * @return array<int, Alerte> Liste des alertes filtrées (manuelles + automatiques)
     *
     * @example
     * $alertes = $alerteManager->getAlertsForUser($currentUser);
     * foreach ($alertes as $alerte) {
     *     echo $alerte->getMessage(); // Affiche le message d'alerte
     * }
     */
    public function getAlertsForUser(User $user): array
    {
        $userRoles = $user->getRoles();
        $userSociete = $user->getSocietePrincipale();

        // Récupérer toutes les alertes non résolues et actives
        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Alerte::class, 'a')
            ->where('a.resolved = false')
            ->andWhere('a.isActive = true');

        $alertes = $qb->getQuery()->getResult();

        // Créer un cache des AlerteType par classeDetection pour éviter N+1 queries
        $alerteTypesCache = [];
        $alerteTypes = $this->entityManager->getRepository(\App\Entity\AlerteType::class)->findAll();
        foreach ($alerteTypes as $type) {
            $alerteTypesCache[$type->getClasseDetection()] = [
                'ordre' => $type->getOrdre(),
                'rolesCibles' => $type->getRolesCibles() ?? [],
                'societesCibles' => $type->getSocietesCibles() ?? []
            ];
        }

        // Trier en PHP : utiliser l'ordre d'AlerteType pour les auto, sinon l'ordre de l'alerte
        usort($alertes, function($a, $b) use ($alerteTypesCache) {
            $ordreA = 999;
            $ordreB = 999;

            // Récupérer l'ordre depuis AlerteType si c'est une alerte automatique
            if ($a->getDetectorClass() && isset($alerteTypesCache[$a->getDetectorClass()])) {
                $ordreA = $alerteTypesCache[$a->getDetectorClass()]['ordre'];
            } else {
                $ordreA = $a->getOrdre();
            }

            if ($b->getDetectorClass() && isset($alerteTypesCache[$b->getDetectorClass()])) {
                $ordreB = $alerteTypesCache[$b->getDetectorClass()]['ordre'];
            } else {
                $ordreB = $b->getOrdre();
            }

            if ($ordreA === $ordreB) {
                return $b->getCreatedAt() <=> $a->getCreatedAt(); // Plus récent d'abord
            }
            return $ordreA <=> $ordreB;
        });

        $filteredAlertes = [];
        foreach ($alertes as $alerte) {
            // Pour les alertes automatiques, récupérer les rôles depuis AlerteType
            if ($alerte->getDetectorClass() && isset($alerteTypesCache[$alerte->getDetectorClass()])) {
                $cibles = $alerteTypesCache[$alerte->getDetectorClass()]['rolesCibles'];
                $societesCibles = $alerteTypesCache[$alerte->getDetectorClass()]['societesCibles'];
            } else {
                // Alertes manuelles : utiliser leurs propres rôles
                $cibles = $alerte->getCibles() ?? [];
                $societesCibles = $alerte->getSocietesCibles() ?? [];
            }

            // Vérifier les rôles cibles (vide = tous les rôles)
            $hasRoleAccess = empty($cibles) || array_intersect($userRoles, $cibles);

            // Vérifier les sociétés cibles (vide = toutes les sociétés)
            $hasSocieteAccess = empty($societesCibles) ||
                ($userSociete && in_array($userSociete->getId(), $societesCibles));

            if ($hasRoleAccess && $hasSocieteAccess) {
                $filteredAlertes[] = $alerte;
            }
        }

        return $filteredAlertes;
    }

    /**
     * Marque une alerte comme résolue
     *
     * Met à jour l'alerte avec l'utilisateur qui l'a résolue,
     * la date de résolution et un commentaire optionnel.
     *
     * @param Alerte $alerte L'alerte à résoudre
     * @param User $user L'utilisateur qui résout l'alerte
     * @param string|null $commentaire Commentaire optionnel sur la résolution
     * @return void
     *
     * @example
     * $alerteManager->resolveAlerte($alerte, $currentUser, 'Problème corrigé');
     */
    public function resolveAlerte(Alerte $alerte, User $user, string $commentaire = null): void
    {
        $alerte->setResolved(true);
        $alerte->setDateResolution(new \DateTimeImmutable());
        $alerte->setResolvedBy($user);

        if ($commentaire) {
            $alerte->setCommentaire($commentaire);
        }

        $this->entityManager->flush();

        $this->logger->info('Alerte résolue', [
            'alerte_id' => $alerte->getId(),
            'user_id' => $user->getId(),
            'commentaire' => $commentaire
        ]);
    }

    /**
     * Génère des statistiques complètes sur le système d'alertes
     *
     * Calcule les indicateurs clés de performance pour le dashboard
     * d'administration et le suivi des alertes métier.
     *
     * @return array<string, mixed> Statistiques avec clés alertes_non_resolues, alertes_manuelles, etc.
     * @throws \Exception Si erreur lors des requêtes statistiques
     *
     * @example
     * $stats = $alerteManager->getStatistics();
     * echo "Alertes actives: " . $stats['alertes_non_resolues'];
     * echo "Alertes automatiques: " . $stats['alertes_automatiques'];
     */
    public function getStatistics(): array
    {
        $stats = [
            'types_actifs' => $this->entityManager->getRepository(AlerteType::class)
                ->count(['actif' => true]),
            'alertes_non_resolues' => $this->entityManager->getRepository(Alerte::class)
                ->count(['resolved' => false]),
            'alertes_resolues_aujourd_hui' => $this->entityManager->createQueryBuilder()
                ->select('COUNT(a.id)')
                ->from(Alerte::class, 'a')
                ->where('a.resolved = true')
                ->andWhere('a.dateResolution >= :today')
                ->setParameter('today', new \DateTime('today'))
                ->getQuery()
                ->getSingleScalarResult(),
            'alertes_manuelles' => $this->entityManager->createQueryBuilder()
                ->select('COUNT(a.id)')
                ->from(Alerte::class, 'a')
                ->where('a.detectorClass IS NULL')
                ->andWhere('a.resolved = false')
                ->getQuery()
                ->getSingleScalarResult(),
            'alertes_automatiques' => $this->entityManager->createQueryBuilder()
                ->select('COUNT(a.id)')
                ->from(Alerte::class, 'a')
                ->where('a.detectorClass IS NOT NULL')
                ->andWhere('a.resolved = false')
                ->getQuery()
                ->getSingleScalarResult()
        ];

        // Statistiques par type d'alerte automatique
        $autoStats = $this->entityManager->createQueryBuilder()
            ->select('a.detectorClass, COUNT(a.id) as nb_alertes')
            ->from(Alerte::class, 'a')
            ->where('a.detectorClass IS NOT NULL')
            ->andWhere('a.resolved = false')
            ->groupBy('a.detectorClass')
            ->getQuery()
            ->getResult();

        $stats['par_detecteur'] = $autoStats;

        return $stats;
    }
}