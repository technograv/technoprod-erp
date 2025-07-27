<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Trouve tous les contacts d'un client
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.client = :client')
            ->setParameter('client', $client)
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le contact de facturation par défaut d'un client
     */
    public function findFacturationDefaultByClient(Client $client): ?Contact
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.client = :client')
            ->andWhere('c.isFacturationDefault = true')
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve le contact de livraison par défaut d'un client
     */
    public function findLivraisonDefaultByClient(Client $client): ?Contact
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.client = :client')
            ->andWhere('c.isLivraisonDefault = true')
            ->setParameter('client', $client)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche de contacts par nom/prénom
     */
    public function searchByName(string $search, Client $client = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.nom LIKE :search OR c.prenom LIKE :search')
            ->setParameter('search', '%' . $search . '%');
            
        if ($client) {
            $qb->andWhere('c.client = :client')
               ->setParameter('client', $client);
        }
        
        return $qb->orderBy('c.nom', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}