<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Produit;
use App\Service\TagAssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-tag-assignment',
    description: 'Test l\'assignation automatique des tags aux clients'
)]
class TestTagAssignmentCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagAssignmentService $tagAssignmentService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test du système d\'assignation automatique des tags');

        // Récupérer un client de test
        $client = $this->entityManager->getRepository(Client::class)->findOneBy(['actif' => true]);
        if (!$client) {
            $io->error('Aucun client actif trouvé pour les tests');
            return Command::FAILURE;
        }

        $io->info("Client de test: {$client->getNom()} (ID: {$client->getId()})");
        
        // Afficher les tags actuels du client
        $currentTags = $client->getTagNames();
        $io->section('Tags actuels du client:');
        if (empty($currentTags)) {
            $io->text('Aucun tag assigné');
        } else {
            $io->listing($currentTags);
        }

        // Récupérer un produit avec des tags
        $produit = $this->entityManager->createQuery('
            SELECT p FROM App\Entity\Produit p 
            JOIN p.tags t 
            WHERE t.assignationAutomatique = true 
            AND t.actif = true
            GROUP BY p.id 
            HAVING COUNT(t.id) > 0
        ')->setMaxResults(1)->getOneOrNullResult();

        if (!$produit) {
            $io->error('Aucun produit avec tags d\'assignation automatique trouvé');
            return Command::FAILURE;
        }

        $io->info("Produit de test: {$produit->getDesignation()} ({$produit->getReference()})");
        
        $productTags = $produit->getTagNames();
        $io->section('Tags du produit:');
        $io->listing($productTags);

        // Test d'assignation automatique
        $io->section('Test d\'assignation automatique...');
        $this->tagAssignmentService->assignTagsFromProduct($client, $produit);

        // Afficher les nouveaux tags du client
        $this->entityManager->refresh($client);
        $newTags = $client->getTagNames();
        $io->section('Nouveaux tags du client:');
        if (empty($newTags)) {
            $io->text('Aucun tag assigné');
        } else {
            $io->listing($newTags);
        }

        // Afficher les statistiques
        $io->section('Statistiques des tags:');
        $statistics = $this->tagAssignmentService->getTagStatistics();
        
        $tableData = [];
        foreach ($statistics as $stat) {
            $tableData[] = [
                $stat['tag']->getNom(),
                $stat['clients_count'],
                $stat['products_count'],
                $stat['is_auto'] ? 'Oui' : 'Non',
                $stat['usage_rate'] . '%'
            ];
        }

        $io->table(
            ['Tag', 'Clients', 'Produits', 'Auto', 'Taux d\'usage'],
            $tableData
        );

        $io->success('Test terminé avec succès !');

        return Command::SUCCESS;
    }
}