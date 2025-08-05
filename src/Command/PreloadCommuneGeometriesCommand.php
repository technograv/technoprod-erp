<?php

namespace App\Command;

use App\Entity\Secteur;
use App\Entity\DivisionAdministrative;
use App\Service\CommuneGeometryCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:preload-commune-geometries',
    description: 'Pré-charge les géométries des communes depuis l\'API officielle'
)]
class PreloadCommuneGeometriesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private CommuneGeometryCacheService $cacheService;

    public function __construct(
        EntityManagerInterface $entityManager,
        CommuneGeometryCacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->cacheService = $cacheService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('epci-only', null, InputOption::VALUE_NONE, 'Pré-charger uniquement les communes des EPCI actifs')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limite le nombre de communes à traiter', 0)
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Taille des lots pour traitement', 50)
            ->addOption('delay', 'd', InputOption::VALUE_OPTIONAL, 'Délai entre appels API (ms)', 100)
            ->addOption('force-refresh', 'f', InputOption::VALUE_NONE, 'Force le rafraîchissement des données en cache')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pré-chargement des géométries des communes');

        $epciOnly = $input->getOption('epci-only');
        $limit = (int) $input->getOption('limit');
        $batchSize = (int) $input->getOption('batch-size');
        $delay = (int) $input->getOption('delay');
        $forceRefresh = $input->getOption('force-refresh');

        try {
            // 1. Collecter les communes à traiter
            $communesToProcess = $this->collectCommunesToProcess($epciOnly, $limit);
            
            if (empty($communesToProcess)) {
                $io->warning('Aucune commune à traiter');
                return Command::SUCCESS;
            }

            $io->section('Communes à traiter');
            $io->text(sprintf('Total: %d communes', count($communesToProcess)));
            
            if ($epciOnly) {
                $io->text('Mode: EPCI actifs uniquement');
            } else {
                $io->text('Mode: Toutes les communes');
            }

            // 2. Statistiques initiales du cache
            $initialStats = $this->cacheService->getCacheStats();
            $io->section('État initial du cache');
            $this->displayCacheStats($io, $initialStats);

            // 3. Traitement par lots
            $io->section('Traitement des géométries');
            
            $batches = array_chunk($communesToProcess, $batchSize);
            $totalBatches = count($batches);
            $processedCount = 0;
            $successCount = 0;
            $errorCount = 0;

            $progressBar = new ProgressBar($output, count($communesToProcess));
            $progressBar->start();

            foreach ($batches as $batchIndex => $batch) {
                $io->text(sprintf("\nTraitement lot %d/%d (%d communes)", 
                    $batchIndex + 1, $totalBatches, count($batch)
                ));

                foreach ($batch as $commune) {
                    $codeInsee = $commune['codeInseeCommune'];
                    $nomCommune = $commune['nomCommune'];

                    // Vérifier si besoin de traiter (cache existant + force refresh)
                    if (!$forceRefresh) {
                        $existing = $this->entityManager
                            ->getRepository(\App\Entity\CommuneGeometryCache::class)
                            ->findByCodeInsee($codeInsee);
                        
                        if ($existing && !$existing->isExpired()) {
                            $progressBar->advance();
                            $processedCount++;
                            continue;
                        }
                    }

                    // Récupérer la géométrie
                    $geometry = $this->cacheService->getCommuneGeometry($codeInsee, $nomCommune);
                    
                    if ($geometry) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }

                    $processedCount++;
                    $progressBar->advance();

                    // Délai entre appels pour ne pas surcharger l'API
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                }

                // Flush périodique pour libérer la mémoire
                $this->entityManager->clear();
            }

            $progressBar->finish();
            $io->newLine(2);

            // 4. Statistiques finales
            $finalStats = $this->cacheService->getCacheStats();
            $io->success('Pré-chargement terminé');
            
            $io->section('Résultats');
            $io->text(sprintf('Communes traitées: %d', $processedCount));
            $io->text(sprintf('Succès: %d', $successCount));
            $io->text(sprintf('Erreurs: %d', $errorCount));
            $io->text(sprintf('Taux de succès: %.1f%%', 
                $processedCount > 0 ? ($successCount / $processedCount) * 100 : 0
            ));

            $io->section('État final du cache');
            $this->displayCacheStats($io, $finalStats);

            $io->section('Amélioration');
            $improvement = $finalStats['valid'] - $initialStats['valid'];
            $io->text(sprintf('Nouvelles géométries en cache: +%d', $improvement));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du pré-chargement: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function collectCommunesToProcess(bool $epciOnly, int $limit): array
    {
        if ($epciOnly) {
            // Récupérer les communes des EPCI actifs
            $qb = $this->entityManager->createQueryBuilder();
            $communes = $qb->select('DISTINCT da.codeInseeCommune, da.nomCommune')
                ->from(DivisionAdministrative::class, 'da')
                ->join('App\Entity\AttributionSecteur', 'attr', 'WITH', 'attr.divisionAdministrative = da')
                ->join('attr.secteur', 's')
                ->where('s.isActive = true')
                ->andWhere('attr.typeCritere = :type')
                ->andWhere('da.codeInseeCommune IS NOT NULL')
                ->andWhere('da.nomCommune IS NOT NULL')
                ->setParameter('type', 'epci')
                ->orderBy('da.nomCommune', 'ASC');

            if ($limit > 0) {
                $qb->setMaxResults($limit);
            }

            return $qb->getQuery()->getArrayResult();
        } else {
            // Récupérer toutes les communes de la base
            $qb = $this->entityManager->createQueryBuilder();
            $communes = $qb->select('da.codeInseeCommune, da.nomCommune')
                ->from(DivisionAdministrative::class, 'da')
                ->where('da.codeInseeCommune IS NOT NULL')
                ->andWhere('da.nomCommune IS NOT NULL')
                ->orderBy('da.nomCommune', 'ASC');

            if ($limit > 0) {
                $qb->setMaxResults($limit);
            }

            return $qb->getQuery()->getArrayResult();
        }
    }

    private function displayCacheStats(SymfonyStyle $io, array $stats): void
    {
        $io->text(sprintf('Total entrées: %d', $stats['total']));
        $io->text(sprintf('Valides: %d', $stats['valid']));
        $io->text(sprintf('Invalides: %d', $stats['invalid']));
        $io->text(sprintf('Expirées: %d', $stats['expired']));
        $io->text(sprintf('Taux de couverture: %.1f%%', $stats['coverage_rate']));
    }
}