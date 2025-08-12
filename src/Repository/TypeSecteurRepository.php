<?php

namespace App\Repository;

use App\Entity\TypeSecteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeSecteur>
 */
class TypeSecteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeSecteur::class);
    }

    /**
     * Trouve tous les types de secteur actifs triés par ordre
     */
    public function findAllActifs(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les types triés par ordre
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un type de secteur par son code
     */
    public function findByCode(string $code): ?TypeSecteur
    {
        return $this->createQueryBuilder('t')
            ->where('t.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les types par type de division
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->andWhere('t.actif = :actif')
            ->setParameter('type', $type)
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de secteurs utilisant ce type
     */
    public function countSecteursUtilisant(TypeSecteur $typeSecteur): int
    {
        return $this->getEntityManager()
            ->createQuery('SELECT COUNT(s.id) FROM App\Entity\Secteur s WHERE s.typeSecteur = :type')
            ->setParameter('type', $typeSecteur)
            ->getSingleScalarResult();
    }

    /**
     * Réorganise les ordres pour éviter les doublons
     */
    public function reorganizeOrdres(): void
    {
        $em = $this->getEntityManager();
        
        // Récupérer tous les types triés par ordre actuel puis par nom
        $types = $this->createQueryBuilder('t')
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();

        // Réassigner les ordres séquentiellement
        foreach ($types as $index => $type) {
            $nouvelOrdre = $index + 1;
            if ($type->getOrdre() !== $nouvelOrdre) {
                $type->setOrdre($nouvelOrdre);
                $em->persist($type);
            }
        }

        $em->flush();
    }

    /**
     * Trouve le prochain ordre disponible
     */
    public function getProchainOrdre(): int
    {
        $maxOrdre = $this->createQueryBuilder('t')
            ->select('MAX(t.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxOrdre ?? 0) + 1;
    }

    /**
     * Insère un type à un ordre donné et décale les autres
     */
    public function insererAOrdre(TypeSecteur $typeSecteur, int $ordreVoulu): void
    {
        $em = $this->getEntityManager();

        // Décaler tous les types à partir de l'ordre voulu
        $em->createQuery('UPDATE App\Entity\TypeSecteur t SET t.ordre = t.ordre + 1 WHERE t.ordre >= :ordre AND t.id != :id')
            ->setParameter('ordre', $ordreVoulu)
            ->setParameter('id', $typeSecteur->getId() ?? 0)
            ->executeUpdate();

        // Assigner l'ordre voulu au type
        $typeSecteur->setOrdre($ordreVoulu);
        $em->persist($typeSecteur);
        $em->flush();

        // Réorganiser pour éviter les trous
        $this->reorganizeOrdres();
    }

    /**
     * Recherche par nom ou description
     */
    public function search(string $terme): array
    {
        return $this->createQueryBuilder('t')
            ->where('LOWER(t.nom) LIKE LOWER(:terme)')
            ->orWhere('LOWER(t.description) LIKE LOWER(:terme)')
            ->orWhere('LOWER(t.code) LIKE LOWER(:terme)')
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'utilisation des types de secteur
     */
    public function getStatistiquesUtilisation(): array
    {
        $stats = [];
        $types = $this->findAllOrdered();

        foreach ($types as $type) {
            $nbSecteurs = $this->countSecteursUtilisant($type);
            $stats[] = [
                'type' => $type,
                'nb_secteurs' => $nbSecteurs,
                'pourcentage' => 0 // Sera calculé après
            ];
        }

        // Calculer les pourcentages
        $totalSecteurs = array_sum(array_column($stats, 'nb_secteurs'));
        if ($totalSecteurs > 0) {
            foreach ($stats as &$stat) {
                $stat['pourcentage'] = round(($stat['nb_secteurs'] / $totalSecteurs) * 100, 1);
            }
        }

        return $stats;
    }

    /**
     * Trouve les types recommandés selon la taille de l'entreprise
     */
    public function getTypesRecommandes(string $tailleEntreprise = 'PME'): array
    {
        $typesRecommandes = match($tailleEntreprise) {
            'TPE' => [TypeSecteur::TYPE_CODE_POSTAL, TypeSecteur::TYPE_COMMUNE],
            'PME' => [TypeSecteur::TYPE_COMMUNE, TypeSecteur::TYPE_CANTON, TypeSecteur::TYPE_EPCI],
            'GE' => [TypeSecteur::TYPE_DEPARTEMENT, TypeSecteur::TYPE_REGION],
            default => [TypeSecteur::TYPE_COMMUNE, TypeSecteur::TYPE_CANTON]
        };

        return $this->createQueryBuilder('t')
            ->where('t.type IN (:types)')
            ->andWhere('t.actif = :actif')
            ->setParameter('types', $typesRecommandes)
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Validation des données pour un type de secteur
     */
    public function validerDonnees(TypeSecteur $typeSecteur): array
    {
        $erreurs = [];

        // Vérifier l'unicité du code
        if ($typeSecteur->getCode()) {
            $existant = $this->createQueryBuilder('t')
                ->where('t.code = :code')
                ->andWhere('t.id != :id')
                ->setParameter('code', $typeSecteur->getCode())
                ->setParameter('id', $typeSecteur->getId() ?? 0)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existant) {
                $erreurs[] = "Le code '{$typeSecteur->getCode()}' est déjà utilisé";
            }
        }

        // Vérifier que le type est valide
        if (!array_key_exists($typeSecteur->getType(), TypeSecteur::TYPES_DISPONIBLES)) {
            $erreurs[] = "Le type '{$typeSecteur->getType()}' n'est pas valide";
        }

        // Vérifier l'ordre
        if ($typeSecteur->getOrdre() < 1) {
            $erreurs[] = "L'ordre doit être supérieur à 0";
        }

        return $erreurs;
    }

    /**
     * Génère automatiquement un code unique pour un type de secteur
     */
    public function genererCodeUnique(string $nom): string
    {
        // Normaliser le nom
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $nom), 0, 10));
        
        if (empty($base)) {
            $base = 'TYPE';
        }

        $code = $base;
        $compteur = 1;

        // Vérifier l'unicité et incrémenter si nécessaire
        while ($this->findByCode($code)) {
            $code = $base . sprintf('%02d', $compteur);
            $compteur++;
            
            // Sécurité pour éviter les boucles infinies
            if ($compteur > 99) {
                $code = $base . time();
                break;
            }
        }

        return $code;
    }

    /**
     * Obtient les types compatibles avec une division administrative donnée
     */
    public function getTypesCompatibles(string $typeDivision): array
    {
        // Mapping des types de divisions vers les types de secteur compatibles
        $compatibilites = [
            'code_postal' => [TypeSecteur::TYPE_CODE_POSTAL],
            'commune' => [TypeSecteur::TYPE_COMMUNE, TypeSecteur::TYPE_CODE_POSTAL],
            'canton' => [TypeSecteur::TYPE_CANTON, TypeSecteur::TYPE_COMMUNE],
            'epci' => [TypeSecteur::TYPE_EPCI, TypeSecteur::TYPE_COMMUNE],
            'departement' => [TypeSecteur::TYPE_DEPARTEMENT, TypeSecteur::TYPE_CANTON, TypeSecteur::TYPE_EPCI],
            'region' => [TypeSecteur::TYPE_REGION, TypeSecteur::TYPE_DEPARTEMENT],
        ];

        $types = $compatibilites[$typeDivision] ?? [];

        if (empty($types)) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.type IN (:types)')
            ->andWhere('t.actif = :actif')
            ->setParameter('types', $types)
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(TypeSecteur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TypeSecteur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}