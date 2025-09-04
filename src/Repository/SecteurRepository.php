<?php

namespace App\Repository;

use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Secteur>
 */
class SecteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Secteur::class);
    }

    /**
     * Trouve les secteurs d'un commercial donné avec relations preloadées
     */
    public function findByCommercial(int $commercialId): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.attributions', 'a')
            ->leftJoin('a.zone', 'z')
            ->leftJoin('z.communeFrancaise', 'cf')
            ->addSelect('a', 'z', 'cf')
            ->andWhere('s.commercial = :commercial')
            ->setParameter('commercial', $commercialId)
            ->andWhere('s.isActive = true')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les secteurs actifs
     */
    public function findAllActifs(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.commercial', 'u')
            ->where('s.isActive = :actif')
            ->setParameter('actif', true)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les secteurs avec informations détaillées
     */
    public function findAllAvecDetails(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.commercial', 'u')
            ->leftJoin('s.typeSecteur', 't')
            ->leftJoin('s.attributions', 'a')
            ->leftJoin('s.clients', 'c')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de secteurs par nom ou commercial
     */
    public function search(string $terme): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.commercial', 'u')
            ->where('LOWER(s.nomSecteur) LIKE LOWER(:terme)')
            ->orWhere('LOWER(u.nom) LIKE LOWER(:terme)')
            ->orWhere('LOWER(u.prenom) LIKE LOWER(:terme)')
            ->orWhere('LOWER(s.description) LIKE LOWER(:terme)')
            ->setParameter('terme', '%' . $terme . '%')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le secteur qui couvre une division administrative
     */
    public function findCouvrantDivision(DivisionAdministrative $division): ?Secteur
    {
        // Utilisation du repository AttributionSecteur pour la logique de couverture
        return $this->getEntityManager()
            ->getRepository(\App\Entity\AttributionSecteur::class)
            ->findSecteurCouvrant($division);
    }

    /**
     * Trouve le secteur qui couvre un code postal donné
     */
    public function findCouvrantCodePostal(string $codePostal): ?Secteur
    {
        // D'abord essayer de trouver par attribution directe
        $attribution = $this->getEntityManager()
            ->getRepository(\App\Entity\AttributionSecteur::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.secteur', 's')
            ->where('a.typeCritere = :typeCP')
            ->andWhere('a.valeurCritere = :cp')
            ->andWhere('s.isActive = :actif')
            ->setParameter('typeCP', TypeSecteur::TYPE_CODE_POSTAL)
            ->setParameter('cp', $codePostal)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();

        if ($attribution) {
            return $attribution->getSecteur();
        }

        // Sinon essayer par département (les 2 premiers chiffres)
        $codeDept = substr($codePostal, 0, 2);
        $attribution = $this->getEntityManager()
            ->getRepository(\App\Entity\AttributionSecteur::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.secteur', 's')
            ->where('a.typeCritere = :typeDept')
            ->andWhere('a.valeurCritere = :dept')
            ->andWhere('s.isActive = :actif')
            ->setParameter('typeDept', TypeSecteur::TYPE_DEPARTEMENT)
            ->setParameter('dept', $codeDept)
            ->setParameter('actif', true)
            ->getQuery()
            ->getOneOrNullResult();

        return $attribution?->getSecteur();
    }

    /**
     * Statistiques des secteurs
     */
    public function getStatistiques(): array
    {
        $stats = [
            'total' => $this->count([]),
            'actifs' => $this->count(['isActive' => true]),
            'inactifs' => $this->count(['isActive' => false]),
            'avec_nouveau_systeme' => 0,
            'avec_ancien_systeme' => 0,
            'systeme_mixte' => 0
        ];

        // Statistiques des systèmes utilisés
        $secteurs = $this->findAll();
        foreach ($secteurs as $secteur) {
            $nouveauSysteme = $secteur->utiliseNouveauSysteme();
            $ancienSysteme = $secteur->utiliseAncienSysteme();

            if ($nouveauSysteme && $ancienSysteme) {
                $stats['systeme_mixte']++;
            } elseif ($nouveauSysteme) {
                $stats['avec_nouveau_systeme']++;
            } elseif ($ancienSysteme) {
                $stats['avec_ancien_systeme']++;
            }
        }

        return $stats;
    }

    /**
     * Trouve les secteurs utilisant le nouveau système
     */
    public function findAvecNouveauSysteme(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.attributions', 'a')
            ->where('s.isActive = :actif')
            ->andWhere('a.id IS NOT NULL')
            ->setParameter('actif', true)
            ->groupBy('s.id')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les secteurs utilisant encore l'ancien système
     */
    public function findAvecAncienSysteme(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.zones', 'z')
            ->leftJoin('s.secteurZones', 'sz')
            ->leftJoin('s.attributions', 'a')
            ->where('s.isActive = :actif')
            ->andWhere($this->getEntityManager()->getExpressionBuilder()->orX(
                'z.id IS NOT NULL',
                'sz.id IS NOT NULL'
            ))
            ->andWhere('a.id IS NULL')
            ->setParameter('actif', true)
            ->groupBy('s.id')
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les secteurs orphelins (sans attribution ni zone)
     */
    public function findOrphelins(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.zones', 'z')
            ->leftJoin('s.secteurZones', 'sz')
            ->leftJoin('s.attributions', 'a')
            ->where('s.isActive = :actif')
            ->andWhere('z.id IS NULL')
            ->andWhere('sz.id IS NULL')
            ->andWhere('a.id IS NULL')
            ->setParameter('actif', true)
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les secteurs d'un type donné
     */
    public function findByTypeSecteur(TypeSecteur $typeSecteur): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.typeSecteur = :type')
            ->andWhere('s.isActive = :actif')
            ->setParameter('type', $typeSecteur)
            ->setParameter('actif', true)
            ->orderBy('s.nomSecteur', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée avec filtres multiples
     */
    public function rechercheAvancee(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.commercial', 'u')
            ->leftJoin('s.typeSecteur', 't')
            ->leftJoin('s.attributions', 'a')
            ->leftJoin('s.clients', 'c');

        if (!empty($filtres['terme'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(s.nomSecteur)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(u.nom)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(u.prenom)', 'LOWER(:terme)'),
                    $qb->expr()->like('LOWER(s.description)', 'LOWER(:terme)')
                )
            )
            ->setParameter('terme', '%' . $filtres['terme'] . '%');
        }

        if (!empty($filtres['commercial_id'])) {
            $qb->andWhere('s.commercial = :commercialId')
               ->setParameter('commercialId', $filtres['commercial_id']);
        }

        if (!empty($filtres['type_secteur_id'])) {
            $qb->andWhere('s.typeSecteur = :typeSecteurId')
               ->setParameter('typeSecteurId', $filtres['type_secteur_id']);
        }

        if (isset($filtres['actif'])) {
            $qb->andWhere('s.isActive = :actif')
               ->setParameter('actif', $filtres['actif']);
        }

        if (isset($filtres['systeme'])) {
            switch ($filtres['systeme']) {
                case 'nouveau':
                    $qb->andWhere('a.id IS NOT NULL');
                    break;
                case 'ancien':
                    $qb->leftJoin('s.zones', 'z')
                       ->leftJoin('s.secteurZones', 'sz')
                       ->andWhere($qb->expr()->orX('z.id IS NOT NULL', 'sz.id IS NOT NULL'))
                       ->andWhere('a.id IS NULL');
                    break;
                case 'orphelin':
                    $qb->leftJoin('s.zones', 'z')
                       ->leftJoin('s.secteurZones', 'sz')
                       ->andWhere('z.id IS NULL')
                       ->andWhere('sz.id IS NULL')
                       ->andWhere('a.id IS NULL');
                    break;
            }
        }

        if (!empty($filtres['couleur'])) {
            $qb->andWhere('s.couleurHex = :couleur')
               ->setParameter('couleur', $filtres['couleur']);
        }

        // Groupement pour éviter les doublons avec les jointures
        $qb->groupBy('s.id', 'u.id', 't.id');

        // Tri par défaut
        $qb->orderBy('u.nom', 'ASC')
           ->addOrderBy('s.nomSecteur', 'ASC');

        // Limite
        if (!empty($filtres['limit'])) {
            $qb->setMaxResults($filtres['limit']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Rapport de couverture géographique
     */
    public function getRapportCouverture(): array
    {
        $rapport = [
            'secteurs_actifs' => $this->count(['isActive' => true]),
            'total_attributions' => 0,
            'couverture_par_type' => [],
            'commerciaux' => [],
            'conflits' => []
        ];

        // Statistiques par type de critère
        $attributionRepo = $this->getEntityManager()->getRepository(\App\Entity\AttributionSecteur::class);
        $rapport['couverture_par_type'] = $attributionRepo->getStatistiquesCouverture();
        $rapport['total_attributions'] = array_sum(array_column($rapport['couverture_par_type'], 'nb_attributions'));

        // Statistiques par commercial
        $commerciaux = $this->createQueryBuilder('s')
            ->select('u.nom, u.prenom, COUNT(s.id) as nb_secteurs, COUNT(DISTINCT a.id) as nb_attributions')
            ->leftJoin('s.commercial', 'u')
            ->leftJoin('s.attributions', 'a')
            ->where('s.isActive = :actif')
            ->setParameter('actif', true)
            ->groupBy('u.id')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $rapport['commerciaux'] = $commerciaux;

        // Conflits de couverture
        $rapport['conflits'] = $attributionRepo->findConflits();

        return $rapport;
    }

    /**
     * Validation d'un secteur
     */
    public function validerSecteur(Secteur $secteur): array
    {
        $erreurs = [];

        // Vérifier le nom unique
        $existant = $this->createQueryBuilder('s')
            ->where('LOWER(s.nomSecteur) = LOWER(:nom)')
            ->andWhere('s.id != :id')
            ->setParameter('nom', $secteur->getNomSecteur())
            ->setParameter('id', $secteur->getId() ?? 0)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existant) {
            $erreurs[] = "Le nom de secteur '{$secteur->getNomSecteur()}' est déjà utilisé";
        }

        // Vérifier qu'il y a un commercial assigné
        if (!$secteur->getCommercial()) {
            $erreurs[] = "Un commercial doit être assigné au secteur";
        }

        // Vérifier qu'il y a au moins une attribution ou zone
        if ($secteur->utiliseNouveauSysteme()) {
            if ($secteur->getAttributions()->isEmpty()) {
                $erreurs[] = "Le secteur doit avoir au moins une attribution géographique";
            }
        } elseif ($secteur->utiliseAncienSysteme()) {
            if ($secteur->getZones()->isEmpty() && $secteur->getSecteurZones()->isEmpty()) {
                $erreurs[] = "Le secteur doit avoir au moins une zone géographique";
            }
        } else {
            $erreurs[] = "Le secteur doit avoir des attributions géographiques";
        }

        return $erreurs;
    }

    public function save(Secteur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Secteur $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}