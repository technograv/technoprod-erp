<?php

namespace App\Command;

use App\Entity\AuditTrail;
use App\Repository\AuditTrailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rebuild-audit-chain',
    description: 'Reconstruit la chaîne d\'audit pour corriger les ruptures de chaînage',
)]
class RebuildAuditChainCommand extends Command
{
    private EntityManagerInterface $em;
    private AuditTrailRepository $auditRepo;

    public function __construct(EntityManagerInterface $em, AuditTrailRepository $auditRepo)
    {
        $this->em = $em;
        $this->auditRepo = $auditRepo;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les modifications sans les appliquer')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limite le nombre d\'enregistrements à traiter', 1000)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $limit = (int)$input->getOption('limit');

        $io->title('🔗 Reconstruction de la chaîne d\'audit');

        // Récupérer tous les enregistrements d'audit par ordre chronologique
        $audits = $this->em->createQueryBuilder()
            ->select('at')
            ->from(AuditTrail::class, 'at')
            ->orderBy('at.timestamp', 'ASC')
            ->addOrderBy('at.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        if (empty($audits)) {
            $io->warning('Aucun enregistrement d\'audit trouvé.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Traitement de %d enregistrements d\'audit...', count($audits)));

        $previousHash = null;
        $updatedCount = 0;
        $errorCount = 0;

        if (!$dryRun) {
            $this->em->beginTransaction();
        }

        try {
            foreach ($audits as $key => $audit) {
                $needsUpdate = false;
                $originalPreviousHash = $audit->getPreviousRecordHash();
                
                // Le premier enregistrement ne doit pas avoir de hash précédent
                if ($key === 0) {
                    if ($audit->getPreviousRecordHash() !== null) {
                        $audit->setPreviousRecordHash(null);
                        $needsUpdate = true;
                        $io->writeln(sprintf('  - Audit #%d: Correction du premier enregistrement (previous_hash: %s -> null)', 
                            $audit->getId(), 
                            $originalPreviousHash ? substr($originalPreviousHash, 0, 8) . '...' : 'null'
                        ));
                    }
                } else {
                    // Les autres enregistrements doivent chaîner avec le précédent
                    if ($audit->getPreviousRecordHash() !== $previousHash) {
                        $audit->setPreviousRecordHash($previousHash);
                        $needsUpdate = true;
                        $io->writeln(sprintf('  - Audit #%d: Correction du chaînage (previous_hash: %s -> %s)', 
                            $audit->getId(), 
                            $originalPreviousHash ? substr($originalPreviousHash, 0, 8) . '...' : 'null',
                            $previousHash ? substr($previousHash, 0, 8) . '...' : 'null'
                        ));
                    }
                }

                // Recalculer le hash si nécessaire
                if ($needsUpdate) {
                    $newHash = $this->calculateAuditHash($audit);
                    $audit->setRecordHash($newHash);
                    
                    if (!$dryRun) {
                        $this->em->persist($audit);
                    }
                    
                    $updatedCount++;
                }

                $previousHash = $audit->getRecordHash();
            }

            if (!$dryRun && $updatedCount > 0) {
                $this->em->flush();
                $this->em->commit();
            }

            if ($dryRun) {
                $io->info(sprintf('Mode dry-run: %d enregistrements seraient mis à jour', $updatedCount));
            } else {
                $io->success(sprintf('✅ Chaîne d\'audit reconstruite: %d enregistrements mis à jour', $updatedCount));
            }

            // Vérification finale
            $io->section('Vérification de la chaîne reconstruite');
            $verification = $this->auditRepo->verifyAuditChain($limit);
            
            if ($verification['valid']) {
                $io->success(sprintf('✅ Chaîne d\'audit intègre (%d enregistrements vérifiés)', $verification['total_records']));
            } else {
                $io->error(sprintf('❌ %d erreurs détectées dans la chaîne d\'audit:', count($verification['errors'])));
                foreach (array_slice($verification['errors'], 0, 5) as $error) {
                    $io->writeln(sprintf('  - Audit #%d: %s', $error['audit_id'], $error['error']));
                }
                if (count($verification['errors']) > 5) {
                    $io->writeln(sprintf('  ... et %d autres erreurs', count($verification['errors']) - 5));
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            if (!$dryRun) {
                $this->em->rollback();
            }
            
            $io->error('Erreur lors de la reconstruction: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Calcule le hash d'un enregistrement audit (copie de la méthode AuditService)
     */
    private function calculateAuditHash(AuditTrail $audit): string
    {
        $data = [
            'entity_type' => $audit->getEntityType(),
            'entity_id' => $audit->getEntityId(),
            'action' => $audit->getAction(),
            'old_values' => $audit->getOldValues(),
            'new_values' => $audit->getNewValues(),
            'user_id' => $audit->getUser()->getId(),
            'timestamp' => $audit->getTimestamp()->format('Y-m-d H:i:s.u'),
            'ip_address' => $audit->getIpAddress(),
            'previous_hash' => $audit->getPreviousRecordHash()
        ];
        
        return hash('sha256', json_encode($data, 64 | \JSON_UNESCAPED_UNICODE)); // 64 = JSON_SORT_KEYS
    }
}
