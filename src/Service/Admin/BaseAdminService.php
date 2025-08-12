<?php

namespace App\Service\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;

/**
 * Service de base pour l'administration
 * Fournit les fonctionnalités communes à tous les services admin
 */
abstract class BaseAdminService implements AdminServiceInterface
{
    protected EntityManagerInterface $entityManager;
    protected Security $security;
    protected LoggerInterface $logger;
    protected EntityRepository $repository;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->logger = $logger;
        $this->repository = $this->entityManager->getRepository($this->getEntityClass());
    }

    /**
     * Retourne la classe de l'entité gérée par ce service
     */
    abstract protected function getEntityClass(): string;

    /**
     * Valide les données avant création/mise à jour
     */
    protected function validateData(array $data, ?object $entity = null): array
    {
        // Validation de base - à override dans les classes filles
        return $data;
    }

    /**
     * Log d'action admin avec contexte utilisateur
     */
    protected function logAction(string $action, string $entityType, ?int $entityId = null, array $context = []): void
    {
        $user = $this->security->getUser();
        $this->logger->info("Admin action: {$action}", [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user' => $user ? $user->getUserIdentifier() : 'anonymous',
            'context' => $context
        ]);
    }

    /**
     * Récupère tous les éléments avec tri par défaut
     */
    public function findAll(?int $page = null, ?int $limit = null): array
    {
        $queryBuilder = $this->repository->createQueryBuilder('e')
            ->orderBy('e.id', 'ASC');

        if ($page !== null && $limit !== null) {
            $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère un élément par son ID
     */
    public function findById(int $id): ?object
    {
        return $this->repository->find($id);
    }

    /**
     * Crée un nouvel élément
     */
    public function create(array $data): object
    {
        $data = $this->validateData($data);
        
        $entityClass = $this->getEntityClass();
        $entity = new $entityClass();
        
        $this->updateEntityFromData($entity, $data);
        
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        
        $this->logAction('create', $this->getEntityName(), $entity->getId(), $data);
        
        return $entity;
    }

    /**
     * Met à jour un élément existant
     */
    public function update(object $entity, array $data): object
    {
        $data = $this->validateData($data, $entity);
        
        $this->updateEntityFromData($entity, $data);
        
        $this->entityManager->flush();
        
        $this->logAction('update', $this->getEntityName(), $entity->getId(), $data);
        
        return $entity;
    }

    /**
     * Supprime un élément
     */
    public function delete(object $entity): bool
    {
        try {
            $entityId = $entity->getId();
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            
            $this->logAction('delete', $this->getEntityName(), $entityId);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete entity', [
                'entity_type' => $this->getEntityName(),
                'entity_id' => $entity->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Statistiques de base - à override dans les classes filles
     */
    public function getStatistics(): array
    {
        $total = $this->repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total
        ];
    }

    /**
     * Met à jour une entité à partir des données
     * À override dans les classes filles pour la logique spécifique
     */
    protected function updateEntityFromData(object $entity, array $data): void
    {
        // Implementation de base - à override
        foreach ($data as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($entity, $setter)) {
                $entity->$setter($value);
            }
        }
    }

    /**
     * Retourne le nom de l'entité pour les logs
     */
    protected function getEntityName(): string
    {
        $class = $this->getEntityClass();
        return substr($class, strrpos($class, '\\') + 1);
    }
}