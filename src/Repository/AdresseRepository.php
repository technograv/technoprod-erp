<?php

namespace App\Repository;

use App\Entity\Adresse;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Adresse>
 */
class AdresseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adresse::class);
    }

    /**
     * Trouve toutes les adresses d'un client
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.client = :client')
            ->setParameter('client', $client)
            ->orderBy('a.ville', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'adresse de facturation par défaut d'un client
     */
    public function findFacturationDefaultByClient(Client $client): ?Adresse
    {
        $contactFacturation = $client->getContactFacturationDefault();
        if (!$contactFacturation) {
            return null;
        }
        
        // Retourne l'adresse du contact de facturation par défaut
        return $contactFacturation->getAdresse();
    }

    /**
     * Trouve l'adresse de livraison par défaut d'un client
     */
    public function findLivraisonDefaultByClient(Client $client): ?Adresse
    {
        $contactLivraison = $client->getContactLivraisonDefault();
        if (!$contactLivraison) {
            return null;
        }
        
        // Retourne l'adresse du contact de livraison par défaut
        return $contactLivraison->getAdresse();
    }

    /**
     * Recherche d'adresses par ville
     */
    public function searchByVille(string $search, Client $client = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.ville LIKE :search OR a.ligne1 LIKE :search')
            ->setParameter('search', '%' . $search . '%');
            
        if ($client) {
            $qb->andWhere('a.client = :client')
               ->setParameter('client', $client);
        }
        
        return $qb->orderBy('a.ville', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Trouve les adresses navigables (avec coordonnées complètes)
     */
    public function findNavigableByClient(Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.client = :client')
            ->andWhere('a.ligne1 IS NOT NULL')
            ->andWhere('a.ville IS NOT NULL')
            ->andWhere('a.codePostal IS NOT NULL')
            ->setParameter('client', $client)
            ->orderBy('a.ville', 'ASC')
            ->getQuery()
            ->getResult();
    }
}