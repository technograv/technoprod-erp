<?php

namespace App\Repository;

use App\Entity\CommuneFrancaise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommuneFrancaise>
 */
class CommuneFrancaiseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommuneFrancaise::class);
    }

    /**
     * Recherche de communes par code postal
     */
    public function findByCodePostal(string $codePostal): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codePostal = :codePostal')
            ->setParameter('codePostal', $codePostal)
            ->orderBy('c.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de communes par nom (autocomplétion)
     */
    public function findByNomCommune(string $nom, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.nomCommune) LIKE LOWER(:nom)')
            ->setParameter('nom', '%' . $nom . '%')
            ->orderBy('c.nomCommune', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche globale pour autocomplétion (nom de ville ou code postal)
     */
    public function searchForAutocomplete(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.nomCommune) LIKE LOWER(:query) OR c.codePostal LIKE :codePostal')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('codePostal', $query . '%')
            ->orderBy('c.codePostal', 'ASC')
            ->addOrderBy('c.nomCommune', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir toutes les communes d'un département
     */
    public function findByDepartement(string $codeDepartement): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codeDepartement = :codeDepartement')
            ->setParameter('codeDepartement', $codeDepartement)
            ->orderBy('c.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les communes dans une zone géographique (pour les secteurs commerciaux)
     */
    public function findInRadius(float $latitude, float $longitude, float $radiusKm): array
    {
        // Note: Pour PostgreSQL, nous utiliserons une approximation simple
        // Pour une précision maximale, il faudrait utiliser PostGIS
        $latRange = $radiusKm / 111; // Approximation: 1 degré ≈ 111 km
        $lngRange = $radiusKm / (111 * cos(deg2rad($latitude)));
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.latitude IS NOT NULL AND c.longitude IS NOT NULL')
            ->andWhere('c.latitude BETWEEN :latMin AND :latMax')
            ->andWhere('c.longitude BETWEEN :lngMin AND :lngMax')
            ->setParameter('latMin', $latitude - $latRange)
            ->setParameter('latMax', $latitude + $latRange)
            ->setParameter('lngMin', $longitude - $lngRange)
            ->setParameter('lngMax', $longitude + $lngRange)
            ->orderBy('c.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les statistiques par département
     */
    public function getStatsParDepartement(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.codeDepartement, c.nomDepartement, COUNT(c.id) as nbCommunes')
            ->groupBy('c.codeDepartement, c.nomDepartement')
            ->orderBy('c.codeDepartement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche exacte par code postal et nom de commune
     */
    public function findExact(string $codePostal, string $nomCommune): ?CommuneFrancaise
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codePostal = :codePostal')
            ->andWhere('LOWER(c.nomCommune) = LOWER(:nomCommune)')
            ->setParameter('codePostal', $codePostal)
            ->setParameter('nomCommune', $nomCommune)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtenir tous les départements distincts
     */
    public function getAllDepartements(): array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT c.codeDepartement, c.nomDepartement')
            ->where('c.codeDepartement IS NOT NULL')
            ->orderBy('c.codeDepartement', 'ASC')
            ->getQuery()
            ->getResult();
    }
}