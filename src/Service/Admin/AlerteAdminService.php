<?php

namespace App\Service\Admin;

use App\DTO\Alerte\AlerteCreateDto;
use App\DTO\Alerte\AlerteUpdateDto;
use App\Entity\Alerte;
use App\Service\AlerteService;
use App\Service\AlerteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Psr\Log\LoggerInterface;

class AlerteAdminService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private ValidatorInterface $validator,
        private AlerteService $alerteService,
        private AlerteManager $alerteManager
    ) {
    }

    public function getAllAlertes(): JsonResponse
    {
        $this->logger->info("Récupération de toutes les alertes admin (manuelles + types automatiques agrégés)");

        $alertesData = [];

        // 1. Récupérer les alertes MANUELLES
        $alertesManuelles = $this->entityManager->getRepository(Alerte::class)
            ->createQueryBuilder('a')
            ->where('a.detectorClass IS NULL')
            ->orderBy('a.ordre', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($alertesManuelles as $alerte) {
            $alertesData[] = [
                'id' => $alerte->getId(),
                'titre' => $alerte->getTitre(),
                'message' => $alerte->getMessage(),
                'type' => $alerte->getType(),
                'isActive' => $alerte->isActive(),
                'dismissible' => $alerte->isDismissible(),
                'ordre' => $alerte->getOrdre(),
                'cibles' => $alerte->getCibles(),
                'societesCibles' => $alerte->getSocietesCibles(),
                'dateExpiration' => $alerte->getDateExpiration() ? $alerte->getDateExpiration()->format('d/m/Y H:i') : null,
                'createdAt' => $alerte->getCreatedAt()->format('d/m/Y H:i'),
                'isExpired' => $alerte->isExpired(),
                'isManual' => true,
                'isAutomatic' => false,
                'resolved' => false,
                'instancesCount' => 0
            ];
        }

        // 2. Récupérer les types d'alertes AUTOMATIQUES avec compteur d'instances
        $alerteTypes = $this->entityManager->getRepository(\App\Entity\AlerteType::class)
            ->findBy([], ['ordre' => 'ASC']);

        foreach ($alerteTypes as $alerteType) {
            // Compter les instances non résolues pour ce type
            $instancesCount = $this->entityManager->createQueryBuilder()
                ->select('COUNT(a.id)')
                ->from(Alerte::class, 'a')
                ->where('a.detectorClass = :detector')
                ->andWhere('a.resolved = false')
                ->setParameter('detector', $alerteType->getClasseDetection())
                ->getQuery()
                ->getSingleScalarResult();

            $alertesData[] = [
                'id' => 'type_' . $alerteType->getId(), // Préfixe pour distinguer des alertes manuelles
                'titre' => $alerteType->getNom(),
                'message' => $alerteType->getDescription() ?? 'Alerte automatique configurée',
                'type' => match($alerteType->getSeverity()) {
                    'info' => 'info',
                    'warning' => 'warning',
                    'error' => 'danger',
                    'success' => 'success',
                    default => 'warning'
                },
                'isActive' => $alerteType->isActif(),
                'dismissible' => false,
                'ordre' => $alerteType->getOrdre(),
                'cibles' => $alerteType->getRolesCibles(),
                'societesCibles' => $alerteType->getSocietesCibles(),
                'dateExpiration' => null,
                'createdAt' => null,
                'isExpired' => false,
                'isManual' => false,
                'isAutomatic' => true,
                'resolved' => false,
                'instancesCount' => (int)$instancesCount,
                'detectorClass' => $alerteType->getClasseDetection()
            ];
        }

        // Trier toutes les alertes (manuelles + automatiques) par ordre
        usort($alertesData, function($a, $b) {
            $ordreA = (int)($a['ordre'] ?? 999);
            $ordreB = (int)($b['ordre'] ?? 999);
            return $ordreA <=> $ordreB;
        });

        // Log pour déboguer l'ordre
        $this->logger->info('Alertes triées par ordre', [
            'alertes' => array_map(fn($a) => [
                'titre' => $a['titre'],
                'ordre' => $a['ordre'],
                'isManual' => $a['isManual']
            ], $alertesData)
        ]);

        return new JsonResponse(['alertes' => $alertesData]);
    }

    /**
     * Récupère les instances non résolues d'un type d'alerte automatique
     */
    public function getInstancesByDetector(string $detectorClass): JsonResponse
    {
        $this->logger->info("Récupération des instances pour le détecteur: {$detectorClass}");

        // Récupérer les instances non résolues
        $instances = $this->entityManager->getRepository(Alerte::class)
            ->createQueryBuilder('a')
            ->where('a.detectorClass = :detector')
            ->andWhere('a.resolved = false')
            ->setParameter('detector', $detectorClass)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $instancesData = [];
        foreach ($instances as $instance) {
            $metadata = $instance->getMetadata();

            $instancesData[] = [
                'id' => $instance->getId(),
                'titre' => $instance->getTitre(),
                'message' => $instance->getMessage(),
                'type' => $instance->getType(),
                'entityType' => $instance->getEntityType(),
                'entityId' => $instance->getEntityId(),
                'metadata' => $metadata,
                'createdAt' => $instance->getCreatedAt()->format('d/m/Y à H:i'),
                'resolved' => $instance->isResolved()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'instances' => $instancesData,
            'total' => count($instancesData)
        ]);
    }

    /**
     * Résout une instance d'alerte (admin)
     */
    public function resolveInstance(int $instanceId, \App\Entity\User $admin): JsonResponse
    {
        $this->logger->info("Résolution d'une instance par admin", [
            'instance_id' => $instanceId,
            'admin_id' => $admin->getId()
        ]);

        $instance = $this->entityManager->getRepository(Alerte::class)->find($instanceId);

        if (!$instance) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Instance d\'alerte introuvable'
            ], 404);
        }

        if ($instance->isResolved()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cette alerte est déjà résolue'
            ]);
        }

        // Marquer comme résolu
        $instance->setResolved(true);
        $instance->setDateResolution(new \DateTimeImmutable());
        $instance->setResolvedBy($admin);
        $instance->setCommentaire('Résolu par administrateur depuis l\'interface admin');

        $this->entityManager->flush();

        $this->logger->info("Instance résolue avec succès", [
            'instance_id' => $instanceId
        ]);

        return new JsonResponse([
            'success' => true,
            'message' => 'Instance résolue avec succès'
        ]);
    }

    /**
     * Crée une alerte depuis une requête HTTP
     */
    public function createAlerteFromRequest(Request $request): JsonResponse
    {
        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }

            // Récupérer et décoder le JSON
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'JSON invalide : ' . json_last_error_msg()
                ], 400);
            }

            // Créer le DTO manuellement
            $dto = new AlerteCreateDto();
            $dto->titre = $data['titre'] ?? '';
            $dto->message = $data['message'] ?? '';
            $dto->type = $data['type'] ?? '';
            $dto->cibles = $data['cibles'] ?? [];
            $dto->ordre = $data['ordre'] ?? 0;
            $dto->isActive = $data['isActive'] ?? true;
            $dto->dismissible = $data['dismissible'] ?? true;
            $dto->dateExpiration = $data['dateExpiration'] ?? null;

            // Valider le DTO
            $errors = $this->validator->validate($dto);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'errors' => $errorMessages], 400);
            }

            $this->logger->info("Création d'une nouvelle alerte: {$dto->titre}");

            // Utiliser le service pour créer l'alerte
            $alerteData = [
                'titre' => $dto->titre,
                'message' => $dto->message,
                'type_alerte' => $dto->type,
                'active' => $dto->isActive,
                'dismissible' => $dto->dismissible,
                'ordre' => $dto->ordre,
                'cibles_roles' => $dto->cibles,
                'date_expiration' => $dto->dateExpiration
            ];

            $alerte = $this->alerteService->createAlerte($alerteData);
            $this->logger->info("Alerte {$alerte->getTitre()} créée avec succès");

            return new JsonResponse(['success' => true, 'message' => 'Alerte créée avec succès']);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la création de l'alerte: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function getAlerte(Alerte $alerte): JsonResponse
    {
        $this->logger->info("Récupération de l'alerte ID: {$alerte->getId()}");

        return new JsonResponse([
            'id' => $alerte->getId(),
            'titre' => $alerte->getTitre(),
            'message' => $alerte->getMessage(),
            'type' => $alerte->getType(),
            'isActive' => $alerte->isActive(),
            'dismissible' => $alerte->isDismissible(),
            'cibles' => $alerte->getCibles() ?? [],
            'dateExpiration' => $alerte->getDateExpiration() ? $alerte->getDateExpiration()->format('Y-m-d\TH:i') : null
        ]);
    }

    public function updateAlerte(Alerte $alerte, AlerteUpdateDto $dto, Request $request): JsonResponse
    {
        $this->logger->info("Mise à jour de l'alerte ID: {$alerte->getId()}");
        
        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }
            
            $errors = $this->validator->validate($dto);
            
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'errors' => $errorMessages], 400);
            }
            
            // Utiliser le service pour mettre à jour l'alerte
            $data = [
                'titre' => $dto->titre,
                'message' => $dto->message,
                'type_alerte' => $dto->type,
                'active' => $dto->isActive,
                'dismissible' => $dto->dismissible,
                'ordre' => $dto->ordre,
                'cibles_roles' => $dto->cibles,
                'date_expiration' => $dto->dateExpiration
            ];
            
            $this->alerteService->updateAlerte($alerte, $data);
            $this->logger->info("Alerte {$alerte->getTitre()} mise à jour avec succès");

            return new JsonResponse(['success' => true, 'message' => 'Alerte mise à jour avec succès']);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour de l'alerte {$alerte->getId()}: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    public function deleteAlerte(Alerte $alerte, Request $request): JsonResponse
    {
        $this->logger->info("Suppression de l'alerte ID: {$alerte->getId()}");

        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }

            $alerteTitre = $alerte->getTitre();
            $success = $this->alerteService->deleteAlerte($alerte);

            if (!$success) {
                $this->logger->warning("Impossible de supprimer l'alerte {$alerteTitre}");
                return new JsonResponse(['success' => false, 'message' => 'Impossible de supprimer l\'alerte']);
            }

            $this->logger->info("Alerte {$alerteTitre} supprimée avec succès");
            return new JsonResponse(['success' => true, 'message' => 'Alerte supprimée avec succès']);
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression de l'alerte {$alerte->getId()}: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
    }

    /**
     * Lance la détection de toutes les alertes automatiques
     */
    public function runDetection(): JsonResponse
    {
        $this->logger->info("Lancement manuel de la détection des alertes depuis l'admin");

        try {
            $results = $this->alerteManager->runDetection();

            $totalInstances = 0;
            $detailsParType = [];

            foreach ($results as $typeId => $count) {
                if (is_numeric($count)) {
                    $totalInstances += $count;
                    $detailsParType[] = [
                        'type_id' => $typeId,
                        'count' => $count
                    ];
                }
            }

            // Récupérer les statistiques
            $stats = $this->alerteManager->getStatistics();

            $this->logger->info("Détection terminée avec succès", [
                'total_instances' => $totalInstances,
                'types_traites' => count($detailsParType)
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('%d instance(s) d\'alerte créée(s)', $totalInstances),
                'total_instances' => $totalInstances,
                'details' => $detailsParType,
                'statistics' => [
                    'types_actifs' => $stats['types_actifs'],
                    'alertes_non_resolues' => $stats['alertes_non_resolues']
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la détection des alertes: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la détection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère un type d'alerte automatique par son ID
     */
    public function getAlerteType(int $id): JsonResponse
    {
        $this->logger->info("Récupération du type d'alerte ID: {$id}");

        $alerteType = $this->entityManager->getRepository(\App\Entity\AlerteType::class)->find($id);

        if (!$alerteType) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Type d\'alerte introuvable'
            ], 404);
        }

        return new JsonResponse([
            'id' => $alerteType->getId(),
            'nom' => $alerteType->getNom(),
            'description' => $alerteType->getDescription(),
            'severity' => $alerteType->getSeverity(),
            'actif' => $alerteType->isActif(),
            'rolesCibles' => $alerteType->getRolesCibles() ?? [],
            'societesCibles' => $alerteType->getSocietesCibles() ?? [],
            'classeDetection' => $alerteType->getClasseDetection(),
            'configuration' => $alerteType->getConfiguration()
        ]);
    }

    /**
     * Met à jour un type d'alerte automatique
     */
    public function updateAlerteType(int $id, Request $request): JsonResponse
    {
        $this->logger->info("Mise à jour du type d'alerte ID: {$id}");

        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }

            $alerteType = $this->entityManager->getRepository(\App\Entity\AlerteType::class)->find($id);

            if (!$alerteType) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Type d\'alerte introuvable'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Mise à jour des champs
            if (isset($data['nom'])) {
                $alerteType->setNom($data['nom']);
            }
            if (isset($data['description'])) {
                $alerteType->setDescription($data['description']);
            }
            if (isset($data['severity'])) {
                $alerteType->setSeverity($data['severity']);
            }
            if (isset($data['actif'])) {
                $alerteType->setActif($data['actif']);
            }
            if (isset($data['rolesCibles'])) {
                $alerteType->setRolesCibles($data['rolesCibles']);
            }
            if (isset($data['societesCibles'])) {
                $alerteType->setSocietesCibles($data['societesCibles']);
            }

            $alerteType->setDateModification(new \DateTimeImmutable());

            $this->entityManager->flush();

            $this->logger->info("Type d'alerte {$alerteType->getNom()} mis à jour avec succès");

            return new JsonResponse([
                'success' => true,
                'message' => 'Type d\'alerte mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour du type d'alerte {$id}: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée un nouveau type d'alerte configurable
     */
    public function createAlerteType(Request $request): JsonResponse
    {
        $this->logger->info("Création d'un nouveau type d'alerte");

        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }

            $data = json_decode($request->getContent(), true);

            // Validation des champs obligatoires
            if (empty($data['nom'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le nom est obligatoire'
                ], 400);
            }

            if (empty($data['classeDetection'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'La classe de détection est obligatoire'
                ], 400);
            }

            // Créer le nouveau type d'alerte
            $alerteType = new \App\Entity\AlerteType();
            $alerteType->setNom($data['nom']);
            $alerteType->setDescription($data['description'] ?? '');
            $alerteType->setSeverity($data['severity'] ?? 'warning');
            $alerteType->setClasseDetection($data['classeDetection']);
            $alerteType->setActif($data['actif'] ?? true);
            $alerteType->setRolesCibles($data['rolesCibles'] ?? []);
            $alerteType->setSocietesCibles($data['societesCibles'] ?? []);
            $alerteType->setConfiguration($data['configuration'] ?? null);
            $alerteType->setOrdre($this->getNextOrdre());

            $this->entityManager->persist($alerteType);
            $this->entityManager->flush();

            $this->logger->info("Type d'alerte '{$alerteType->getNom()}' créé avec succès");

            return new JsonResponse([
                'success' => true,
                'message' => 'Type d\'alerte créé avec succès',
                'id' => $alerteType->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la création du type d'alerte: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère le prochain ordre disponible pour une alerte
     */
    private function getNextOrdre(): int
    {
        $maxOrdreManual = $this->entityManager->createQueryBuilder()
            ->select('MAX(a.ordre)')
            ->from(Alerte::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        $maxOrdreType = $this->entityManager->createQueryBuilder()
            ->select('MAX(at.ordre)')
            ->from(\App\Entity\AlerteType::class, 'at')
            ->getQuery()
            ->getSingleScalarResult();

        return max((int)$maxOrdreManual, (int)$maxOrdreType) + 1;
    }

    /**
     * Supprime un type d'alerte automatique
     */
    public function deleteAlerteType(int $id, Request $request): JsonResponse
    {
        $this->logger->info("Suppression du type d'alerte ID: {$id}");

        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }

            $alerteType = $this->entityManager->getRepository(\App\Entity\AlerteType::class)->find($id);

            if (!$alerteType) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Type d\'alerte introuvable'
                ], 404);
            }

            // Supprimer toutes les instances liées (alertes automatiques créées par ce type)
            $instances = $this->entityManager->getRepository(Alerte::class)
                ->findBy(['detectorClass' => $alerteType->getClasseDetection()]);

            foreach ($instances as $instance) {
                $this->entityManager->remove($instance);
            }

            // Supprimer le type d'alerte
            $nom = $alerteType->getNom();
            $this->entityManager->remove($alerteType);
            $this->entityManager->flush();

            $this->logger->info("Type d'alerte '{$nom}' supprimé avec succès");

            return new JsonResponse([
                'success' => true,
                'message' => 'Type d\'alerte supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression du type d'alerte {$id}: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour l'ordre des alertes (manuelles et automatiques)
     */
    public function updateOrdre(Request $request): JsonResponse
    {
        try {
            // Validation CSRF
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('ajax', $csrfToken))) {
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
            }

            $data = json_decode($request->getContent(), true);
            if (!isset($data['ordre']) || !is_array($data['ordre'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données d\'ordre invalides'
                ], 400);
            }

            foreach ($data['ordre'] as $item) {
                $id = $item['id'];
                $ordre = $item['ordre'];

                // Vérifier si c'est une alerte manuelle ou un type d'alerte automatique
                if (str_starts_with($id, 'type_')) {
                    // Type d'alerte automatique
                    $typeId = (int) str_replace('type_', '', $id);
                    $alerteType = $this->entityManager->getRepository(\App\Entity\AlerteType::class)->find($typeId);
                    if ($alerteType) {
                        $alerteType->setOrdre($ordre);
                    }
                } else {
                    // Alerte manuelle
                    $alerte = $this->entityManager->getRepository(Alerte::class)->find($id);
                    if ($alerte) {
                        $alerte->setOrdre($ordre);
                    }
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Ordre des alertes mis à jour', [
                'count' => count($data['ordre'])
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Ordre sauvegardé avec succès'
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour de l'ordre des alertes: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les champs et relations d'une entité pour le constructeur de conditions
     */
    public function getEntityFields(string $entityClass): JsonResponse
    {
        try {
            // Vérifier que la classe existe
            if (!class_exists($entityClass)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Classe d\'entité introuvable'
                ], 404);
            }

            // Récupérer les métadonnées de l'entité
            $metadata = $this->entityManager->getClassMetadata($entityClass);

            $fields = [];
            $relations = [];

            // Récupérer les champs simples
            foreach ($metadata->getFieldNames() as $fieldName) {
                // Ignorer l'ID
                if ($fieldName === 'id') {
                    continue;
                }

                $fieldMapping = $metadata->getFieldMapping($fieldName);
                $type = $fieldMapping['type'] ?? 'string';

                $fields[] = [
                    'name' => $fieldName,
                    'type' => $type,
                    'label' => ucfirst($fieldName)
                ];
            }

            // Récupérer les relations
            foreach ($metadata->getAssociationNames() as $associationName) {
                $mapping = $metadata->getAssociationMapping($associationName);
                $relationType = 'unknown';

                if ($metadata->isCollectionValuedAssociation($associationName)) {
                    $relationType = 'collection'; // OneToMany, ManyToMany
                } else {
                    $relationType = 'single'; // ManyToOne, OneToOne
                }

                $relations[] = [
                    'name' => $associationName,
                    'type' => $relationType,
                    'targetEntity' => $mapping['targetEntity'],
                    'label' => ucfirst($associationName)
                ];
            }

            return new JsonResponse([
                'success' => true,
                'fields' => $fields,
                'relations' => $relations
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des champs de l'entité {$entityClass}: {$e->getMessage()}");
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}