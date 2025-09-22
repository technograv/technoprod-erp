<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-devis-contact',
    description: 'Test les fonctionnalités du DevisContactService',
)]
class TestDevisContactServiceCommand extends Command
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('client-id', null, InputOption::VALUE_OPTIONAL, 'ID du client à tester')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔍 Test DevisContactService - Diagnostic complet');
        
        // 1. Vérifier les clients
        $io->section('1️⃣ Clients dans la base');
        $clientsCount = $this->entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $io->info("Nombre total de clients: $clientsCount");
        
        // 2. Trouver un client avec contacts et adresses
        $io->section('2️⃣ Recherche d\'un client avec contacts par défaut');
        
        $qb = $this->entityManager->createQueryBuilder();
        $clients = $qb->select('c', 'contacts', 'adresses')
            ->from(Client::class, 'c')
            ->leftJoin('c.contacts', 'contacts')
            ->leftJoin('c.adresses', 'adresses')
            ->where($qb->expr()->orX(
                'contacts.isLivraisonDefault = true',
                'contacts.isFacturationDefault = true'
            ))
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        if (empty($clients)) {
            $io->warning('Aucun client avec contacts par défaut trouvé!');
            
            // Chercher n'importe quel client avec contacts
            $clients = $this->entityManager->getRepository(Client::class)
                ->createQueryBuilder('c')
                ->leftJoin('c.contacts', 'contacts')
                ->addSelect('contacts')
                ->where('contacts IS NOT NULL')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }
        
        if (empty($clients)) {
            $io->error('Aucun client avec contacts trouvé dans la base!');
            return Command::FAILURE;
        }
        
        // Afficher les détails du premier client trouvé
        $testClient = $clients[0];
        $io->success(sprintf('Client trouvé: #%d - %s', $testClient->getId(), $testClient->getNom()));
        
        // 3. Tester les données retournées pour ce client
        $io->section('3️⃣ Test des données API pour le client #' . $testClient->getId());
        
        // Simuler ce que retournerait l'API /client/{id}/contacts
        $contactsData = [];
        foreach ($testClient->getContacts() as $contact) {
            $contactData = [
                'id' => $contact->getId(),
                'prenom' => $contact->getPrenom(),
                'nom' => $contact->getNom(),
                'email' => $contact->getEmail(),
                'fonction' => $contact->getFonction(),
                'is_livraison_default' => $contact->isLivraisonDefault(),
                'is_facturation_default' => $contact->isFacturationDefault(),
                'adresse_id' => $contact->getAdresse() ? $contact->getAdresse()->getId() : null,
            ];
            $contactsData[] = $contactData;
            
            $io->write(sprintf(
                "  Contact #%d: %s %s%s%s",
                $contact->getId(),
                $contact->getPrenom() ?? '-',
                $contact->getNom() ?? '-',
                $contact->isLivraisonDefault() ? ' [LIVRAISON]' : '',
                $contact->isFacturationDefault() ? ' [FACTURATION]' : ''
            ));
            
            if ($contact->getEmail()) {
                $io->write(" - Email: " . $contact->getEmail());
            }
            if ($contact->getAdresse()) {
                $io->write(" - Adresse ID: " . $contact->getAdresse()->getId());
            }
            $io->writeln('');
        }
        
        $io->info(sprintf('Total: %d contacts', count($contactsData)));
        
        // Simuler ce que retournerait l'API /client/{id}/addresses
        $io->section('4️⃣ Adresses du client');
        $addressesData = [];
        foreach ($testClient->getAdresses() as $address) {
            // Créer un label si pas de méthode getLabel
            $label = sprintf('%s - %s %s', 
                $address->getLigne1() ?? '',
                $address->getCodePostal() ?? '',
                $address->getVille() ?? ''
            );
            
            $addressData = [
                'id' => $address->getId(),
                'label' => trim($label),
                'ligne1' => $address->getLigne1(),
                'codePostal' => $address->getCodePostal(),
                'ville' => $address->getVille(),
            ];
            $addressesData[] = $addressData;
            
            $io->writeln(sprintf(
                "  Adresse #%d: %s - %s %s",
                $address->getId(),
                $address->getLigne1() ?? '-',
                $address->getCodePostal() ?? '',
                $address->getVille() ?? ''
            ));
        }
        
        $io->info(sprintf('Total: %d adresses', count($addressesData)));
        
        // 5. Vérifier les problèmes potentiels
        $io->section('5️⃣ Diagnostics');
        
        // Vérifier les contacts par défaut
        $hasLivraisonDefault = false;
        $hasFacturationDefault = false;
        foreach ($contactsData as $contact) {
            if ($contact['is_livraison_default']) $hasLivraisonDefault = true;
            if ($contact['is_facturation_default']) $hasFacturationDefault = true;
        }
        
        if (!$hasLivraisonDefault) {
            $io->warning('⚠️ Aucun contact de livraison par défaut');
        } else {
            $io->success('✅ Contact de livraison par défaut trouvé');
        }
        
        if (!$hasFacturationDefault) {
            $io->warning('⚠️ Aucun contact de facturation par défaut');
        } else {
            $io->success('✅ Contact de facturation par défaut trouvé');
        }
        
        // 6. Générer un script de test JavaScript
        $io->section('6️⃣ Script de test JavaScript');
        
        $jsTest = sprintf(
            "// Copiez ce code dans la console du navigateur sur /devis/new ou /devis/{id}/edit\n" .
            "// Client de test: #%d\n" .
            "const testClientId = %d;\n" .
            "console.log('Test avec client #' + testClientId);\n\n" .
            "// Forcer le changement de client\n" .
            "if (window.devisContactService) {\n" .
            "    window.devisContactService.handleClientChange(testClientId);\n" .
            "} else {\n" .
            "    console.error('DevisContactService non trouvé!');\n" .
            "}\n\n" .
            "// Vérifier les logs\n" .
            "if (window.debugLoggers) {\n" .
            "    console.log('Logs disponibles:', Object.keys(window.debugLoggers));\n" .
            "    window.debugLoggers['DevisContactService'].summary();\n" .
            "}",
            $testClient->getId(),
            $testClient->getId()
        );
        
        $io->block($jsTest, 'JS', 'fg=cyan', ' ', true);
        
        $io->success('Test terminé! Client #' . $testClient->getId() . ' peut être utilisé pour les tests.');
        
        return Command::SUCCESS;
    }
}