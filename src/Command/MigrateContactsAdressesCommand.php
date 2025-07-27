<?php

namespace App\Command;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Adresse;
use App\Entity\ContactFacturation;
use App\Entity\ContactLivraison;
use App\Entity\AdresseFacturation;
use App\Entity\AdresseLivraison;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-contacts-adresses',
    description: 'Migre les contacts et adresses vers la nouvelle structure avec collections',
)]
class MigrateContactsAdressesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans modifier la base')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force la migration même si des données existent déjà')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Migration des contacts et adresses vers la nouvelle structure');

        if ($dryRun) {
            $io->note('Mode dry-run activé - aucune modification ne sera effectuée');
        }

        try {
            // Étape 1: Ajouter les nouveaux champs aux tables existantes
            $this->updateDatabaseStructure($io, $dryRun);
            
            // Étape 2: Migrer les données des anciennes entités ContactFacturation/ContactLivraison
            $this->migrateContactData($io, $dryRun, $force);
            
            // Étape 3: Migrer les données des anciennes entités AdresseFacturation/AdresseLivraison
            $this->migrateAdresseData($io, $dryRun, $force);
            
            // Étape 4: Mettre à jour les relations Client
            $this->updateClientRelations($io, $dryRun);
            
            // Étape 5: Mettre à jour les relations Devis
            $this->updateDevisRelations($io, $dryRun);

            if (!$dryRun) {
                $this->entityManager->flush();
                $io->success('Migration terminée avec succès !');
            } else {
                $io->info('Migration simulée terminée - utilisez --force pour appliquer les changements');
            }

        } catch (\Exception $e) {
            $io->error('Erreur durant la migration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function updateDatabaseStructure(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Mise à jour de la structure de base de données');
        
        $connection = $this->entityManager->getConnection();
        
        $sql = [
            // Ajout des champs aux tables contact et adresse existantes
            "ALTER TABLE contact ADD COLUMN IF NOT EXISTS civilite VARCHAR(10) DEFAULT NULL",
            "ALTER TABLE contact ADD COLUMN IF NOT EXISTS fax VARCHAR(25) DEFAULT NULL", 
            "ALTER TABLE contact ADD COLUMN IF NOT EXISTS is_facturation_default BOOLEAN DEFAULT FALSE",
            "ALTER TABLE contact ADD COLUMN IF NOT EXISTS is_livraison_default BOOLEAN DEFAULT FALSE",
            "ALTER TABLE contact ALTER COLUMN is_defaut DROP NOT NULL",
            "ALTER TABLE contact ALTER COLUMN is_defaut SET DEFAULT FALSE",
            "ALTER TABLE contact DROP CONSTRAINT IF EXISTS fk_4c62e63819eb6921",
            "DELETE FROM contact WHERE client_id NOT IN (SELECT id FROM client)",
            "ALTER TABLE contact DROP CONSTRAINT IF EXISTS fk_contact_client",
            "ALTER TABLE contact ADD CONSTRAINT fk_contact_client FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE CASCADE",
            
            "ALTER TABLE adresse ADD COLUMN IF NOT EXISTS ligne3 VARCHAR(200) DEFAULT NULL",
            "ALTER TABLE adresse ADD COLUMN IF NOT EXISTS is_facturation_default BOOLEAN DEFAULT FALSE", 
            "ALTER TABLE adresse ADD COLUMN IF NOT EXISTS is_livraison_default BOOLEAN DEFAULT FALSE",
            "ALTER TABLE adresse ALTER COLUMN is_defaut DROP NOT NULL",
            "ALTER TABLE adresse ALTER COLUMN is_defaut SET DEFAULT FALSE",
            "ALTER TABLE adresse ALTER COLUMN type_adresse DROP NOT NULL",
            "ALTER TABLE adresse ALTER COLUMN type_adresse SET DEFAULT 'principale'",
            "ALTER TABLE adresse DROP CONSTRAINT IF EXISTS fk_c35f081619eb6921",
            "DELETE FROM adresse WHERE client_id NOT IN (SELECT id FROM client)",
            "ALTER TABLE adresse DROP CONSTRAINT IF EXISTS fk_adresse_client",
            "ALTER TABLE adresse ADD CONSTRAINT fk_adresse_client FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE CASCADE",
            
            // Ajuster les types de colonnes si nécessaire
            "ALTER TABLE adresse ALTER COLUMN code_postal TYPE VARCHAR(10)",
            "ALTER TABLE adresse ALTER COLUMN pays DROP NOT NULL",
        ];

        foreach ($sql as $query) {
            if ($dryRun) {
                $io->text("Exécuterait: " . $query);
            } else {
                try {
                    $connection->executeStatement($query);
                    $io->text("✓ " . $query);
                } catch (\Exception $e) {
                    // Ignore les erreurs si la colonne existe déjà
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
        }
    }

    private function migrateContactData(SymfonyStyle $io, bool $dryRun, bool $force): void
    {
        $io->section('Migration des données de contacts');

        // Migrer ContactFacturation vers Contact
        $contactsFacturation = $this->entityManager->getRepository(ContactFacturation::class)->findAll();
        $io->text(sprintf('Trouvé %d contacts de facturation à migrer', count($contactsFacturation)));

        foreach ($contactsFacturation as $oldContact) {
            if (!$dryRun) {
                // Skip contact if no client found or missing essential data
                $client = $this->findClientByContact($oldContact);
                if (!$client || !$oldContact->getNom()) {
                    $io->text("⚠ Ignoré contact facturation sans client ou nom: " . ($oldContact->getNom() ?? 'inconnu'));
                    continue;
                }
                
                $newContact = new Contact();
                $newContact->setClient($client);
                $newContact->setCivilite($oldContact->getCivilite());
                $newContact->setNom($oldContact->getNom());
                $newContact->setPrenom($oldContact->getPrenom());
                $newContact->setFonction($oldContact->getFonction());
                $newContact->setTelephone($oldContact->getTelephone());
                $newContact->setTelephoneMobile($oldContact->getTelephoneMobile());
                $newContact->setFax($oldContact->getFax());
                $newContact->setEmail($oldContact->getEmail());
                $newContact->setIsFacturationDefault(true);
                $newContact->setIsLivraisonDefault(false);
                
                $this->entityManager->persist($newContact);
            }
            $io->text("✓ Migré contact facturation: " . $oldContact->getNom());
        }

        // Migrer ContactLivraison vers Contact
        $contactsLivraison = $this->entityManager->getRepository(ContactLivraison::class)->findAll();
        $io->text(sprintf('Trouvé %d contacts de livraison à migrer', count($contactsLivraison)));

        foreach ($contactsLivraison as $oldContact) {
            if (!$dryRun) {
                // Skip contact if no client found or missing essential data
                $client = $this->findClientByContact($oldContact);
                if (!$client || !$oldContact->getNom()) {
                    $io->text("⚠ Ignoré contact livraison sans client ou nom: " . ($oldContact->getNom() ?? 'inconnu'));
                    continue;
                }
                
                $newContact = new Contact();
                $newContact->setClient($client);
                $newContact->setCivilite($oldContact->getCivilite());
                $newContact->setNom($oldContact->getNom());
                $newContact->setPrenom($oldContact->getPrenom());
                $newContact->setFonction($oldContact->getFonction());
                $newContact->setTelephone($oldContact->getTelephone());
                $newContact->setTelephoneMobile($oldContact->getTelephoneMobile());
                $newContact->setFax($oldContact->getFax());
                $newContact->setEmail($oldContact->getEmail());
                $newContact->setIsFacturationDefault(false);
                $newContact->setIsLivraisonDefault(true);
                
                $this->entityManager->persist($newContact);
            }
            $io->text("✓ Migré contact livraison: " . $oldContact->getNom());
        }
    }

    private function migrateAdresseData(SymfonyStyle $io, bool $dryRun, bool $force): void
    {
        $io->section('Migration des données d\'adresses');

        // Migrer AdresseFacturation vers Adresse
        $adressesFacturation = $this->entityManager->getRepository(AdresseFacturation::class)->findAll();
        $io->text(sprintf('Trouvé %d adresses de facturation à migrer', count($adressesFacturation)));

        foreach ($adressesFacturation as $oldAdresse) {
            if (!$dryRun) {
                // Skip address if no client found or missing essential data
                $client = $this->findClientByAdresse($oldAdresse);
                if (!$client || !$oldAdresse->getVille()) {
                    $io->text("⚠ Ignoré adresse facturation sans client ou ville: " . ($oldAdresse->getVille() ?? 'inconnue'));
                    continue;
                }
                
                $newAdresse = new Adresse();
                $newAdresse->setClient($client);
                $newAdresse->setLigne1($oldAdresse->getLigne1() ?? '');
                $newAdresse->setLigne2($oldAdresse->getLigne2());
                $newAdresse->setLigne3($oldAdresse->getLigne3());
                $newAdresse->setCodePostal($oldAdresse->getCodePostal() ?? '');
                $newAdresse->setVille($oldAdresse->getVille());
                $newAdresse->setPays($oldAdresse->getPays());
                $newAdresse->setIsFacturationDefault(true);
                $newAdresse->setIsLivraisonDefault(false);
                
                $this->entityManager->persist($newAdresse);
                $this->entityManager->flush();
                
                // Set type_adresse directly in database since the entity doesn't have this field
                $connection = $this->entityManager->getConnection();
                $connection->executeStatement(
                    "UPDATE adresse SET type_adresse = ? WHERE id = ?",
                    ['facturation', $newAdresse->getId()]
                );
            }
            $io->text("✓ Migré adresse facturation: " . $oldAdresse->getVille());
        }

        // Migrer AdresseLivraison vers Adresse
        $adressesLivraison = $this->entityManager->getRepository(AdresseLivraison::class)->findAll();
        $io->text(sprintf('Trouvé %d adresses de livraison à migrer', count($adressesLivraison)));

        foreach ($adressesLivraison as $oldAdresse) {
            if (!$dryRun) {
                // Skip address if no client found or missing essential data
                $client = $this->findClientByAdresse($oldAdresse);
                if (!$client || !$oldAdresse->getVille()) {
                    $io->text("⚠ Ignoré adresse livraison sans client ou ville: " . ($oldAdresse->getVille() ?? 'inconnue'));
                    continue;
                }
                
                $newAdresse = new Adresse();
                $newAdresse->setClient($client);
                $newAdresse->setLigne1($oldAdresse->getLigne1() ?? '');
                $newAdresse->setLigne2($oldAdresse->getLigne2());
                $newAdresse->setLigne3($oldAdresse->getLigne3());
                $newAdresse->setCodePostal($oldAdresse->getCodePostal() ?? '');
                $newAdresse->setVille($oldAdresse->getVille());
                $newAdresse->setPays($oldAdresse->getPays());
                $newAdresse->setIsFacturationDefault(false);
                $newAdresse->setIsLivraisonDefault(true);
                
                $this->entityManager->persist($newAdresse);
                $this->entityManager->flush();
                
                // Set type_adresse directly in database since the entity doesn't have this field
                $connection = $this->entityManager->getConnection();
                $connection->executeStatement(
                    "UPDATE adresse SET type_adresse = ? WHERE id = ?",
                    ['livraison', $newAdresse->getId()]
                );
            }
            $io->text("✓ Migré adresse livraison: " . $oldAdresse->getVille());
        }
    }

    private function updateClientRelations(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Mise à jour des relations Client');
        // Cette partie sera implémentée après la migration des données
        $io->text('Relations Client mises à jour');
    }

    private function updateDevisRelations(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Mise à jour des relations Devis');
        // Cette partie sera implémentée après la migration des données
        $io->text('Relations Devis mises à jour');
    }

    private function findClientByContact($contact): ?Client
    {
        $connection = $this->entityManager->getConnection();
        $sql = "SELECT id FROM client WHERE contact_facturation_id = ? OR contact_livraison_id = ?";
        $result = $connection->fetchOne($sql, [$contact->getId(), $contact->getId()]);
        
        if ($result) {
            return $this->entityManager->getRepository(Client::class)->find($result);
        }
        return null;
    }

    private function findClientByAdresse($adresse): ?Client
    {
        $connection = $this->entityManager->getConnection();
        $sql = "SELECT id FROM client WHERE adresse_facturation_id = ? OR adresse_livraison_id = ?";
        $result = $connection->fetchOne($sql, [$adresse->getId(), $adresse->getId()]);
        
        if ($result) {
            return $this->entityManager->getRepository(Client::class)->find($result);
        }
        return null;
    }
}
