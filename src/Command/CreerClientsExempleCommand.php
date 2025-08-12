<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Adresse;
use App\Entity\Secteur;
use App\Entity\FormeJuridique;
use App\Entity\DivisionAdministrative;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:creer-clients-exemple',
    description: 'Crée des clients d\'exemple répartis dans les secteurs pour tester l\'attribution automatique',
)]
class CreerClientsExempleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création de clients d\'exemple pour tester les secteurs');

        // Récupérer les formes juridiques disponibles
        $formesJuridiques = $this->entityManager->getRepository(FormeJuridique::class)->findAll();
        if (empty($formesJuridiques)) {
            $io->error('Aucune forme juridique trouvée');
            return Command::FAILURE;
        }

        $sarl = null;
        $sas = null;
        foreach ($formesJuridiques as $forme) {
            if (str_contains(strtolower($forme->getNom()), 'sarl')) {
                $sarl = $forme;
            } elseif (str_contains(strtolower($forme->getNom()), 'sas')) {
                $sas = $forme;
            }
        }

        $formeParDefaut = $sarl ?? $sas ?? $formesJuridiques[0];

        // Données clients d'exemple basées sur les divisions administratives existantes
        $clientsExemple = [
            // Secteur Centre-ville (grandes métropoles)
            [
                'code_postal' => '75001',
                'nom_entreprise' => 'TechParis Solutions',
                'contact_nom' => 'Dubois',
                'contact_prenom' => 'Pierre',
                'telephone' => '01.42.33.44.55',
                'email' => 'p.dubois@techparis.fr'
            ],
            [
                'code_postal' => '69001',
                'nom_entreprise' => 'Lyon Innovation',
                'contact_nom' => 'Lambert',
                'contact_prenom' => 'Sophie',
                'telephone' => '04.78.12.34.56',
                'email' => 's.lambert@lyon-innovation.com'
            ],
            [
                'code_postal' => '31000',
                'nom_entreprise' => 'Toulouse Aerospace',
                'contact_nom' => 'Moreau',
                'contact_prenom' => 'Vincent',
                'telephone' => '05.61.98.76.54',
                'email' => 'v.moreau@toulouse-aero.fr'
            ],

            // Secteur Zone industrielle
            [
                'code_postal' => '92100',
                'nom_entreprise' => 'Boulogne Industries',
                'contact_nom' => 'Rousseau',
                'contact_prenom' => 'Isabelle',
                'telephone' => '01.46.89.12.34',
                'email' => 'i.rousseau@boulogne-industries.fr'
            ],
            [
                'code_postal' => '77100',
                'nom_entreprise' => 'Meaux Logistique',
                'contact_nom' => 'Bernard',
                'contact_prenom' => 'Marc',
                'telephone' => '01.60.45.67.89',
                'email' => 'm.bernard@meaux-logistique.com'
            ],

            // Secteur Tanguy (ouest)
            [
                'code_postal' => '35000',
                'nom_entreprise' => 'Rennes Digital',
                'contact_nom' => 'Petit',
                'contact_prenom' => 'Céline',
                'telephone' => '02.99.87.65.43',
                'email' => 'c.petit@rennes-digital.fr'
            ],
            [
                'code_postal' => '44000',
                'nom_entreprise' => 'Nantes Maritime',
                'contact_nom' => 'Robert',
                'contact_prenom' => 'Julien',
                'telephone' => '02.40.56.78.90',
                'email' => 'j.robert@nantes-maritime.com'
            ],
            [
                'code_postal' => '76000',
                'nom_entreprise' => 'Rouen Export',
                'contact_nom' => 'Simon',
                'contact_prenom' => 'Nathalie',
                'telephone' => '02.35.23.45.67',
                'email' => 'n.simon@rouen-export.fr'
            ]
        ];

        $stats = ['created' => 0, 'assigned' => 0, 'errors' => 0];

        foreach ($clientsExemple as $clientData) {
            try {
                // Vérifier si le client existe déjà
                $existant = $this->entityManager->getRepository(Client::class)
                    ->findOneBy(['nomEntreprise' => $clientData['nom_entreprise']]);

                if ($existant) {
                    $io->note("Client '{$clientData['nom_entreprise']}' déjà existant, ignoré");
                    continue;
                }

                // Trouver la division administrative correspondante
                $division = $this->entityManager->getRepository(DivisionAdministrative::class)
                    ->findOneBy(['codePostal' => $clientData['code_postal']]);

                if (!$division) {
                    $io->warning("Division non trouvée pour " . $clientData['code_postal']);
                    $stats['errors']++;
                    continue;
                }

                // Trouver le secteur qui couvre cette division
                $secteur = $this->entityManager->getRepository(Secteur::class)
                    ->findCouvrantDivision($division);

                // Créer le client
                $client = new Client();
                // Générer un code client unique
                $codeClient = 'CLI' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                $client->setCode($codeClient)
                      ->setNomEntreprise($clientData['nom_entreprise'])
                      ->setFormeJuridique($formeParDefaut)
                      ->setStatut('client') // Client, pas prospect
                      ->setDateConversionClient(new \DateTimeImmutable());

                if ($secteur) {
                    $client->setSecteur($secteur);
                    $stats['assigned']++;
                }

                // Créer le contact principal
                $contact = new Contact();
                $contact->setNom($clientData['contact_nom'])
                       ->setPrenom($clientData['contact_prenom'])
                       ->setTelephone($clientData['telephone'])
                       ->setEmail($clientData['email'])
                       ->setClient($client);

                // Créer l'adresse
                $adresse = new Adresse();
                $adresse->setNom('Siège social')
                       ->setLigne1('1 rue de l\'Exemple')
                       ->setVille($division->getNomCommune())
                       ->setCodePostal($division->getCodePostal())
                       ->setPays('France')
                       ->setClient($client);

                $this->entityManager->persist($client);
                $this->entityManager->persist($contact);
                $this->entityManager->persist($adresse);

                $stats['created']++;

                $secteurInfo = $secteur ? $secteur->getNomSecteur() : 'Non assigné';
                $io->writeln("✓ {$clientData['nom_entreprise']} ({$division->getCodePostal()} {$division->getNomCommune()}) → $secteurInfo");

            } catch (\Exception $e) {
                $io->error("Erreur pour {$clientData['nom_entreprise']}: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        try {
            $this->entityManager->flush();
            $io->success('Clients d\'exemple créés avec succès !');
        } catch (\Exception $e) {
            $io->error('Erreur lors de la sauvegarde: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Statistiques
        $io->section('Résultats');
        $io->table(
            ['Métrique', 'Nombre'],
            [
                ['Clients créés', $stats['created']],
                ['Clients assignés automatiquement', $stats['assigned']],
                ['Erreurs', $stats['errors']]
            ]
        );

        // Affichage par secteur
        $io->section('Répartition des clients par secteur');
        $secteurs = $this->entityManager->getRepository(Secteur::class)->findAll();
        
        foreach ($secteurs as $secteur) {
            $nbClients = count($secteur->getClients());
            $commercial = $secteur->getCommercial();
            $io->writeln("<info>{$secteur->getNomSecteur()}</info> ({$commercial->getNom()} {$commercial->getPrenom()}) : <comment>$nbClients client(s)</comment>");
            
            foreach ($secteur->getClients() as $client) {
                $io->writeln("  → {$client->getNomEntreprise()}");
            }
            $io->newLine();
        }

        return Command::SUCCESS;
    }
}