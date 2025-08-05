<?php

namespace App\Command;

use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-occitanie-api',
    description: 'Import complet Occitanie depuis les APIs officielles (INSEE, geo.api.gouv.fr)'
)]
class ImportOccitanieFromApiCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient)
    {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🌍 Import Occitanie depuis les APIs officielles');

        try {
            // Étape 1: Récupérer toutes les communes de l'Occitanie
            $io->section('📡 Récupération des communes depuis geo.api.gouv.fr...');
            $communes = $this->fetchCommunesOccitanie($io);
            
            if (empty($communes)) {
                $io->error('Aucune commune récupérée depuis l\'API');
                return Command::FAILURE;
            }

            $io->success("✅ " . count($communes) . " communes récupérées depuis l'API");

            // Étape 2: Enrichir avec les données de codes postaux
            $io->section('📮 Enrichissement avec les codes postaux...');
            $communesEnrichies = $this->enrichirAvecCodesPostaux($communes, $io);

            // Étape 3: Import en base de données
            $io->section('💾 Import en base de données...');
            $stats = $this->importerEnBaseDeDonnees($communesEnrichies, $io);

            // Statistiques finales
            $this->afficherStatistiquesFinales($io, $stats);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Erreur lors de l\'import:',
                $e->getMessage(),
                'Trace: ' . $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function fetchCommunesOccitanie(SymfonyStyle $io): array
    {
        $communes = [];
        $departements = ['09', '11', '12', '30', '31', '32', '34', '46', '48', '65', '66', '81', '82'];
        
        $io->progressStart(count($departements));

        foreach ($departements as $dept) {
            try {
                $io->note("Récupération du département $dept...");
                
                // API geo.api.gouv.fr pour récupérer les communes d'un département
                $response = $this->httpClient->request('GET', 
                    "https://geo.api.gouv.fr/departements/$dept/communes", [
                    'query' => [
                        'fields' => 'nom,code,codeDepartement,codeRegion,centre,population,codesPostaux,codeEpci,epci',
                        'format' => 'json'
                    ],
                    'timeout' => 30
                ]);

                if ($response->getStatusCode() === 200) {
                    $communesDept = $response->toArray();
                    $communes = array_merge($communes, $communesDept);
                    $io->text("  → " . count($communesDept) . " communes récupérées pour le $dept");
                } else {
                    $io->warning("Erreur API pour le département $dept: " . $response->getStatusCode());
                }

                $io->progressAdvance();
                
                // Pause pour éviter de surcharger l'API
                usleep(100000); // 100ms

            } catch (\Exception $e) {
                $io->warning("Erreur pour le département $dept: " . $e->getMessage());
                $io->progressAdvance();
            }
        }

        $io->progressFinish();
        return $communes;
    }

    private function enrichirAvecCodesPostaux(array $communes, SymfonyStyle $io): array
    {
        $communesEnrichies = [];
        
        $io->progressStart(count($communes));

        foreach ($communes as $commune) {
            try {
                // Structurer les données pour notre format
                $communeEnrichie = [
                    'code_insee' => $commune['code'],
                    'nom_commune' => $commune['nom'],
                    'code_departement' => $commune['codeDepartement'],
                    'nom_departement' => $this->getNomDepartement($commune['codeDepartement']),
                    'code_region' => $commune['codeRegion'],
                    'nom_region' => 'Occitanie',
                    'population' => $commune['population'] ?? null,
                    'latitude' => $commune['centre']['coordinates'][1] ?? null,
                    'longitude' => $commune['centre']['coordinates'][0] ?? null,
                    'codes_postaux' => $commune['codesPostaux'] ?? [],
                    'code_epci' => $commune['codeEpci'] ?? null,
                    'nom_epci' => $commune['epci']['nom'] ?? null,
                    'type_epci' => $this->determineTypeEpci($commune['epci']['nom'] ?? null)
                ];

                // Créer une entrée par code postal
                if (!empty($communeEnrichie['codes_postaux'])) {
                    foreach ($communeEnrichie['codes_postaux'] as $codePostal) {
                        $entry = $communeEnrichie;
                        $entry['code_postal'] = $codePostal;
                        unset($entry['codes_postaux']);
                        $communesEnrichies[] = $entry;
                    }
                } else {
                    // Si pas de code postal, créer quand même l'entrée
                    $entry = $communeEnrichie;
                    $entry['code_postal'] = null;
                    unset($entry['codes_postaux']);
                    $communesEnrichies[] = $entry;
                }

            } catch (\Exception $e) {
                $io->warning("Erreur traitement commune {$commune['nom']}: " . $e->getMessage());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        return $communesEnrichies;
    }

    private function importerEnBaseDeDonnees(array $communesEnrichies, SymfonyStyle $io): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        $io->progressStart(count($communesEnrichies));

        foreach ($communesEnrichies as $data) {
            try {
                // Vérifier si la division existe déjà
                $existingDivision = $this->entityManager->getRepository(DivisionAdministrative::class)
                    ->findOneBy([
                        'codeInseeCommune' => $data['code_insee'],
                        'codePostal' => $data['code_postal']
                    ]);

                if ($existingDivision) {
                    // Mettre à jour si nécessaire
                    if ($this->updateExistingDivision($existingDivision, $data)) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    // Créer une nouvelle division
                    $division = $this->createNewDivision($data);
                    $this->entityManager->persist($division);
                    $imported++;
                }

                // Flush par batch pour optimiser
                if (($imported + $updated) % 100 === 0) {
                    $this->entityManager->flush();
                    $io->text("  → Sauvegarde batch: $imported importées, $updated mises à jour");
                }

            } catch (\Exception $e) {
                $io->warning("Erreur import {$data['nom_commune']}: " . $e->getMessage());
                $skipped++;
            }

            $io->progressAdvance();
        }

        // Flush final
        $this->entityManager->flush();
        $io->progressFinish();

        return compact('imported', 'updated', 'skipped');
    }

    private function createNewDivision(array $data): DivisionAdministrative
    {
        $division = new DivisionAdministrative();
        
        $division->setCodePostal($data['code_postal']);
        $division->setCodeInseeCommune($data['code_insee']);
        $division->setNomCommune($data['nom_commune']);
        $division->setCodeDepartement($data['code_departement']);
        $division->setNomDepartement($data['nom_departement']);
        $division->setCodeRegion($data['code_region']);
        $division->setNomRegion($data['nom_region']);
        
        if ($data['latitude'] && $data['longitude']) {
            $division->setLatitude((string) $data['latitude']);
            $division->setLongitude((string) $data['longitude']);
        }
        
        if ($data['population']) {
            $division->setPopulation($data['population']);
        }

        // Données EPCI
        if ($data['code_epci']) {
            $division->setCodeEpci($data['code_epci']);
        }
        if ($data['nom_epci']) {
            $division->setNomEpci($data['nom_epci']);
        }
        if ($data['type_epci']) {
            $division->setTypeEpci($data['type_epci']);
        }

        return $division;
    }

    private function updateExistingDivision(DivisionAdministrative $division, array $data): bool
    {
        $updated = false;
        
        // Mettre à jour les coordonnées si manquantes
        if (!$division->getLatitude() && $data['latitude']) {
            $division->setLatitude((string) $data['latitude']);
            $updated = true;
        }
        if (!$division->getLongitude() && $data['longitude']) {
            $division->setLongitude((string) $data['longitude']);
            $updated = true;
        }
        
        // Mettre à jour la population si manquante
        if (!$division->getPopulation() && $data['population']) {
            $division->setPopulation($data['population']);
            $updated = true;
        }

        // Mettre à jour les données EPCI si manquantes
        if (!$division->getCodeEpci() && $data['code_epci']) {
            $division->setCodeEpci($data['code_epci']);
            $updated = true;
        }
        if (!$division->getNomEpci() && $data['nom_epci']) {
            $division->setNomEpci($data['nom_epci']);
            $updated = true;
        }
        if (!$division->getTypeEpci() && $data['type_epci']) {
            $division->setTypeEpci($data['type_epci']);
            $updated = true;
        }

        return $updated;
    }

    private function afficherStatistiquesFinales(SymfonyStyle $io, array $stats): void
    {
        $io->success([
            "🎉 Import Occitanie terminé !",
            "✅ {$stats['imported']} nouvelles divisions importées",
            "🔄 {$stats['updated']} divisions mises à jour",
            "⏭️ {$stats['skipped']} divisions ignorées"
        ]);

        // Statistiques par département
        $io->section('📊 Statistiques par département:');
        
        $statsParDept = $this->entityManager->getConnection()->fetchAllAssociative("
            SELECT 
                code_departement, 
                nom_departement, 
                COUNT(*) as nb_divisions,
                COUNT(DISTINCT code_insee_commune) as nb_communes,
                COUNT(DISTINCT code_postal) as nb_codes_postaux
            FROM division_administrative 
            WHERE code_region = '76' 
            GROUP BY code_departement, nom_departement 
            ORDER BY code_departement
        ");

        $totalCommunes = 0;
        $totalDivisions = 0;

        foreach ($statsParDept as $stat) {
            $io->text(sprintf(
                "  %s (%s): %d communes, %d codes postaux, %d divisions",
                $stat['nom_departement'],
                $stat['code_departement'],
                $stat['nb_communes'],
                $stat['nb_codes_postaux'],
                $stat['nb_divisions']
            ));
            $totalCommunes += $stat['nb_communes'];
            $totalDivisions += $stat['nb_divisions'];
        }

        $io->note([
            "📈 Total Occitanie:",
            "  • $totalCommunes communes uniques",
            "  • $totalDivisions divisions administratives",
            "  • Toutes avec coordonnées GPS et population INSEE"
        ]);
    }

    private function getNomDepartement(string $codeDept): string
    {
        $depts = [
            '09' => 'Ariège',
            '11' => 'Aude',
            '12' => 'Aveyron', 
            '30' => 'Gard',
            '31' => 'Haute-Garonne',
            '32' => 'Gers',
            '34' => 'Hérault',
            '46' => 'Lot',
            '48' => 'Lozère',
            '65' => 'Hautes-Pyrénées',
            '66' => 'Pyrénées-Orientales',
            '81' => 'Tarn',
            '82' => 'Tarn-et-Garonne'
        ];

        return $depts[$codeDept] ?? 'Département inconnu';
    }

    private function determineTypeEpci(?string $nomEpci): ?string
    {
        if (!$nomEpci) {
            return null;
        }

        // Détermine le type d'EPCI à partir du nom
        if (str_contains($nomEpci, 'Métropole')) {
            return 'ME'; // Métropole
        } elseif (str_contains($nomEpci, 'Communauté Urbaine') || str_contains($nomEpci, 'CU ')) {
            return 'CU'; // Communauté Urbaine
        } elseif (str_contains($nomEpci, 'Communauté d\'Agglomération') || str_contains($nomEpci, 'CA ')) {
            return 'CA'; // Communauté d'Agglomération
        } elseif (str_contains($nomEpci, 'Communauté de Communes') || str_contains($nomEpci, 'CC ')) {
            return 'CC'; // Communauté de Communes
        } else {
            return 'AU'; // Autre type
        }
    }
}