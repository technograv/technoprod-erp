<?php

namespace App\Command;

use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-duplicate-default-contacts',
    description: 'Détecte et corrige les clients avec plusieurs contacts par défaut du même type',
)]
class FixDuplicateDefaultContactsCommand extends Command
{
    public function __construct(
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Détection et correction des contacts par défaut dupliqués');
        
        $clients = $this->clientRepository->findAll();
        $facturationIssues = 0;
        $livraisonIssues = 0;
        $correctedClients = [];
        
        foreach ($clients as $client) {
            $facturationDefaults = [];
            $livraisonDefaults = [];
            
            // Vérifier les contacts de facturation par défaut
            foreach ($client->getContacts() as $contact) {
                if ($contact->isFacturationDefault()) {
                    $facturationDefaults[] = $contact;
                }
                if ($contact->isLivraisonDefault()) {
                    $livraisonDefaults[] = $contact;
                }
            }
            
            // Corriger les contacts de facturation dupliqués
            if (count($facturationDefaults) > 1) {
                $facturationIssues++;
                $io->warning(sprintf(
                    'Client "%s" a %d contacts de facturation par défaut', 
                    $client->getNomEntreprise() ?: $client->getNom(), 
                    count($facturationDefaults)
                ));
                
                // Garder seulement le premier, désactiver les autres
                for ($i = 1; $i < count($facturationDefaults); $i++) {
                    $facturationDefaults[$i]->setIsFacturationDefault(false);
                    $io->text(sprintf(
                        '  - Désactivé: %s %s', 
                        $facturationDefaults[$i]->getPrenom(), 
                        $facturationDefaults[$i]->getNom()
                    ));
                }
                $correctedClients[] = $client->getNomEntreprise() ?: $client->getNom();
            }
            
            // Corriger les contacts de livraison dupliqués
            if (count($livraisonDefaults) > 1) {
                $livraisonIssues++;
                $io->warning(sprintf(
                    'Client "%s" a %d contacts de livraison par défaut', 
                    $client->getNomEntreprise() ?: $client->getNom(), 
                    count($livraisonDefaults)
                ));
                
                // Garder seulement le premier, désactiver les autres
                for ($i = 1; $i < count($livraisonDefaults); $i++) {
                    $livraisonDefaults[$i]->setIsLivraisonDefault(false);
                    $io->text(sprintf(
                        '  - Désactivé: %s %s', 
                        $livraisonDefaults[$i]->getPrenom(), 
                        $livraisonDefaults[$i]->getNom()
                    ));
                }
                if (!in_array($client->getNomEntreprise() ?: $client->getNom(), $correctedClients)) {
                    $correctedClients[] = $client->getNomEntreprise() ?: $client->getNom();
                }
            }
        }
        
        // Sauvegarder les corrections
        if ($facturationIssues > 0 || $livraisonIssues > 0) {
            $this->entityManager->flush();
            $io->success(sprintf(
                'Corrections appliquées: %d problèmes de facturation, %d problèmes de livraison sur %d clients',
                $facturationIssues,
                $livraisonIssues,
                count($correctedClients)
            ));
            
            if (!empty($correctedClients)) {
                $io->section('Clients corrigés:');
                $io->listing($correctedClients);
            }
        } else {
            $io->success('Aucun problème de contacts par défaut dupliqués détecté');
        }
        
        return Command::SUCCESS;
    }
}