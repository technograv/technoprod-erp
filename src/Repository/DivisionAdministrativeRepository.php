<?php

namespace App\Repository;

use App\Entity\DivisionAdministrative;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DivisionAdministrative>
 */
class DivisionAdministrativeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DivisionAdministrative::class);
    }

    /**
     * Recherche de divisions administratives par critères multiples
     */
    public function search(?string $terme, ?string $typeDivision = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.actif = :actif')
            ->setParameter('actif', true)
            ->setMaxResults($limit);

        if ($terme) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('d.codePostal', ':terme'),
                    $qb->expr()->like('LOWER(d.nomCommune)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomCanton)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomEpci)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomDepartement)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomRegion)', 'LOWER(:terme)')
                )
            )
            ->setParameter('terme', '%' . $terme . '%');
        }

        // Filtrage par type de division
        switch ($typeDivision) {
            case 'code_postal':
                $qb->orderBy('d.codePostal', 'ASC')
                   ->addOrderBy('d.nomCommune', 'ASC');
                break;
            case 'commune':
                $qb->orderBy('d.nomCommune', 'ASC')
                   ->addOrderBy('d.codePostal', 'ASC');
                break;
            case 'canton':
                $qb->orderBy('d.nomCanton', 'ASC')
                   ->addOrderBy('d.nomCommune', 'ASC');
                break;
            case 'epci':
                $qb->orderBy('d.nomEpci', 'ASC')
                   ->addOrderBy('d.nomCommune', 'ASC');
                break;
            case 'departement':
                $qb->orderBy('d.nomDepartement', 'ASC')
                   ->addOrderBy('d.nomCommune', 'ASC');
                break;
            case 'region':
                $qb->orderBy('d.nomRegion', 'ASC')
                   ->addOrderBy('d.nomDepartement', 'ASC');
                break;
            default:
                $qb->orderBy('d.nomCommune', 'ASC')
                   ->addOrderBy('d.codePostal', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les divisions d'un département donné
     */
    public function findByDepartement(string $codeDepartement): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.codeDepartement = :code')
            ->andWhere('d.actif = :actif')
            ->setParameter('code', $codeDepartement)
            ->setParameter('actif', true)
            ->orderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les divisions d'une région donnée
     */
    public function findByRegion(string $codeRegion): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.codeRegion = :code')
            ->andWhere('d.actif = :actif')
            ->setParameter('code', $codeRegion)
            ->setParameter('actif', true)
            ->orderBy('d.nomDepartement', 'ASC')
            ->addOrderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les divisions d'un EPCI donné
     */
    public function findByEpci(string $codeEpci): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.codeEpci = :code')
            ->andWhere('d.actif = :actif')
            ->setParameter('code', $codeEpci)
            ->setParameter('actif', true)
            ->orderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les divisions d'un canton donné
     */
    public function findByCanton(string $codeCanton): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.codeCanton = :code')
            ->andWhere('d.actif = :actif')
            ->setParameter('code', $codeCanton)
            ->setParameter('actif', true)
            ->orderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par code postal exact
     */
    public function findByCodePostal(string $codePostal): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.codePostal = :code')
            ->andWhere('d.actif = :actif')
            ->setParameter('code', $codePostal)
            ->setParameter('actif', true)
            ->orderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par commune exact (code INSEE)
     */
    public function findByCommune(string $codeInseeCommune): ?DivisionAdministrative
    {
        return $this->createQueryBuilder('d')
            ->where('d.codeInseeCommune = :code')
            ->andWhere('d.actif = :actif')
            ->setParameter('code', $codeInseeCommune)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtient toutes les valeurs distinctes pour un type de division
     */
    public function getValeursDistinctes(string $typeDivision): array
    {
        $champCode = match($typeDivision) {
            'code_postal' => 'd.codePostal',
            'commune' => 'd.codeInseeCommune',
            'canton' => 'd.codeCanton',
            'epci' => 'd.codeEpci',
            'departement' => 'd.codeDepartement',
            'region' => 'd.codeRegion',
            default => 'd.codePostal'
        };

        $champNom = match($typeDivision) {
            'code_postal' => 'd.codePostal',
            'commune' => 'd.nomCommune',
            'canton' => 'd.nomCanton',
            'epci' => 'd.nomEpci',
            'departement' => 'd.nomDepartement',
            'region' => 'd.nomRegion',
            default => 'd.nomCommune'
        };

        $qb = $this->createQueryBuilder('d')
            ->select("DISTINCT {$champCode} as code, {$champNom} as nom")
            ->where('d.actif = :actif')
            ->andWhere("{$champCode} IS NOT NULL")
            ->setParameter('actif', true)
            ->orderBy('nom', 'ASC');

        // Pour les cantons, EPCI, départements, on évite les doublons en groupant
        if (in_array($typeDivision, ['canton', 'epci', 'departement', 'region'])) {
            $qb->groupBy($champCode, $champNom);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient des statistiques de couverture géographique
     */
    public function getStatistiquesCouverture(): array
    {
        return [
            'total_divisions' => $this->count(['actif' => true]),
            'codes_postaux' => $this->createQueryBuilder('d')
                ->select('COUNT(DISTINCT d.codePostal)')
                ->where('d.actif = :actif')
                ->setParameter('actif', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'communes' => $this->createQueryBuilder('d')
                ->select('COUNT(DISTINCT d.codeInseeCommune)')
                ->where('d.actif = :actif')
                ->setParameter('actif', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'cantons' => $this->createQueryBuilder('d')
                ->select('COUNT(DISTINCT d.codeCanton)')
                ->where('d.actif = :actif')
                ->andWhere('d.codeCanton IS NOT NULL')
                ->setParameter('actif', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'epci' => $this->createQueryBuilder('d')
                ->select('COUNT(DISTINCT d.codeEpci)')
                ->where('d.actif = :actif')
                ->andWhere('d.codeEpci IS NOT NULL')
                ->setParameter('actif', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'departements' => $this->createQueryBuilder('d')
                ->select('COUNT(DISTINCT d.codeDepartement)')
                ->where('d.actif = :actif')
                ->setParameter('actif', true)
                ->getQuery()
                ->getSingleScalarResult(),
            'regions' => $this->createQueryBuilder('d')
                ->select('COUNT(DISTINCT d.codeRegion)')
                ->where('d.actif = :actif')
                ->setParameter('actif', true)
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Recherche avancée avec filtres multiples
     */
    public function rechercheAvancee(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.actif = :actif')
            ->setParameter('actif', true);

        if (!empty($filtres['terme'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('d.codePostal', ':terme'),
                    $qb->expr()->like('LOWER(d.nomCommune)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomCanton)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomEpci)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomDepartement)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(d.nomRegion)', 'LOWER(:terme)')
                )
            )
            ->setParameter('terme', '%' . $filtres['terme'] . '%');
        }

        if (!empty($filtres['departement'])) {
            $qb->andWhere('d.codeDepartement = :dept')
               ->setParameter('dept', $filtres['departement']);
        }

        if (!empty($filtres['region'])) {
            $qb->andWhere('d.codeRegion = :region')
               ->setParameter('region', $filtres['region']);
        }

        if (!empty($filtres['epci'])) {
            $qb->andWhere('d.codeEpci = :epci')
               ->setParameter('epci', $filtres['epci']);
        }

        if (!empty($filtres['canton'])) {
            $qb->andWhere('d.codeCanton = :canton')
               ->setParameter('canton', $filtres['canton']);
        }

        if (isset($filtres['avec_population']) && $filtres['avec_population']) {
            $qb->andWhere('d.population IS NOT NULL AND d.population > 0');
        }

        if (isset($filtres['avec_coordonnees']) && $filtres['avec_coordonnees']) {
            $qb->andWhere('d.latitude IS NOT NULL AND d.longitude IS NOT NULL');
        }

        // Tri par pertinence
        $qb->orderBy('d.nomCommune', 'ASC')
           ->addOrderBy('d.codePostal', 'ASC');

        // Limite par défaut
        $limit = $filtres['limit'] ?? 100;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les divisions proches géographiquement
     * Rayon en kilomètres
     */
    public function findProches(float $latitude, float $longitude, float $rayonKm = 10): array
    {
        // Formule de distance orthodromique simplifiée
        // 111.045 = approximation km par degré de latitude
        $deltaLat = $rayonKm / 111.045;
        $deltaLon = $rayonKm / (111.045 * cos(deg2rad($latitude)));

        return $this->createQueryBuilder('d')
            ->where('d.actif = :actif')
            ->andWhere('d.latitude IS NOT NULL')
            ->andWhere('d.longitude IS NOT NULL')
            ->andWhere('d.latitude BETWEEN :latMin AND :latMax')
            ->andWhere('d.longitude BETWEEN :lonMin AND :lonMax')
            ->setParameter('actif', true)
            ->setParameter('latMin', $latitude - $deltaLat)
            ->setParameter('latMax', $latitude + $deltaLat)
            ->setParameter('lonMin', $longitude - $deltaLon)
            ->setParameter('lonMax', $longitude + $deltaLon)
            ->orderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(DivisionAdministrative $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DivisionAdministrative $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}