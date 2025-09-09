<?php

namespace App\Command;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-contacts',
    description: 'Test contact API logic for a client'
)]
class TestContactsCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('clientId', InputArgument::REQUIRED, 'Client ID to test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clientId = $input->getArgument('clientId');
        
        $client = $this->entityManager->getRepository(Client::class)->find($clientId);
        
        if (!$client) {
            $output->writeln("Client $clientId non trouvé");
            return Command::FAILURE;
        }
        
        $output->writeln("=== CLIENT TROUVÉ ===");
        $output->writeln("ID: " . $client->getId());
        $output->writeln("Nom: " . $client->getNom());
        $output->writeln("Prénom: " . $client->getPrenom());
        
        $output->writeln("\n=== SIMULATION API CONTACTS ===");
        
        $contacts = [];
        foreach ($client->getContacts() as $contact) {
            $label = trim(($contact->getCivilite() ?? '') . ' ' . ($contact->getPrenom() ?? '') . ' ' . ($contact->getNom() ?? ''));
            if (empty($label)) {
                $label = $contact->getEmail() ?? 'Contact sans nom';
            }
            
            $contactArray = [
                'id' => $contact->getId(),
                'label' => $label,
                'adresse_id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : null,
                'prenom' => $contact->getPrenom(),
                'nom' => $contact->getNom(),
                'fonction' => $contact->getFonction()
            ];
            
            $contacts[] = $contactArray;
            
            $output->writeln("Contact ID: " . $contactArray['id']);
            $output->writeln("Label: " . $contactArray['label']);
            $output->writeln("Adresse ID: " . ($contactArray['adresse_id'] ?? 'null'));
            $output->writeln("---");
        }
        
        $output->writeln("\n=== JSON RESULT ===");
        $output->writeln(json_encode($contacts, JSON_PRETTY_PRINT));
        
        return Command::SUCCESS;
    }
}