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
    name: 'app:import-france-complete',
    description: 'Import complet de toutes les données administratives françaises (communes, départements, régions)'
)]
class ImportFranceCompleteCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🇫🇷 Import complet des données administratives françaises');

        // Statistiques avant import
        $this->displayStats($io, 'AVANT IMPORT');

        // Étape 1: Import toutes les communes françaises
        $io->section('📍 Étape 1: Import des communes françaises');
        $this->importCommunes($io);

        // Étape 2: Nettoyage des doublons
        $io->section('🧹 Étape 2: Nettoyage des doublons');
        $this->cleanDuplicates($io);

        // Étape 3: Vérification de la cohérence
        $io->section('✅ Étape 3: Vérification de la cohérence');
        $this->verifyData($io);

        // Statistiques après import
        $this->displayStats($io, 'APRÈS IMPORT');

        $io->success('Import terminé avec succès !');
        return Command::SUCCESS;
    }

    private function importCommunes(SymfonyStyle $io): void
    {
        $io->info('Récupération de toutes les communes françaises depuis l\'API officielle...');

        try {
            // API officielle: toutes les communes françaises avec leurs données administratives
            $response = $this->httpClient->request('GET', 'https://geo.api.gouv.fr/communes', [
                'query' => [
                    'fields' => 'nom,code,codesPostaux,centre,departement,region,epci,canton',
                    'format' => 'json'
                ],
                'timeout' => 60
            ]);

            if ($response->getStatusCode() !== 200) {
                $io->error('Erreur lors de la récupération des données');
                return;
            }

            $communes = $response->toArray();
            $io->info(sprintf('🎯 %d communes récupérées depuis l\'API', count($communes)));

            $progressBar = $io->createProgressBar(count($communes));
            $progressBar->start();

            $nouvelles = 0;
            $mises_a_jour = 0;
            $batch = 0;

            foreach ($communes as $communeData) {
                $division = $this->findOrCreateDivision($communeData);
                
                if ($division->getId() === null) {
                    $nouvelles++;
                } else {
                    $mises_a_jour++;
                }

                $this->entityManager->persist($division);
                
                // Batch processing pour éviter les problèmes de mémoire
                if (++$batch % 100 === 0) {
                    try {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    } catch (\Exception $e) {
                        $io->error("Erreur batch {$batch}: " . $e->getMessage());
                        // Réouvrir l'EntityManager si fermé
                        if (!$this->entityManager->isOpen()) {
                            $this->entityManager = $this->entityManager->create(
                                $this->entityManager->getConnection(),
                                $this->entityManager->getConfiguration()
                            );
                        }
                    }
                }

                $progressBar->advance();
            }

            // Flush final
            try {
                $this->entityManager->flush();
                $this->entityManager->clear();
            } catch (\Exception $e) {
                $io->error("Erreur flush final: " . $e->getMessage());
            }

            $progressBar->finish();
            $io->newLine();
            $io->success(sprintf('✅ Import terminé: %d nouvelles communes, %d mises à jour', $nouvelles, $mises_a_jour));

        } catch (\Exception $e) {
            $io->error('Erreur durant l\'import: ' . $e->getMessage());
        }
    }

    private function findOrCreateDivision(array $data): DivisionAdministrative
    {
        // Chercher par code INSEE (identifiant unique)
        $division = $this->entityManager->getRepository(DivisionAdministrative::class)
            ->findOneBy(['codeInseeCommune' => $data['code']]);

        if (!$division) {
            $division = new DivisionAdministrative();
        }

        // Remplir les données
        $division->setCodeInseeCommune($data['code']);
        $division->setNomCommune($data['nom']);
        
        // Codes postaux (peut y en avoir plusieurs)
        if (isset($data['codesPostaux']) && !empty($data['codesPostaux'])) {
            $division->setCodePostal($data['codesPostaux'][0]); // Premier code postal
        }

        // Coordonnées géographiques
        if (isset($data['centre']['coordinates'])) {
            $division->setLongitude((string) $data['centre']['coordinates'][0]);
            $division->setLatitude((string) $data['centre']['coordinates'][1]);
        }

        // Département
        if (isset($data['departement'])) {
            $division->setCodeDepartement($data['departement']['code']);
            $division->setNomDepartement($data['departement']['nom']);
        }

        // Région
        if (isset($data['region'])) {
            $division->setCodeRegion($data['region']['code']);
            $division->setNomRegion($data['region']['nom']);
        }

        // EPCI
        if (isset($data['epci'])) {
            $division->setCodeEpci($data['epci']['code']);
            $division->setNomEpci($data['epci']['nom']);
        }

        // Canton
        if (isset($data['canton'])) {
            $division->setCodeCanton($data['canton']['code']);
            $division->setNomCanton($data['canton']['nom']);
        }

        $division->setActif(true);

        return $division;
    }

    private function cleanDuplicates(SymfonyStyle $io): void
    {
        $io->info('Recherche et suppression des doublons...');

        // Trouver les doublons par code INSEE
        $sql = "
            SELECT code_insee_commune, COUNT(*) as count 
            FROM division_administrative 
            WHERE code_insee_commune IS NOT NULL 
            GROUP BY code_insee_commune 
            HAVING COUNT(*) > 1
        ";

        $duplicates = $this->entityManager->getConnection()->executeQuery($sql)->fetchAllAssociative();
        
        if (empty($duplicates)) {
            $io->success('✅ Aucun doublon trouvé');
            return;
        }

        $io->warning(sprintf('⚠️ %d codes INSEE en doublon trouvés', count($duplicates)));

        foreach ($duplicates as $duplicate) {
            $codeInsee = $duplicate['code_insee_commune'];
            $count = $duplicate['count'];
            
            $io->text("🔍 Code INSEE {$codeInsee}: {$count} entrées");

            // Garder la plus récente (par ID) et supprimer les autres
            $divisions = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->findBy(['codeInseeCommune' => $codeInsee], ['id' => 'DESC']);

            for ($i = 1; $i < count($divisions); $i++) {
                $this->entityManager->remove($divisions[$i]);
            }
        }

        $this->entityManager->flush();
        $io->success('✅ Doublons supprimés');
    }

    private function verifyData(SymfonyStyle $io): void
    {
        $io->info('Vérification de la cohérence des données...');

        // Vérifications de base
        $checks = [
            'Communes sans nom' => "nom_commune IS NULL OR nom_commune = ''",
            'Communes sans code INSEE' => "code_insee_commune IS NULL OR code_insee_commune = ''",
            'Communes sans département' => "code_departement IS NULL OR code_departement = ''",
            'Communes sans région' => "code_region IS NULL OR code_region = ''",
            'Communes sans coordonnées' => "latitude IS NULL OR longitude IS NULL"
        ];

        foreach ($checks as $description => $condition) {
            $count = $this->entityManager->getConnection()->executeQuery(
                "SELECT COUNT(*) FROM division_administrative WHERE actif = true AND ({$condition})"
            )->fetchOne();

            if ($count > 0) {
                $io->warning("⚠️ {$description}: {$count}");
            } else {
                $io->text("✅ {$description}: 0");
            }
        }
    }

    private function displayStats(SymfonyStyle $io, string $moment): void
    {
        $io->section("📊 Statistiques {$moment}");

        $stats = $this->entityManager->getConnection()->executeQuery("
            SELECT 
                COUNT(*) as total_entries,
                COUNT(DISTINCT code_insee_commune) as communes_distinctes,
                COUNT(DISTINCT code_departement) as departements,
                COUNT(DISTINCT code_region) as regions,
                COUNT(DISTINCT code_epci) as epcis,
                COUNT(DISTINCT code_canton) as cantons,
                COUNT(DISTINCT code_postal) as codes_postaux
            FROM division_administrative 
            WHERE actif = true
        ")->fetchAssociative();

        $table = $io->createTable();
        $table->setHeaders(['Métrique', 'Valeur']);
        $table->addRows([
            ['Total entrées', number_format($stats['total_entries'])],
            ['Communes distinctes', number_format($stats['communes_distinctes'])],
            ['Départements', number_format($stats['departements'])],
            ['Régions', number_format($stats['regions'])],
            ['EPCIs', number_format($stats['epcis'])],
            ['Cantons', number_format($stats['cantons'])],
            ['Codes postaux', number_format($stats['codes_postaux'])]
        ]);
        $table->render();
    }
}