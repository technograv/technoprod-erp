<?php

namespace App\Command;

use App\Entity\DivisionAdministrative;
use App\Service\EpciBoundariesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

#[AsCommand(
    name: 'app:import-epci-boundaries',
    description: 'Importe les vraies frontières géographiques des EPCI depuis l\'API officielle'
)]
class ImportEpciBoundariesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private EpciBoundariesService $epciBoundariesService;

    public function __construct(EntityManagerInterface $entityManager, EpciBoundariesService $epciBoundariesService)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->epciBoundariesService = $epciBoundariesService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Import des frontières géographiques des EPCI');
        
        // Récupérer tous les codes EPCI uniques de la base
        $io->info('📊 Récupération des codes EPCI depuis la base de données...');
        
        $codesEpci = $this->entityManager->getRepository(DivisionAdministrative::class)
            ->createQueryBuilder('d')
            ->select('DISTINCT d.codeEpci, d.nomEpci, d.typeEpci')
            ->where('d.codeEpci IS NOT NULL')
            ->andWhere('d.codeEpci != \'\'')
            ->orderBy('d.nomEpci', 'ASC')
            ->getQuery()
            ->getResult();

        $totalEpci = count($codesEpci);
        $io->info("📍 {$totalEpci} EPCI trouvés dans la base de données");

        if ($totalEpci === 0) {
            $io->warning('❌ Aucun EPCI trouvé dans la base de données');
            return Command::FAILURE;
        }

        // Initialiser le cache
        $cache = new FilesystemAdapter('epci_boundaries', 0, '/tmp/technoprod_cache');

        $progress = $io->createProgressBar($totalEpci);
        $progress->start();

        $successCount = 0;
        $errorCount = 0;
        $cacheHits = 0;

        foreach ($codesEpci as $epciData) {
            $codeEpci = $epciData['codeEpci'];
            $nomEpci = $epciData['nomEpci'];
            $typeEpci = $epciData['typeEpci'];

            // Vérifier si déjà en cache
            $cacheKey = "boundaries_{$codeEpci}";
            $cachedBoundaries = $cache->getItem($cacheKey);

            if ($cachedBoundaries->isHit()) {
                $cacheHits++;
                $progress->advance();
                continue;
            }

            // Récupérer les données complètes des communes de l'EPCI
            $communesEpci = $this->entityManager->getRepository(DivisionAdministrative::class)
                ->createQueryBuilder('d')
                ->where('d.codeEpci = :codeEpci')
                ->andWhere('d.actif = true')
                ->andWhere('d.latitude IS NOT NULL')
                ->andWhere('d.longitude IS NOT NULL')
                ->andWhere('d.codeInseeCommune IS NOT NULL')
                ->setParameter('codeEpci', $codeEpci)
                ->getQuery()
                ->getResult();

            if (count($communesEpci) < 2) {
                $errorCount++;
                $io->writeln("");
                $io->error("❌ Pas assez de communes pour {$nomEpci} ({$codeEpci}) - " . count($communesEpci) . " communes");
                $progress->advance();
                continue;
            }

            // Préparer les données complètes des communes pour l'algorithme
            $communesData = [];
            foreach ($communesEpci as $commune) {
                $communesData[] = [
                    'codeInseeCommune' => $commune->getCodeInseeCommune(),
                    'nomCommune' => $commune->getNomCommune(),
                    'latitude' => (float) $commune->getLatitude(),
                    'longitude' => (float) $commune->getLongitude()
                ];
            }

            // Calculer des frontières réelles avec les géométries officielles des communes
            $boundaries = $this->epciBoundariesService->getEpciBoundaries($codeEpci, $communesData);

            if ($boundaries !== null && $this->epciBoundariesService->areBoundariesValid($boundaries)) {
                // Simplifier les contours pour améliorer les performances
                $simplifiedBoundaries = $this->epciBoundariesService->simplifyBoundaries($boundaries, 0.001);
                
                // Calculer le centre
                $center = $this->epciBoundariesService->calculateCentroid($simplifiedBoundaries);

                // Mettre en cache
                $cacheData = [
                    'code_epci' => $codeEpci,
                    'nom_epci' => $nomEpci,
                    'type_epci' => $typeEpci,
                    'boundaries' => $simplifiedBoundaries,
                    'center' => $center,
                    'communes_count' => count($communesEpci),
                    'original_points' => count($boundaries),
                    'simplified_points' => count($simplifiedBoundaries),
                    'algorithm' => 'real_geometries_api',
                    'imported_at' => new \DateTimeImmutable()
                ];

                $cachedBoundaries->set($cacheData);
                $cache->save($cachedBoundaries);

                $successCount++;
                
                $io->writeln("");
                $io->success("✅ {$nomEpci} ({$typeEpci}) - {$codeEpci}");
                $io->info("   Communes: {$cacheData['communes_count']} | Points: {$cacheData['original_points']} → {$cacheData['simplified_points']} (géométries réelles)");
                
            } else {
                $errorCount++;
                $io->writeln("");
                $io->error("❌ Échec pour {$nomEpci} ({$codeEpci})");
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        // Résumé
        $io->section('📊 Résumé de l\'import');
        $io->table(['Métrique', 'Valeur'], [
            ['EPCI traités', $totalEpci],
            ['Succès', $successCount],
            ['Erreurs', $errorCount],
            ['Déjà en cache', $cacheHits],
            ['Taux de succès', round(($successCount / $totalEpci) * 100, 1) . '%']
        ]);

        if ($successCount > 0) {
            $io->success("🎉 Import terminé ! {$successCount} frontières EPCI importées et mises en cache.");
            $io->info("💡 Les contours sont maintenant disponibles pour l'affichage des cartes");
        } else {
            $io->warning("⚠️  Aucune frontière n'a pu être importée");
        }

        return Command::SUCCESS;
    }
}