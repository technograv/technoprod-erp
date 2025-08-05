<?php

namespace App\Service;

use App\Entity\DivisionAdministrative;
use App\Entity\TypeSecteur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ImportDivisionsAdministrativesService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Importe un échantillon de données INSEE représentatif
     */
    public function importerDonneesEchantillon(): array
    {
        $stats = [
            'total' => 0,
            'succes' => 0,
            'erreurs' => 0,
            'doublons' => 0
        ];

        // Données d'échantillon avec principales villes françaises
        $donneesEchantillon = $this->getDonneesEchantillon();

        foreach ($donneesEchantillon as $donnee) {
            try {
                $stats['total']++;
                
                // Vérifier si la division existe déjà
                $existante = $this->entityManager
                    ->getRepository(DivisionAdministrative::class)
                    ->findOneBy([
                        'codePostal' => $donnee['code_postal'],
                        'codeInseeCommune' => $donnee['code_insee']
                    ]);

                if ($existante) {
                    $stats['doublons']++;
                    continue;
                }

                $division = new DivisionAdministrative();
                $division->setCodePostal($donnee['code_postal'])
                        ->setCodeInseeCommune($donnee['code_insee'])
                        ->setNomCommune($donnee['nom_commune'])
                        ->setCodeCanton($donnee['code_canton'] ?? null)
                        ->setNomCanton($donnee['nom_canton'] ?? null)
                        ->setCodeEpci($donnee['code_epci'] ?? null)
                        ->setNomEpci($donnee['nom_epci'] ?? null)
                        ->setTypeEpci($donnee['type_epci'] ?? null)
                        ->setCodeDepartement($donnee['code_departement'])
                        ->setNomDepartement($donnee['nom_departement'])
                        ->setCodeRegion($donnee['code_region'])
                        ->setNomRegion($donnee['nom_region'])
                        ->setLatitude($donnee['latitude'] ?? null)
                        ->setLongitude($donnee['longitude'] ?? null)
                        ->setPopulation($donnee['population'] ?? null)
                        ->setActif(true);

                $this->entityManager->persist($division);
                $stats['succes']++;

                // Flush par batch de 50
                if ($stats['succes'] % 50 === 0) {
                    $this->entityManager->flush();
                }

            } catch (\Exception $e) {
                $stats['erreurs']++;
                $this->logger->error('Erreur import division administrative', [
                    'donnee' => $donnee,
                    'erreur' => $e->getMessage()
                ]);
            }
        }

        $this->entityManager->flush();
        return $stats;
    }

    /**
     * Importe les types de secteur standard
     */
    public function importerTypesSecteurStandard(): array
    {
        $stats = ['total' => 0, 'succes' => 0, 'doublons' => 0];

        $typesStandard = [
            [
                'code' => 'CP',
                'nom' => 'Par code postal',
                'type' => TypeSecteur::TYPE_CODE_POSTAL,
                'description' => 'Attribution de secteur par code postal individuel',
                'ordre' => 1
            ],
            [
                'code' => 'COMMUNE',
                'nom' => 'Par commune',
                'type' => TypeSecteur::TYPE_COMMUNE,
                'description' => 'Attribution de secteur par commune complète',
                'ordre' => 2
            ],
            [
                'code' => 'CANTON',
                'nom' => 'Par canton',
                'type' => TypeSecteur::TYPE_CANTON,
                'description' => 'Attribution de secteur par canton administratif',
                'ordre' => 3
            ],
            [
                'code' => 'EPCI',
                'nom' => 'Par intercommunalité',
                'type' => TypeSecteur::TYPE_EPCI,
                'description' => 'Attribution par Établissement Public de Coopération Intercommunale',
                'ordre' => 4
            ],
            [
                'code' => 'DEPT',
                'nom' => 'Par département',
                'type' => TypeSecteur::TYPE_DEPARTEMENT,
                'description' => 'Attribution de secteur par département entier',
                'ordre' => 5
            ],
            [
                'code' => 'REGION',
                'nom' => 'Par région',
                'type' => TypeSecteur::TYPE_REGION,
                'description' => 'Attribution de secteur par région administrative',
                'ordre' => 6
            ]
        ];

        foreach ($typesStandard as $typeData) {
            $stats['total']++;

            // Vérifier si existe déjà
            $existant = $this->entityManager
                ->getRepository(TypeSecteur::class)
                ->findOneBy(['code' => $typeData['code']]);

            if ($existant) {
                $stats['doublons']++;
                continue;
            }

            $typeSecteur = new TypeSecteur();
            $typeSecteur->setCode($typeData['code'])
                       ->setNom($typeData['nom'])
                       ->setType($typeData['type'])
                       ->setDescription($typeData['description'])
                       ->setOrdre($typeData['ordre'])
                       ->setActif(true);

            $this->entityManager->persist($typeSecteur);
            $stats['succes']++;
        }

        $this->entityManager->flush();
        return $stats;
    }

    /**
     * Données d'échantillon représentatives
     */
    private function getDonneesEchantillon(): array
    {
        return [
            // Île-de-France - Paris
            [
                'code_postal' => '75001',
                'code_insee' => '75101',
                'nom_commune' => 'Paris 1er Arrondissement',
                'code_canton' => '7501',
                'nom_canton' => 'Paris 1er',
                'code_epci' => '200054781',
                'nom_epci' => 'Métropole du Grand Paris',
                'type_epci' => 'ME',
                'code_departement' => '75',
                'nom_departement' => 'Paris',
                'code_region' => '11',
                'nom_region' => 'Île-de-France',
                'latitude' => '48.8606111',
                'longitude' => '2.3354556',
                'population' => 16888
            ],
            [
                'code_postal' => '92100',
                'code_insee' => '92012',
                'nom_commune' => 'Boulogne-Billancourt',
                'code_canton' => '9201',
                'nom_canton' => 'Boulogne-Billancourt-1',
                'code_epci' => '200054781',
                'nom_epci' => 'Métropole du Grand Paris',
                'type_epci' => 'ME',
                'code_departement' => '92',
                'nom_departement' => 'Hauts-de-Seine',
                'code_region' => '11',
                'nom_region' => 'Île-de-France',
                'latitude' => '48.8369444',
                'longitude' => '2.2425000',
                'population' => 121583
            ],
            [
                'code_postal' => '77100',
                'code_insee' => '77284',
                'nom_commune' => 'Meaux',
                'code_canton' => '7716',
                'nom_canton' => 'Meaux',
                'code_epci' => '200072134',
                'nom_epci' => 'Communauté d\'Agglomération du Pays de Meaux',
                'type_epci' => 'CA',
                'code_departement' => '77',
                'nom_departement' => 'Seine-et-Marne',
                'code_region' => '11',
                'nom_region' => 'Île-de-France',
                'latitude' => '48.9597222',
                'longitude' => '2.8888889',
                'population' => 55750
            ],

            // Auvergne-Rhône-Alpes - Lyon
            [
                'code_postal' => '69001',
                'code_insee' => '69381',
                'nom_commune' => 'Lyon 1er Arrondissement',
                'code_canton' => '6901',
                'nom_canton' => 'Lyon-1er',
                'code_epci' => '200046977',
                'nom_epci' => 'Métropole de Lyon',
                'type_epci' => 'ME',
                'code_departement' => '69',
                'nom_departement' => 'Rhône',
                'code_region' => '84',
                'nom_region' => 'Auvergne-Rhône-Alpes',
                'latitude' => '45.7681944',
                'longitude' => '4.8347222',
                'population' => 29494
            ],
            [
                'code_postal' => '38000',
                'code_insee' => '38185',
                'nom_commune' => 'Grenoble',
                'code_canton' => '3812',
                'nom_canton' => 'Grenoble-2',
                'code_epci' => '200040715',
                'nom_epci' => 'Grenoble-Alpes Métropole',
                'type_epci' => 'ME',
                'code_departement' => '38',
                'nom_departement' => 'Isère',
                'code_region' => '84',
                'nom_region' => 'Auvergne-Rhône-Alpes',
                'latitude' => '45.1875000',
                'longitude' => '5.7263889',
                'population' => 158552
            ],

            // Provence-Alpes-Côte d'Azur - Marseille
            [
                'code_postal' => '13001',
                'code_insee' => '13201',
                'nom_commune' => 'Marseille 1er Arrondissement',
                'code_canton' => '1301',
                'nom_canton' => 'Marseille-1',
                'code_epci' => '200054807',
                'nom_epci' => 'Métropole d\'Aix-Marseille-Provence',
                'type_epci' => 'ME',
                'code_departement' => '13',
                'nom_departement' => 'Bouches-du-Rhône',
                'code_region' => '93',
                'nom_region' => 'Provence-Alpes-Côte d\'Azur',
                'latitude' => '43.2969444',
                'longitude' => '5.3802778',
                'population' => 39100
            ],
            [
                'code_postal' => '06000',
                'code_insee' => '06088',
                'nom_commune' => 'Nice',
                'code_canton' => '0601',
                'nom_canton' => 'Nice-1',
                'code_epci' => '200030195',
                'nom_epci' => 'Métropole Nice Côte d\'Azur',
                'type_epci' => 'ME',
                'code_departement' => '06',
                'nom_departement' => 'Alpes-Maritimes',
                'code_region' => '93',
                'nom_region' => 'Provence-Alpes-Côte d\'Azur',
                'latitude' => '43.7009358',
                'longitude' => '7.2683912',
                'population' => 338620
            ],

            // Occitanie - Toulouse
            [
                'code_postal' => '31000',
                'code_insee' => '31555',
                'nom_commune' => 'Toulouse',
                'code_canton' => '3111',
                'nom_canton' => 'Toulouse-1',
                'code_epci' => '243100518',
                'nom_epci' => 'Toulouse Métropole',
                'type_epci' => 'ME',
                'code_departement' => '31',
                'nom_departement' => 'Haute-Garonne',
                'code_region' => '76',
                'nom_region' => 'Occitanie',
                'latitude' => '43.6044622',
                'longitude' => '1.4442469',
                'population' => 479553
            ],
            [
                'code_postal' => '34000',
                'code_insee' => '34172',
                'nom_commune' => 'Montpellier',
                'code_canton' => '3401',
                'nom_canton' => 'Montpellier-1',
                'code_epci' => '243400017',
                'nom_epci' => 'Montpellier Méditerranée Métropole',
                'type_epci' => 'ME',
                'code_departement' => '34',
                'nom_departement' => 'Hérault',
                'code_region' => '76',
                'nom_region' => 'Occitanie',
                'latitude' => '43.6112422',
                'longitude' => '3.8767337',
                'population' => 285121
            ],

            // Nouvelle-Aquitaine - Bordeaux
            [
                'code_postal' => '33000',
                'code_insee' => '33063',
                'nom_commune' => 'Bordeaux',
                'code_canton' => '3301',
                'nom_canton' => 'Bordeaux-1',
                'code_epci' => '243300316',
                'nom_epci' => 'Bordeaux Métropole',
                'type_epci' => 'ME',
                'code_departement' => '33',
                'nom_departement' => 'Gironde',
                'code_region' => '75',
                'nom_region' => 'Nouvelle-Aquitaine',
                'latitude' => '44.8378196',
                'longitude' => '-0.5792663',
                'population' => 254436
            ],

            // Hauts-de-France - Lille
            [
                'code_postal' => '59000',
                'code_insee' => '59350',
                'nom_commune' => 'Lille',
                'code_canton' => '5934',
                'nom_canton' => 'Lille-1',
                'code_epci' => '245900410',
                'nom_epci' => 'Métropole Européenne de Lille',
                'type_epci' => 'ME',
                'code_departement' => '59',
                'nom_departement' => 'Nord',
                'code_region' => '32',
                'nom_region' => 'Hauts-de-France',
                'latitude' => '50.6365654',
                'longitude' => '3.0635282',
                'population' => 232787
            ],

            // Grand Est - Strasbourg
            [
                'code_postal' => '67000',
                'code_insee' => '67482',
                'nom_commune' => 'Strasbourg',
                'code_canton' => '6701',
                'nom_canton' => 'Strasbourg-1',
                'code_epci' => '246700488',
                'nom_epci' => 'Eurométropole de Strasbourg',
                'type_epci' => 'ME',
                'code_departement' => '67',
                'nom_departement' => 'Bas-Rhin',
                'code_region' => '44',
                'nom_region' => 'Grand Est',
                'latitude' => '48.5734053',
                'longitude' => '7.7521113',
                'population' => 280966
            ],

            // Pays de la Loire - Nantes
            [
                'code_postal' => '44000',
                'code_insee' => '44109',
                'nom_commune' => 'Nantes',
                'code_canton' => '4401',
                'nom_canton' => 'Nantes-1',
                'code_epci' => '244400404',
                'nom_epci' => 'Nantes Métropole',
                'type_epci' => 'ME',
                'code_departement' => '44',
                'nom_departement' => 'Loire-Atlantique',
                'code_region' => '52',
                'nom_region' => 'Pays de la Loire',
                'latitude' => '47.2186371',
                'longitude' => '-1.5541362',
                'population' => 314138
            ],

            // Bretagne - Rennes
            [
                'code_postal' => '35000',
                'code_insee' => '35238',
                'nom_commune' => 'Rennes',
                'code_canton' => '3501',
                'nom_canton' => 'Rennes-1',
                'code_epci' => '243500139',
                'nom_epci' => 'Rennes Métropole',
                'type_epci' => 'ME',
                'code_departement' => '35',
                'nom_departement' => 'Ille-et-Vilaine',
                'code_region' => '53',
                'nom_region' => 'Bretagne',
                'latitude' => '48.1113387',
                'longitude' => '-1.6800198',
                'population' => 217728
            ],

            // Normandie - Rouen
            [
                'code_postal' => '76000',
                'code_insee' => '76540',
                'nom_commune' => 'Rouen',
                'code_canton' => '7640',
                'nom_canton' => 'Rouen-1',
                'code_epci' => '200023414',
                'nom_epci' => 'Métropole Rouen Normandie',
                'type_epci' => 'ME',
                'code_departement' => '76',
                'nom_departement' => 'Seine-Maritime',
                'code_region' => '28',
                'nom_region' => 'Normandie',
                'latitude' => '49.4404591',
                'longitude' => '1.0939658',
                'population' => 110755
            ],

            // Exemples ruraux
            [
                'code_postal' => '77200',
                'code_insee' => '77482',
                'nom_commune' => 'Torcy',
                'code_canton' => '7720',
                'nom_canton' => 'Torcy',
                'code_epci' => '200072193',
                'nom_epci' => 'Communauté d\'Agglomération de Marne et Gondoire',
                'type_epci' => 'CA',
                'code_departement' => '77',
                'nom_departement' => 'Seine-et-Marne',
                'code_region' => '11',
                'nom_region' => 'Île-de-France',
                'latitude' => '48.8481944',
                'longitude' => '2.6527778',
                'population' => 23058
            ],
            [
                'code_postal' => '77160',
                'code_insee' => '77379',
                'nom_commune' => 'Provins',
                'code_canton' => '7718',
                'nom_canton' => 'Provins',
                'code_epci' => '200072346',
                'nom_epci' => 'Communauté de Communes du Provinois',
                'type_epci' => 'CC',
                'code_departement' => '77',
                'nom_departement' => 'Seine-et-Marne',
                'code_region' => '11',
                'nom_region' => 'Île-de-France',
                'latitude' => '48.5597222',
                'longitude' => '3.2991667',
                'population' => 11602
            ]
        ];
    }

    /**
     * Obtient des statistiques après import
     */
    public function getStatistiquesApresImport(): array
    {
        return $this->entityManager
            ->getRepository(DivisionAdministrative::class)
            ->getStatistiquesCouverture();
    }

    /**
     * Nettoie les données doublons (si nécessaire)
     */
    public function nettoyerDoublons(): array
    {
        $stats = ['supprimes' => 0, 'conserves' => 0];

        // Trouver les doublons par code postal + code INSEE
        $doublons = $this->entityManager->createQuery('
            SELECT d.codePostal, d.codeInseeCommune, COUNT(d.id) as nb
            FROM App\Entity\DivisionAdministrative d
            GROUP BY d.codePostal, d.codeInseeCommune
            HAVING COUNT(d.id) > 1
        ')->getResult();

        foreach ($doublons as $doublon) {
            $divisions = $this->entityManager
                ->getRepository(DivisionAdministrative::class)
                ->findBy([
                    'codePostal' => $doublon['codePostal'],
                    'codeInseeCommune' => $doublon['codeInseeCommune']
                ]);

            // Conserver le premier, supprimer les autres
            $aConserver = array_shift($divisions);
            $stats['conserves']++;

            foreach ($divisions as $aSupprimer) {
                $this->entityManager->remove($aSupprimer);
                $stats['supprimes']++;
            }
        }

        $this->entityManager->flush();
        return $stats;
    }
}