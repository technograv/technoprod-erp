<?php

namespace App\Service\Admin;

use App\Entity\FormeJuridique;
use App\Repository\FormeJuridiqueRepository;

/**
 * Service d'administration pour les formes juridiques
 * Gère la logique métier spécifique aux formes juridiques
 */
class FormeJuridiqueAdminService extends BaseAdminService
{
    protected function getEntityClass(): string
    {
        return FormeJuridique::class;
    }

    protected function validateData(array $data, ?object $entity = null): array
    {
        // Validation spécifique aux formes juridiques
        if (empty($data['nom'])) {
            throw new \InvalidArgumentException('Le nom est requis');
        }
        
        if (empty($data['templateFormulaire'])) {
            throw new \InvalidArgumentException('Le template de formulaire est requis');
        }
        
        // Vérifier l'unicité du nom (sauf pour l'entité courante)
        $existingEntity = $this->repository->findOneBy(['nom' => $data['nom']]);
        if ($existingEntity && (!$entity || $existingEntity->getId() !== $entity->getId())) {
            throw new \InvalidArgumentException('Cette forme juridique existe déjà');
        }
        
        return $data;
    }

    protected function updateEntityFromData(object $entity, array $data): void
    {
        /** @var FormeJuridique $entity */
        if (isset($data['nom'])) {
            $entity->setNom($data['nom']);
        }
        
        if (isset($data['templateFormulaire'])) {
            $entity->setTemplateFormulaire($data['templateFormulaire']);
        }
        
        if (isset($data['ordre'])) {
            $entity->setOrdre((int) $data['ordre']);
        }
        
        if (isset($data['actif'])) {
            $entity->setActif((bool) $data['actif']);
        }
        
        // Toujours mettre à jour la date de modification
        $entity->setUpdatedAt(new \DateTimeImmutable());
    }

    public function create(array $data): object
    {
        // Gestion automatique de l'ordre si non fourni
        if (!isset($data['ordre'])) {
            $maxOrder = $this->repository->createQueryBuilder('fj')
                ->select('MAX(fj.ordre)')
                ->getQuery()
                ->getSingleScalarResult();
            
            $data['ordre'] = ($maxOrder ?? 0) + 1;
        }
        
        return parent::create($data);
    }

    /**
     * Réorganise les ordres pour éviter les doublons
     */
    public function reorganizeOrdres(): void
    {
        /** @var FormeJuridiqueRepository $repo */
        $repo = $this->repository;
        $repo->reorganizeOrdres();
        
        $this->logAction('reorganize_orders', 'FormeJuridique');
    }

    /**
     * Active/désactive une forme juridique
     */
    public function toggleActive(FormeJuridique $formeJuridique): FormeJuridique
    {
        $formeJuridique->setActif(!$formeJuridique->isActif());
        $formeJuridique->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        $this->logAction('toggle_active', 'FormeJuridique', $formeJuridique->getId(), [
            'new_status' => $formeJuridique->isActif() ? 'active' : 'inactive'
        ]);
        
        return $formeJuridique;
    }

    public function getStatistics(): array
    {
        $baseStats = parent::getStatistics();
        
        $activeCount = $this->repository->createQueryBuilder('fj')
            ->select('COUNT(fj.id)')
            ->where('fj.actif = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
        
        return array_merge($baseStats, [
            'active' => $activeCount,
            'inactive' => $baseStats['total'] - $activeCount
        ]);
    }

    /**
     * Récupère les formes juridiques ordonnées pour les dropdowns
     */
    public function findAllOrdered(): array
    {
        return $this->repository->findBy(['actif' => true], ['ordre' => 'ASC']);
    }
}