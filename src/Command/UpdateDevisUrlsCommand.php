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
    name: 'app:update-devis-urls',
    description: 'Met à jour les URLs d\'accès client avec les nouveaux tokens sécurisés',
)]
class UpdateDevisUrlsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Mise à jour des URLs d\'accès client');

        $baseUrl = $_ENV['APP_BASE_URL'] ?? 'https://test.decorpub.fr:8080';

        // Récupérer tous les devis avec URL d'accès client
        $devisRepository = $this->entityManager->getRepository(Devis::class);
        $devisAvecUrl = $devisRepository->createQueryBuilder('d')
            ->where('d.urlAccesClient IS NOT NULL')
            ->getQuery()
            ->getResult();

        $count = count($devisAvecUrl);

        if ($count === 0) {
            $io->success('Aucun devis avec URL à mettre à jour !');
            return Command::SUCCESS;
        }

        $io->note(sprintf('Nombre de devis avec URL : %d', $count));
        $io->progressStart($count);

        $updated = 0;
        foreach ($devisAvecUrl as $devis) {
            // Générer la nouvelle URL avec le token sécurisé
            $token = $devis->getClientAccessToken();
            $newUrl = $baseUrl . '/devis/' . $devis->getId() . '/client/' . $token;

            if ($devis->getUrlAccesClient() !== $newUrl) {
                $devis->setUrlAccesClient($newUrl);
                $updated++;
            }

            // Flush tous les 50 devis
            if ($updated % 50 === 0) {
                $this->entityManager->flush();
                $io->progressAdvance(50);
            }
        }

        // Flush final
        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('✅ %d URLs mises à jour avec les nouveaux tokens sécurisés !', $updated));

        return Command::SUCCESS;
    }
}
