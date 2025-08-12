<?php

namespace App\Repository;

use App\Entity\AttributionSecteur;
use App\Entity\Secteur;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributionSecteur>
 */
class AttributionSecteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributionSecteur::class);
    }

    /**
     * Trouve toutes les attributions d'un secteur donné
     */
    public function findBySecteur(Secteur $secteur): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->where('a.secteur = :secteur')
            ->setParameter('secteur', $secteur)
            ->orderBy('a.typeCritere', 'ASC')
            ->addOrderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les attributions d'un type donné
     */
    public function findByTypeCritere(string $typeCritere): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.secteur', 's')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->where('a.typeCritere = :type')
            ->setParameter('type', $typeCritere)
            ->orderBy('s.nomSecteur', 'ASC')
            ->addOrderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le secteur qui couvre une division administrative donnée
     */
    public function findSecteurCouvrant(DivisionAdministrative $division): ?Secteur
    {
        // Recherche par ordre de priorité : plus précis vers plus général
        $typesPriorite = [
            TypeSecteur::TYPE_CODE_POSTAL,
            TypeSecteur::TYPE_COMMUNE,
            TypeSecteur::TYPE_CANTON,
            TypeSecteur::TYPE_EPCI,
            TypeSecteur::TYPE_DEPARTEMENT,
            TypeSecteur::TYPE_REGION
        ];

        foreach ($typesPriorite as $type) {
            $valeur = match($type) {
                TypeSecteur::TYPE_CODE_POSTAL => $division->getCodePostal(),
                TypeSecteur::TYPE_COMMUNE => $division->getCodeInseeCommune(),
                TypeSecteur::TYPE_CANTON => $division->getCodeCanton(),
                TypeSecteur::TYPE_EPCI => $division->getCodeEpci(),
                TypeSecteur::TYPE_DEPARTEMENT => $division->getCodeDepartement(),
                TypeSecteur::TYPE_REGION => $division->getCodeRegion(),
                default => null
            };

            if ($valeur) {
                $attribution = $this->createQueryBuilder('a')
                    ->leftJoin('a.secteur', 's')
                    ->where('a.typeCritere = :type')
                    ->andWhere('a.valeurCritere = :valeur')
                    ->andWhere('s.isActive = :actif')
                    ->setParameter('type', $type)
                    ->setParameter('valeur', $valeur)
                    ->setParameter('actif', true)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($attribution) {
                    return $attribution->getSecteur();
                }
            }
        }

        return null;
    }

    /**
     * Trouve toutes les divisions administratives couvertes par un secteur
     */
    public function findDivisionsCouvertes(Secteur $secteur): array
    {
        return $this->createQueryBuilder('a')
            ->select('d')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->where('a.secteur = :secteur')
            ->setParameter('secteur', $secteur)
            ->orderBy('d.nomCommune', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de conflits : plusieurs secteurs couvrant la même division
     */
    public function findConflits(): array
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT a1.valeurCritere, a1.typeCritere, 
                       s1.nomSecteur as secteur1, s2.nomSecteur as secteur2,
                       d.nomCommune, d.codePostal
                FROM App\Entity\AttributionSecteur a1
                LEFT JOIN App\Entity\AttributionSecteur a2 WITH a1.valeurCritere = a2.valeurCritere 
                    AND a1.typeCritere = a2.typeCritere AND a1.id != a2.id
                LEFT JOIN a1.secteur s1
                LEFT JOIN a2.secteur s2
                LEFT JOIN a1.divisionAdministrative d
                WHERE a2.id IS NOT NULL
                ORDER BY a1.valeurCritere, s1.nomSecteur
            ')
            ->getResult();
    }

    /**
     * Statistiques de couverture par type de critère
     */
    public function getStatistiquesCouverture(): array
    {
        $stats = [];
        
        foreach (TypeSecteur::TYPES_DISPONIBLES as $type => $libelle) {
            $nbAttributions = $this->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.typeCritere = :type')
                ->setParameter('type', $type)
                ->getQuery()
                ->getSingleScalarResult();

            $nbSecteursUtilisant = $this->createQueryBuilder('a')
                ->select('COUNT(DISTINCT a.secteur)')
                ->where('a.typeCritere = :type')
                ->setParameter('type', $type)
                ->getQuery()
                ->getSingleScalarResult();

            $stats[$type] = [
                'libelle' => $libelle,
                'nb_attributions' => $nbAttributions,
                'nb_secteurs' => $nbSecteursUtilisant
            ];
        }

        return $stats;
    }

    /**
     * Trouve les attributions par valeur de critère (ex: toutes les attributions sur le 77)
     */
    public function findByValeurCritere(string $valeur, ?string $typeCritere = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.secteur', 's')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->where('a.valeurCritere = :valeur')
            ->setParameter('valeur', $valeur);

        if ($typeCritere) {
            $qb->andWhere('a.typeCritere = :type')
               ->setParameter('type', $typeCritere);
        }

        return $qb->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée avec filtres multiples
     */
    public function rechercheAvancee(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.secteur', 's')
            ->leftJoin('a.divisionAdministrative', 'd')
            ->leftJoin('s.commercial', 'u');

        if (!empty($filtres['secteur_id'])) {
            $qb->andWhere('s.id = :secteurId')
               ->setParameter('secteurId', $filtres['secteur_id']);
        }

        if (!empty($filtres['commercial_id'])) {
            $qb->andWhere('u.id = :commercialId')
               ->setParameter('commercialId', $filtres['commercial_id']);
        }

        if (!empty($filtres['type_critere'])) {
            $qb->andWhere('a.typeCritere = :typeCritere')
               ->setParameter('typeCritere', $filtres['type_critere']);
        }

        if (!empty($filtres['valeur_critere'])) {
            $qb->andWhere('a.valeurCritere LIKE :valeurCritere')
               ->setParameter('valeurCritere', '%' . $filtres['valeur_critere'] . '%');
        }

        if (!empty($filtres['departement'])) {
            $qb->andWhere('d.codeDepartement = :departement')
               ->setParameter('departement', $filtres['departement']);
        }

        if (!empty($filtres['region'])) {
            $qb->andWhere('d.codeRegion = :region')
               ->setParameter('region', $filtres['region']);
        }

        if (!empty($filtres['terme'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(d.nomCommune)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(s.nomSecteur)', 'LOWER(:terme)'),
                    $qb->expr()->like('a.valeurCritere', ':terme'),
                    $qb->expr()->like('LOWER(a.notes)', 'LOWER(:terme)')
                )
            )
            ->setParameter('terme', '%' . $filtres['terme'] . '%');
        }

        if (isset($filtres['secteur_actif'])) {
            $qb->andWhere('s.isActive = :secteurActif')
               ->setParameter('secteurActif', $filtres['secteur_actif']);
        }

        // Tri par défaut
        $qb->orderBy('s.nomSecteur', 'ASC')
           ->addOrderBy('a.typeCritere', 'ASC')
           ->addOrderBy('d.nomCommune', 'ASC');

        // Limite
        if (!empty($filtres['limit'])) {
            $qb->setMaxResults($filtres['limit']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si une division administrative est déjà couverte par un secteur
     */
    public function estDejaCouvertePar(DivisionAdministrative $division, string $typeCritere): ?AttributionSecteur
    {
        $valeur = match($typeCritere) {
            TypeSecteur::TYPE_CODE_POSTAL => $division->getCodePostal(),
            TypeSecteur::TYPE_COMMUNE => $division->getCodeInseeCommune(),
            TypeSecteur::TYPE_CANTON => $division->getCodeCanton(),
            TypeSecteur::TYPE_EPCI => $division->getCodeEpci(),
            TypeSecteur::TYPE_DEPARTEMENT => $division->getCodeDepartement(),
            TypeSecteur::TYPE_REGION => $division->getCodeRegion(),
            default => null
        };

        if (!$valeur) {
            return null;
        }

        return $this->createQueryBuilder('a')
            ->where('a.typeCritere = :type')
            ->andWhere('a.valeurCritere = :valeur')
            ->setParameter('type', $typeCritere)
            ->setParameter('valeur', $valeur)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Supprime toutes les attributions d'un secteur
     */
    public function supprimerToutesAttributions(Secteur $secteur): int
    {
        return $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\AttributionSecteur a WHERE a.secteur = :secteur')
            ->setParameter('secteur', $secteur)
            ->executeUpdate();
    }

    /**
     * Attributions en masse par critères
     */
    public function attribuerEnMasse(Secteur $secteur, array $divisions, string $typeCritere): int
    {
        $em = $this->getEntityManager();
        $compteur = 0;

        foreach ($divisions as $division) {
            // Vérifier si pas déjà attribuée
            if (!$this->estDejaCouvertePar($division, $typeCritere)) {
                $attribution = AttributionSecteur::creerDepuisDivision($secteur, $division, $typeCritere);
                $em->persist($attribution);
                $compteur++;
            }
        }

        $em->flush();
        return $compteur;
    }

    /**
     * Migration depuis l'ancien système de zones
     */
    public function migrerDepuisZones(): array
    {
        // Récupérer toutes les zones existantes avec leurs secteurs
        $zonesAvecSecteurs = $this->getEntityManager()
            ->createQuery('
                SELECT z.codePostal, z.ville, s.id as secteur_id, s.nomSecteur
                FROM App\Entity\Zone z
                LEFT JOIN z.secteurs s
                WHERE z.codePostal IS NOT NULL AND z.ville IS NOT NULL
                ORDER BY s.nomSecteur, z.ville
            ')
            ->getResult();

        $resultats = [
            'migrations' => [],
            'erreurs' => [],
            'statistiques' => ['total' => 0, 'reussies' => 0, 'echecs' => 0]
        ];

        foreach ($zonesAvecSecteurs as $zoneData) {
            try {
                // Chercher la division administrative correspondante
                $division = $this->getEntityManager()
                    ->getRepository(DivisionAdministrative::class)
                    ->createQueryBuilder('d')
                    ->where('d.codePostal = :cp')
                    ->andWhere('LOWER(d.nomCommune) = LOWER(:ville)')
                    ->setParameter('cp', $zoneData['codePostal'])
                    ->setParameter('ville', $zoneData['ville'])
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($division && $zoneData['secteur_id']) {
                    $secteur = $this->getEntityManager()
                        ->getRepository(Secteur::class)
                        ->find($zoneData['secteur_id']);

                    if ($secteur) {
                        // Créer l'attribution par code postal
                        if (!$this->estDejaCouvertePar($division, TypeSecteur::TYPE_CODE_POSTAL)) {
                            $attribution = AttributionSecteur::creerDepuisDivision(
                                $secteur, 
                                $division, 
                                TypeSecteur::TYPE_CODE_POSTAL
                            );
                            $attribution->setNotes('Migré depuis ancien système Zone');
                            
                            $this->getEntityManager()->persist($attribution);
                            
                            $resultats['migrations'][] = [
                                'secteur' => $zoneData['nomSecteur'],
                                'division' => $division->getCodePostal() . ' ' . $division->getNomCommune(),
                                'type' => 'code_postal'
                            ];
                            $resultats['statistiques']['reussies']++;
                        }
                    }
                } else {
                    $resultats['erreurs'][] = "Division non trouvée : {$zoneData['codePostal']} {$zoneData['ville']}";
                    $resultats['statistiques']['echecs']++;
                }
                
                $resultats['statistiques']['total']++;
                
            } catch (\Exception $e) {
                $resultats['erreurs'][] = "Erreur migration {$zoneData['codePostal']} {$zoneData['ville']} : " . $e->getMessage();
                $resultats['statistiques']['echecs']++;
            }
        }

        $this->getEntityManager()->flush();
        return $resultats;
    }

    public function save(AttributionSecteur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AttributionSecteur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}