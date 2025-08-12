<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720173431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52b19eb6921');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52be7a1254a');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B5BBD1224');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BBE2F0A35');
        $this->addSql('DROP INDEX idx_8b27c52b19eb6921');
        $this->addSql('DROP INDEX idx_8b27c52be7a1254a');
        $this->addSql('ALTER TABLE devis ADD contact_livraison_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD acompte_percent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD acompte_montant NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD date_envoi DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD date_signature DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD signature_nom VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD signature_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD signature_data TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD date_paiement_acompte DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD transaction_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD mode_paiement VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD url_acces_client VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis RENAME COLUMN client_id TO prospect_id');
        $this->addSql('ALTER TABLE devis RENAME COLUMN contact_id TO contact_facturation_id');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BD182060A FOREIGN KEY (prospect_id) REFERENCES prospect (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BA8387C44 FOREIGN KEY (contact_facturation_id) REFERENCES contact_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BA2E3C911 FOREIGN KEY (contact_livraison_id) REFERENCES contact_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B5BBD1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BBE2F0A35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B27C52BD182060A ON devis (prospect_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52BA8387C44 ON devis (contact_facturation_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52BA2E3C911 ON devis (contact_livraison_id)');
        $this->addSql('ALTER TABLE devis_item ADD produit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis_item ADD CONSTRAINT FK_50C944C1F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_50C944C1F347EFB ON devis_item (produit_id)');
        $this->addSql('ALTER TABLE produit ADD type VARCHAR(20) DEFAULT \'produit\'');
        $this->addSql('UPDATE produit SET type = \'produit\' WHERE type IS NULL');
        $this->addSql('ALTER TABLE produit ALTER COLUMN type SET NOT NULL');
        $this->addSql('ALTER TABLE produit ADD prix_vente_ht NUMERIC(10, 2) DEFAULT \'0.00\'');
        $this->addSql('UPDATE produit SET prix_vente_ht = \'0.00\' WHERE prix_vente_ht IS NULL');
        $this->addSql('ALTER TABLE produit ALTER COLUMN prix_vente_ht SET NOT NULL');
        $this->addSql('ALTER TABLE produit ADD marge_percent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD stock_quantite INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD stock_minimum INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD gestion_stock BOOLEAN DEFAULT false');
        $this->addSql('UPDATE produit SET gestion_stock = false WHERE gestion_stock IS NULL');
        $this->addSql('ALTER TABLE produit ALTER COLUMN gestion_stock SET NOT NULL');
        $this->addSql('ALTER TABLE produit ADD image VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD notes_internes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ALTER reference SET NOT NULL');
        $this->addSql('ALTER TABLE produit RENAME COLUMN prix_unitaire_ht TO prix_achat_ht');
        $this->addSql('ALTER TABLE produit RENAME COLUMN is_active TO actif');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_29A5EC27AEA34913 ON produit (reference)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BD182060A');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BA8387C44');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BA2E3C911');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52b5bbd1224');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52bbe2f0a35');
        $this->addSql('DROP INDEX IDX_8B27C52BD182060A');
        $this->addSql('DROP INDEX IDX_8B27C52BA8387C44');
        $this->addSql('DROP INDEX IDX_8B27C52BA2E3C911');
        $this->addSql('ALTER TABLE devis ADD contact_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis DROP contact_facturation_id');
        $this->addSql('ALTER TABLE devis DROP contact_livraison_id');
        $this->addSql('ALTER TABLE devis DROP acompte_percent');
        $this->addSql('ALTER TABLE devis DROP acompte_montant');
        $this->addSql('ALTER TABLE devis DROP date_envoi');
        $this->addSql('ALTER TABLE devis DROP date_signature');
        $this->addSql('ALTER TABLE devis DROP signature_nom');
        $this->addSql('ALTER TABLE devis DROP signature_email');
        $this->addSql('ALTER TABLE devis DROP signature_data');
        $this->addSql('ALTER TABLE devis DROP date_paiement_acompte');
        $this->addSql('ALTER TABLE devis DROP transaction_id');
        $this->addSql('ALTER TABLE devis DROP mode_paiement');
        $this->addSql('ALTER TABLE devis DROP url_acces_client');
        $this->addSql('ALTER TABLE devis RENAME COLUMN prospect_id TO client_id');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b19eb6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52be7a1254a FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b5bbd1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52bbe2f0a35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8b27c52b19eb6921 ON devis (client_id)');
        $this->addSql('CREATE INDEX idx_8b27c52be7a1254a ON devis (contact_id)');
        $this->addSql('DROP INDEX UNIQ_29A5EC27AEA34913');
        $this->addSql('ALTER TABLE produit ADD prix_unitaire_ht NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE produit ADD is_active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE produit DROP type');
        $this->addSql('ALTER TABLE produit DROP prix_achat_ht');
        $this->addSql('ALTER TABLE produit DROP prix_vente_ht');
        $this->addSql('ALTER TABLE produit DROP marge_percent');
        $this->addSql('ALTER TABLE produit DROP stock_quantite');
        $this->addSql('ALTER TABLE produit DROP stock_minimum');
        $this->addSql('ALTER TABLE produit DROP actif');
        $this->addSql('ALTER TABLE produit DROP gestion_stock');
        $this->addSql('ALTER TABLE produit DROP image');
        $this->addSql('ALTER TABLE produit DROP notes_internes');
        $this->addSql('ALTER TABLE produit ALTER reference DROP NOT NULL');
        $this->addSql('ALTER TABLE devis_item DROP CONSTRAINT FK_50C944C1F347EFB');
        $this->addSql('DROP INDEX IDX_50C944C1F347EFB');
        $this->addSql('ALTER TABLE devis_item DROP produit_id');
    }
}
