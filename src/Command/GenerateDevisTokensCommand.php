<?php

namespace App\Command;

use App\Entity\Devis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-devis-tokens',
    description: 'Génère des tokens sécurisés pour tous les devis existants sans token',
)]
class GenerateDevisTokensCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Génération des tokens sécurisés pour les devis');

        // Récupérer tous les devis sans token
        $devisRepository = $this->entityManager->getRepository(Devis::class);
        $devisSansToken = $devisRepository->createQueryBuilder('d')
            ->where('d.clientAccessToken IS NULL')
            ->getQuery()
            ->getResult();

        $count = count($devisSansToken);

        if ($count === 0) {
            $io->success('Tous les devis ont déjà un token !');
            return Command::SUCCESS;
        }

        $io->note(sprintf('Nombre de devis sans token : %d', $count));
        $io->progressStart($count);

        $generated = 0;
        foreach ($devisSansToken as $devis) {
            // Générer un token sécurisé
            $devis->generateClientAccessToken();
            $generated++;

            // Flush tous les 50 devis pour optimiser
            if ($generated % 50 === 0) {
                $this->entityManager->flush();
                $io->progressAdvance(50);
            }
        }

        // Flush final pour les derniers devis
        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('✅ %d tokens générés avec succès !', $generated));
        $io->note('Les nouveaux tokens utilisent random_bytes(32) au lieu de MD5');

        return Command::SUCCESS;
    }
}
