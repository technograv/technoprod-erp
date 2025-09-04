<?php

namespace App\Service;

use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\AttributionSecteur;
use App\Entity\DivisionAdministrative;
use App\Repository\SecteurRepository;
use App\Repository\AttributionSecteurRepository;
use Doctrine\ORM\EntityManagerInterface;

class SecteurService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SecteurRepository $secteurRepository,
        private AttributionSecteurRepository $attributionRepository
    ) {}

    public function getSecteursForUser(User $user): array
    {
        if ($this->isUserAdmin($user)) {
            return $this->secteurRepository->findAll();
        }

        return $this->secteurRepository->findBy(['commercial' => $user, 'active' => true]);
    }

    public function getSecteurStats(): array
    {
        $total = $this->secteurRepository->count([]);
        $active = $this->secteurRepository->count(['active' => true]);
        $withZones = $this->secteurRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->innerJoin('s.zones', 'z')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'with_zones' => $withZones
        ];
    }

    public function createSecteur(array $data, ?User $commercial = null): Secteur
    {
        $secteur = new Secteur();
        $secteur->setNom($data['nom']);
        $secteur->setDescription($data['description'] ?? null);
        $secteur->setCommercial($commercial);
        $secteur->setActive($data['active'] ?? true);

        $this->entityManager->persist($secteur);
        $this->entityManager->flush();

        return $secteur;
    }

    public function updateSecteur(Secteur $secteur, array $data, ?User $commercial = null): Secteur
    {
        $secteur->setNom($data['nom']);
        $secteur->setDescription($data['description'] ?? null);
        $secteur->setCommercial($commercial);
        $secteur->setActive($data['active'] ?? true);

        $this->entityManager->flush();

        return $secteur;
    }

    public function deleteSecteur(Secteur $secteur): bool
    {
        try {
            $this->entityManager->remove($secteur);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function addZoneToSecteur(Secteur $secteur, DivisionAdministrative $division): AttributionSecteur
    {
        $attribution = new AttributionSecteur();
        $attribution->setSecteur($secteur);
        $attribution->setDivision($division);

        $this->entityManager->persist($attribution);
        $this->entityManager->flush();

        return $attribution;
    }

    public function removeZoneFromSecteur(Secteur $secteur, DivisionAdministrative $division): bool
    {
        $attribution = $this->attributionRepository->findOneBy([
            'secteur' => $secteur,
            'division' => $division
        ]);

        if ($attribution) {
            $this->entityManager->remove($attribution);
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    public function getZonesForSecteur(Secteur $secteur): array
    {
        $attributions = $this->attributionRepository->findBy(['secteur' => $secteur]);
        
        return array_map(function($attribution) {
            return $attribution->getDivision();
        }, $attributions);
    }

    public function getSecteurGeoData(): array
    {
        $secteurs = $this->secteurRepository->findBy(['active' => true]);
        $geoData = [];

        foreach ($secteurs as $secteur) {
            $zones = $this->getZonesForSecteur($secteur);
            
            $secteurData = [
                'id' => $secteur->getId(),
                'nom' => $secteur->getNom(),
                'description' => $secteur->getDescription(),
                'commercial' => $secteur->getCommercial() ? $secteur->getCommercial()->getNom() : 'Non assigné',
                'zones' => []
            ];

            foreach ($zones as $zone) {
                $secteurData['zones'][] = [
                    'type' => $zone->getType(),
                    'nom' => $zone->getNom(),
                    'code' => $zone->getCode(),
                    'coordonnees' => [
                        'lat' => $zone->getLatitude(),
                        'lng' => $zone->getLongitude()
                    ]
                ];
            }

            $geoData[] = $secteurData;
        }

        return $geoData;
    }

    public function canUserManageSecteur(User $user, Secteur $secteur): bool
    {
        if ($this->isUserAdmin($user)) {
            return true;
        }

        return $secteur->getCommercial() && $secteur->getCommercial()->getId() === $user->getId();
    }

    private function isUserAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles());
    }

    public function getSecteursWithStats(): array
    {
        $secteurs = $this->secteurRepository->findAll();
        $secteursWithStats = [];

        foreach ($secteurs as $secteur) {
            $zones = $this->getZonesForSecteur($secteur);
            $clientsCount = $this->countClientsInSecteur($secteur);

            $secteursWithStats[] = [
                'secteur' => $secteur,
                'zones_count' => count($zones),
                'clients_count' => $clientsCount
            ];
        }

        return $secteursWithStats;
    }

    private function countClientsInSecteur(Secteur $secteur): int
    {
        return $this->entityManager->getRepository('App:Client')
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.secteur = :secteur')
            ->setParameter('secteur', $secteur)
            ->getQuery()
            ->getSingleScalarResult();
    }
}