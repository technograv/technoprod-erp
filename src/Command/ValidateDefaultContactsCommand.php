<?php

namespace App\Command;

use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:validate-default-contacts',
    description: 'Valide que tous les clients avec des contacts ont des contacts par défaut assignés',
)]
class ValidateDefaultContactsCommand extends Command
{
    public function __construct(
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Corriger automatiquement en assignant le premier contact comme contact par défaut')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fix = $input->getOption('fix');
        
        $io->title('Validation des contacts par défaut');
        
        $clients = $this->clientRepository->findAll();
        $clientsWithoutFacturation = [];
        $clientsWithoutLivraison = [];
        $correctedClients = [];
        
        foreach ($clients as $client) {
            $contacts = $client->getContacts();
            
            if ($contacts->count() > 0) {
                $hasFacturationDefault = false;
                $hasLivraisonDefault = false;
                $firstContact = $contacts->first();
                
                foreach ($contacts as $contact) {
                    if ($contact->isFacturationDefault()) {
                        $hasFacturationDefault = true;
                    }
                    if ($contact->isLivraisonDefault()) {
                        $hasLivraisonDefault = true;
                    }
                }
                
                $clientName = $client->getNomEntreprise() ?: $client->getNom();
                
                if (!$hasFacturationDefault) {
                    $clientsWithoutFacturation[] = $clientName;
                    
                    if ($fix) {
                        $firstContact->setIsFacturationDefault(true);
                        $correctedClients[] = $clientName . ' (facturation)';
                        $io->text("✓ Assigné contact facturation: {$firstContact->getPrenom()} {$firstContact->getNom()} pour {$clientName}");
                    }
                }
                
                if (!$hasLivraisonDefault) {
                    $clientsWithoutLivraison[] = $clientName;
                    
                    if ($fix) {
                        $firstContact->setIsLivraisonDefault(true);
                        $correctedClients[] = $clientName . ' (livraison)';
                        $io->text("✓ Assigné contact livraison: {$firstContact->getPrenom()} {$firstContact->getNom()} pour {$clientName}");
                    }
                }
            }
        }
        
        // Afficher les résultats
        if (!empty($clientsWithoutFacturation)) {
            $io->section('Clients sans contact de facturation par défaut:');
            $io->listing($clientsWithoutFacturation);
        }
        
        if (!empty($clientsWithoutLivraison)) {
            $io->section('Clients sans contact de livraison par défaut:');
            $io->listing($clientsWithoutLivraison);
        }
        
        if ($fix && !empty($correctedClients)) {
            $this->entityManager->flush();
            $io->success(sprintf('Corrections appliquées sur %d assignments de contacts par défaut', count($correctedClients)));
        } elseif (!$fix && (!empty($clientsWithoutFacturation) || !empty($clientsWithoutLivraison))) {
            $io->note('Utilisez l\'option --fix pour corriger automatiquement ces problèmes');
        } elseif (empty($clientsWithoutFacturation) && empty($clientsWithoutLivraison)) {
            $io->success('Tous les clients avec des contacts ont des contacts par défaut correctement assignés');
        }
        
        return Command::SUCCESS;
    }
}