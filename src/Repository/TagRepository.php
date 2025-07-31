<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Réorganise automatiquement les ordres après modification d'un tag
     * pour éviter les doublons et maintenir une séquence continue
     */
    public function reorganizeOrdres(): void
    {
        $tags = $this->findBy(['actif' => true], ['ordre' => 'ASC']);
        
        $ordre = 1;
        foreach ($tags as $tag) {
            $tag->setOrdre($ordre);
            $ordre++;
        }
        
        $this->getEntityManager()->flush();
    }

    /**
     * Récupère tous les tags actifs ordonnés
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les tags avec assignation automatique
     */
    public function findTagsWithAutoAssignment(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->andWhere('t.assignationAutomatique = :auto')
            ->setParameter('actif', true)
            ->setParameter('auto', true)
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tags par couleur
     */
    public function findByColor(string $couleur): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.couleur = :couleur')
            ->setParameter('couleur', $couleur)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de tags par nom (pour autocomplétion)
     */
    public function findByNomLike(string $search): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->andWhere('LOWER(t.nom) LIKE LOWER(:search)')
            ->setParameter('actif', true)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('t.nom', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}