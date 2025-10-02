<?php

namespace App\Controller\Admin;

use App\Entity\AlerteType;
use App\Entity\Societe;
use App\Service\AlerteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AlerteTypeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlerteManager $alerteManager
    ) {}

    #[Route('/types-alerte-test', name: 'app_admin_types_alerte_test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        error_log('🔍 [DEBUG] Test endpoint called');
        return $this->json(['success' => true, 'message' => 'Test endpoint works']);
    }

    #[Route('/types-alerte', name: 'app_admin_types_alerte', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Si c'est une requête AJAX, retourner seulement les données
        if ($request->isXmlHttpRequest()) {
            try {
                $typesAlerte = $this->entityManager
                    ->getRepository(AlerteType::class)
                    ->findBy([], ['ordre' => 'ASC', 'nom' => 'ASC']);

                $detecteurs = $this->alerteManager->getDetectors();

                $typesData = [];
                foreach ($typesAlerte as $type) {
                    $detector = $detecteurs[$type->getClasseDetection()] ?? null;

                    $typesData[] = [
                        'id' => $type->getId(),
                        'nom' => $type->getNom(),
                        'description' => $type->getDescription(),
                        'classe_detection' => $type->getClasseDetection(),
                        'actif' => $type->isActif(),
                        'ordre' => $type->getOrdre(),
                        'severity' => $type->getSeverity(),
                        'roles_cibles' => $type->getRolesCibles() ?? [],
                        'societes_cibles' => $type->getSocietesCibles() ?? [],
                        'detecteur_nom' => $detector ? $detector['name'] : 'Détecteur inconnu',
                        'instances_count' => $type->getInstances()->count()
                    ];
                }

                return $this->json(['types_alerte' => $typesData]);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Exception: ' . $e->getMessage()], 500);
            }
        }

        try {
            $typesAlerte = $this->entityManager
                ->getRepository(AlerteType::class)
                ->findBy([], ['ordre' => 'ASC', 'nom' => 'ASC']);

            $societes = $this->entityManager
                ->getRepository(Societe::class)
                ->findBy(['active' => true], ['nom' => 'ASC']);

            $detecteurs = $this->alerteManager->getDetectors();

            return $this->render('admin/alertes/types.html.twig', [
                'types_alerte' => $typesAlerte,
                'societes' => $societes,
                'detecteurs' => $detecteurs
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    #[Route('/types-alerte/create', name: 'app_admin_types_alerte_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['nom']) || !isset($data['classeDetection'])) {
                return $this->json(['error' => 'Nom et classe de détection obligatoires'], 400);
            }

            $existingType = $this->entityManager->getRepository(AlerteType::class)
                ->findOneBy(['nom' => $data['nom']]);

            if ($existingType) {
                return $this->json(['error' => 'Ce nom de type d\'alerte existe déjà'], 400);
            }

            $detector = $this->alerteManager->getDetector($data['classeDetection']);
            if (!$detector) {
                return $this->json(['error' => 'Détecteur non trouvé'], 400);
            }

            $type = new AlerteType();
            $type->setNom($data['nom'])
                ->setDescription($data['description'] ?? '')
                ->setClasseDetection($data['classeDetection'])
                ->setRolesCibles($data['rolesCibles'] ?? [])
                ->setSocietesCibles($data['societesCibles'] ?? [])
                ->setActif($data['actif'] ?? true)
                ->setOrdre($data['ordre'] ?? 0)
                ->setSeverity($data['severity'] ?? 'warning');

            $this->entityManager->persist($type);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Type d\'alerte créé avec succès',
                'type' => [
                    'id' => $type->getId(),
                    'nom' => $type->getNom(),
                    'actif' => $type->isActif(),
                    'instances_count' => 0
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-alerte/{id}/update', name: 'app_admin_types_alerte_update', methods: ['PUT'])]
    public function update(Request $request, AlerteType $type): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['nom'])) {
                $existingType = $this->entityManager->getRepository(AlerteType::class)
                    ->createQueryBuilder('at')
                    ->where('at.nom = :nom')
                    ->andWhere('at.id != :id')
                    ->setParameter('nom', $data['nom'])
                    ->setParameter('id', $type->getId())
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existingType) {
                    return $this->json(['error' => 'Ce nom de type d\'alerte existe déjà'], 400);
                }

                $type->setNom($data['nom']);
            }

            if (isset($data['description'])) {
                $type->setDescription($data['description']);
            }

            if (isset($data['classeDetection'])) {
                $detector = $this->alerteManager->getDetector($data['classeDetection']);
                if (!$detector) {
                    return $this->json(['error' => 'Détecteur non trouvé'], 400);
                }
                $type->setClasseDetection($data['classeDetection']);
            }

            if (isset($data['rolesCibles'])) {
                $type->setRolesCibles($data['rolesCibles']);
            }

            if (isset($data['societesCibles'])) {
                $type->setSocietesCibles($data['societesCibles']);
            }

            if (isset($data['actif'])) {
                $type->setActif($data['actif']);
            }

            if (isset($data['ordre'])) {
                $type->setOrdre($data['ordre']);
            }

            if (isset($data['severity'])) {
                $type->setSeverity($data['severity']);
            }

            $type->setDateModification(new \DateTimeImmutable());
            $this->entityManager->flush();

            $instancesCount = 0;
            try {
                $instancesCount = $type->getActiveInstancesCount();
            } catch (\Exception $e) {
                error_log('❌ [ERROR] Failed to get active instances count in update: ' . $e->getMessage());
            }

            return $this->json([
                'success' => true,
                'message' => 'Type d\'alerte mis à jour avec succès',
                'type' => [
                    'id' => $type->getId(),
                    'nom' => $type->getNom(),
                    'actif' => $type->isActif(),
                    'instances_count' => $instancesCount
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-alerte/{id}/delete', name: 'app_admin_types_alerte_delete', methods: ['DELETE'])]
    public function delete(AlerteType $type): JsonResponse
    {
        try {
            $instancesCount = $type->getInstancesCount();

            if ($instancesCount > 0) {
                return $this->json([
                    'error' => 'Ce type d\'alerte ne peut pas être supprimé car il a ' . $instancesCount . ' instance(s) associée(s)'
                ], 400);
            }

            $this->entityManager->remove($type);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Type d\'alerte supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-alerte/{id}/detect', name: 'app_admin_types_alerte_detect', methods: ['POST'])]
    public function detect(AlerteType $type): JsonResponse
    {
        try {
            $results = $this->alerteManager->runDetection($type);
            $instancesCreated = $results[$type->getId()] ?? 0;

            // Compter le total d'instances non résolues pour ce type
            $totalInstances = $this->entityManager
                ->getRepository(\App\Entity\AlerteInstance::class)
                ->count(['alerteType' => $type, 'resolved' => false]);

            return $this->json([
                'success' => true,
                'message' => sprintf('%d nouvelle(s) instance(s) créée(s). Total non résolu: %d', $instancesCreated, $totalInstances),
                'instances_created' => $instancesCreated,
                'total_instances' => $totalInstances
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la détection: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-alerte/reorder', name: 'app_admin_types_alerte_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['types']) || !is_array($data['types'])) {
                return $this->json(['error' => 'Format de données invalide'], 400);
            }

            foreach ($data['types'] as $item) {
                if (isset($item['id']) && isset($item['ordre'])) {
                    $type = $this->entityManager->find(AlerteType::class, $item['id']);
                    if ($type) {
                        $type->setOrdre($item['ordre']);
                    }
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre des types d\'alerte mis à jour'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la réorganisation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-alerte/{id}/instances', name: 'app_admin_types_alerte_instances', methods: ['GET'])]
    public function getInstances(AlerteType $type): JsonResponse
    {
        try {
            $instances = $this->entityManager
                ->getRepository(\App\Entity\AlerteInstance::class)
                ->findBy(
                    ['alerteType' => $type, 'resolved' => false],
                    ['dateDetection' => 'DESC']
                );

            $data = [];
            foreach ($instances as $instance) {
                // Générer le message depuis les metadata et entity_type
                $metadata = $instance->getMetadata();
                $entityType = $instance->getEntityType();
                $message = '';

                // Déterminer le message selon le type d'entité
                if ($entityType === 'App\Entity\Client' || isset($metadata['client_code'])) {
                    $clientNom = $metadata['client_nom'] ?? 'Client inconnu';
                    $message = 'Client "' . $clientNom . '" n\'a aucun contact actif';
                } elseif ($entityType === 'App\Entity\Contact' || isset($metadata['contact_nom'])) {
                    $contactName = $metadata['contact_prenom'] ? $metadata['contact_prenom'] . ' ' . $metadata['contact_nom'] : $metadata['contact_nom'];
                    $clientNom = $metadata['client_nom'] ?? '';
                    $message = 'Contact "' . $contactName . '"' . ($clientNom ? ' (Client: ' . $clientNom . ')' : '') . ' n\'a pas d\'adresse';
                } else {
                    $message = 'Alerte détectée';
                }

                $data[] = [
                    'id' => $instance->getId(),
                    'entityType' => $instance->getEntityType(),
                    'entityId' => $instance->getEntityId(),
                    'message' => $message,
                    'metadata' => $metadata,
                    'dateCreation' => $instance->getDateDetection()->format('d/m/Y H:i'),
                    'clientId' => $metadata['client_id'] ?? null,
                ];
            }

            return $this->json([
                'success' => true,
                'instances' => $data,
                'typeName' => $type->getNom()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/types-alerte/run-all', name: 'app_admin_types_alerte_run_all', methods: ['POST'])]
    public function runAllDetections(): JsonResponse
    {
        try {
            $results = $this->alerteManager->runDetection();
            $totalCreated = array_sum(array_filter($results, 'is_numeric'));

            // Compter le total d'instances non résolues par type
            $totals = [];
            foreach ($results as $typeId => $created) {
                if (is_numeric($created)) {
                    $type = $this->entityManager->find(\App\Entity\AlerteType::class, $typeId);
                    if ($type) {
                        $totals[$typeId] = $this->entityManager
                            ->getRepository(\App\Entity\AlerteInstance::class)
                            ->count(['alerteType' => $type, 'resolved' => false]);
                    }
                }
            }

            return $this->json([
                'success' => true,
                'message' => sprintf('%d nouvelle(s) instance(s) créée(s) au total', $totalCreated),
                'results' => $results,
                'totals' => $totals
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors des détections: ' . $e->getMessage()], 500);
        }
    }
}