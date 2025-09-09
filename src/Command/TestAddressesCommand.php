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
    name: 'app:test-addresses',
    description: 'Test addresses API logic for a client'
)]
class TestAddressesCommand extends Command
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
        
        $output->writeln("=== SIMULATION API ADDRESSES ===");
        
        $addresses = [];
        $addressesAdded = []; // Pour éviter les doublons
        
        foreach ($client->getContacts() as $contact) {
            $adresse = $contact->getAdresse();
            if ($adresse && !in_array($adresse->getId(), $addressesAdded)) {
                $label = ($adresse->getNom() ?? 'Adresse') . ' - ' . $adresse->getLigne1() . ' - ' . $adresse->getVille();
                
                $addressArray = [
                    'id' => $adresse->getId(),
                    'label' => $label
                ];
                
                $addresses[] = $addressArray;
                $addressesAdded[] = $adresse->getId(); // Marquer comme ajoutée
                
                $output->writeln("Adresse ID: " . $addressArray['id']);
                $output->writeln("Label: " . $addressArray['label']);
                $output->writeln("Contact associé: " . $contact->getId() . " - " . $contact->getNom());
                $output->writeln("---");
            } else if ($adresse && in_array($adresse->getId(), $addressesAdded)) {
                $output->writeln("DÉDOUBLONNÉE - Adresse ID: " . $adresse->getId() . " déjà ajoutée (Contact: " . $contact->getId() . " - " . $contact->getNom() . ")");
            }
        }
        
        $output->writeln("\n=== JSON RESULT ===");
        $output->writeln(json_encode($addresses, JSON_PRETTY_PRINT));
        
        return Command::SUCCESS;
    }
}