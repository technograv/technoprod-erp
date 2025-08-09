<?php

namespace App\Repository;

use App\Entity\DocumentTemplate;
use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentTemplate>
 */
class DocumentTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentTemplate::class);
    }

    /**
     * Récupère tous les templates ordonnés par type et ordre
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('dt')
            ->orderBy('dt.typeDocument', 'ASC')
            ->addOrderBy('dt.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les templates pour une société avec héritage
     */
    public function findBySocieteWithInheritance(?Societe $societe = null): array
    {
        $qb = $this->createQueryBuilder('dt');
        
        if ($societe) {
            $qb->where('dt.societe = :societe OR dt.societe IS NULL')
               ->setParameter('societe', $societe);
        } else {
            $qb->where('dt.societe IS NULL');
        }
        
        return $qb->andWhere('dt.estActif = true')
            ->orderBy('dt.typeDocument', 'ASC')
            ->addOrderBy('dt.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le template par défaut pour un type de document et une société
     */
    public function findDefaultByTypeAndSociete(string $typeDocument, ?Societe $societe = null): ?DocumentTemplate
    {
        $qb = $this->createQueryBuilder('dt')
            ->where('dt.typeDocument = :type')
            ->andWhere('dt.estDefaut = true')
            ->andWhere('dt.estActif = true')
            ->setParameter('type', $typeDocument)
            ->setMaxResults(1);
        
        if ($societe) {
            // Priorité au template de la société, puis template global
            $qb->andWhere('dt.societe = :societe OR dt.societe IS NULL')
               ->setParameter('societe', $societe)
               ->orderBy('CASE WHEN dt.societe = :societe THEN 0 ELSE 1 END', 'ASC');
        } else {
            $qb->andWhere('dt.societe IS NULL');
        }
        
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Récupère tous les templates pour un type de document avec héritage
     */
    public function findByTypeWithInheritance(string $typeDocument, ?Societe $societe = null): array
    {
        $qb = $this->createQueryBuilder('dt')
            ->where('dt.typeDocument = :type')
            ->andWhere('dt.estActif = true')
            ->setParameter('type', $typeDocument);
        
        if ($societe) {
            $qb->andWhere('dt.societe = :societe OR dt.societe IS NULL')
               ->setParameter('societe', $societe)
               ->orderBy('CASE WHEN dt.societe = :societe THEN 0 ELSE 1 END', 'ASC');
        } else {
            $qb->andWhere('dt.societe IS NULL');
        }
        
        return $qb->addOrderBy('dt.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Définit un template comme défaut (et retire le défaut des autres du même type/société)
     */
    public function setAsDefault(DocumentTemplate $template): void
    {
        // Retirer le défaut des autres templates du même type et société
        $qb = $this->createQueryBuilder('dt')
            ->update()
            ->set('dt.estDefaut', 'false')
            ->where('dt.typeDocument = :type')
            ->setParameter('type', $template->getTypeDocument());
        
        if ($template->getSociete()) {
            $qb->andWhere('dt.societe = :societe')
               ->setParameter('societe', $template->getSociete());
        } else {
            $qb->andWhere('dt.societe IS NULL');
        }
        
        $qb->getQuery()->execute();
        
        // Définir le template comme défaut
        $template->setEstDefaut(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Réorganise les ordres pour éviter les doublons
     */
    public function reorganizeOrdres(string $typeDocument, ?Societe $societe = null): void
    {
        $qb = $this->createQueryBuilder('dt')
            ->where('dt.typeDocument = :type')
            ->setParameter('type', $typeDocument);
        
        if ($societe) {
            $qb->andWhere('dt.societe = :societe')
               ->setParameter('societe', $societe);
        } else {
            $qb->andWhere('dt.societe IS NULL');
        }
        
        $templates = $qb->orderBy('dt.ordre', 'ASC')
            ->addOrderBy('dt.id', 'ASC')
            ->getQuery()
            ->getResult();
        
        $ordre = 1;
        foreach ($templates as $template) {
            $template->setOrdre($ordre++);
        }
        
        $this->getEntityManager()->flush();
    }

    /**
     * Compte les templates par type de document
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('dt')
            ->select('dt.typeDocument, COUNT(dt.id) as count')
            ->where('dt.estActif = true')
            ->groupBy('dt.typeDocument')
            ->getQuery()
            ->getResult();
        
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['typeDocument']] = (int) $row['count'];
        }
        
        return $counts;
    }

    /**
     * Supprime un template (avec vérifications)
     */
    public function safeRemove(DocumentTemplate $template): bool
    {
        // Vérifier si le template est utilisé par des documents
        // TODO: Ajouter vérifications avec les entités de documents (Devis, Facture, etc.)
        
        $this->getEntityManager()->remove($template);
        $this->getEntityManager()->flush();
        
        // Réorganiser les ordres après suppression
        $this->reorganizeOrdres($template->getTypeDocument(), $template->getSociete());
        
        return true;
    }
}
