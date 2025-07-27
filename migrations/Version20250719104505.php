<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250719104505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande (id SERIAL NOT NULL, devis_id INT NOT NULL, client_id INT NOT NULL, contact_id INT DEFAULT NULL, commercial_id INT NOT NULL, numero_commande VARCHAR(20) NOT NULL, date_commande DATE NOT NULL, date_livraison_prevue DATE DEFAULT NULL, date_livraison_reelle DATE DEFAULT NULL, statut VARCHAR(30) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, total_tva NUMERIC(10, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, notes_production TEXT DEFAULT NULL, notes_livraison TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67DCFFD611D ON commande (numero_commande)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EEAA67D41DEFADA ON commande (devis_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D19EB6921 ON commande (client_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67DE7A1254A ON commande (contact_id)');
        $this->addSql('CREATE INDEX IDX_6EEAA67D7854071C ON commande (commercial_id)');
        $this->addSql('COMMENT ON COLUMN commande.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN commande.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE commande_item (id SERIAL NOT NULL, commande_id INT NOT NULL, designation VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, quantite NUMERIC(10, 2) NOT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, remise_percent NUMERIC(5, 2) DEFAULT NULL, total_ligne_ht NUMERIC(10, 2) NOT NULL, tva_percent NUMERIC(5, 2) NOT NULL, ordre_affichage INT NOT NULL, statut_production VARCHAR(30) NOT NULL, date_production_prevue DATE DEFAULT NULL, date_production_reelle DATE DEFAULT NULL, notes_production TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_747724FD82EA2E54 ON commande_item (commande_id)');
        $this->addSql('COMMENT ON COLUMN commande_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN commande_item.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE facture (id SERIAL NOT NULL, commande_id INT NOT NULL, client_id INT NOT NULL, contact_id INT DEFAULT NULL, commercial_id INT NOT NULL, numero_facture VARCHAR(20) NOT NULL, date_facture DATE NOT NULL, date_echeance DATE DEFAULT NULL, date_paiement DATE DEFAULT NULL, statut VARCHAR(30) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, total_tva NUMERIC(10, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, montant_paye NUMERIC(10, 2) NOT NULL, montant_restant NUMERIC(10, 2) NOT NULL, mode_paiement VARCHAR(50) DEFAULT NULL, notes_facturation TEXT DEFAULT NULL, notes_comptabilite TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FE86641038D27AB1 ON facture (numero_facture)');
        $this->addSql('CREATE INDEX IDX_FE86641082EA2E54 ON facture (commande_id)');
        $this->addSql('CREATE INDEX IDX_FE86641019EB6921 ON facture (client_id)');
        $this->addSql('CREATE INDEX IDX_FE866410E7A1254A ON facture (contact_id)');
        $this->addSql('CREATE INDEX IDX_FE8664107854071C ON facture (commercial_id)');
        $this->addSql('COMMENT ON COLUMN facture.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN facture.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE facture_item (id SERIAL NOT NULL, facture_id INT NOT NULL, designation VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, quantite NUMERIC(10, 2) NOT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, remise_percent NUMERIC(5, 2) DEFAULT NULL, total_ligne_ht NUMERIC(10, 2) NOT NULL, tva_percent NUMERIC(5, 2) NOT NULL, ordre_affichage INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F91D09D27F2DEE08 ON facture_item (facture_id)');
        $this->addSql('COMMENT ON COLUMN facture_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN facture_item.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D41DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D7854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE commande_item ADD CONSTRAINT FK_747724FD82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641082EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE8664107854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facture_item ADD CONSTRAINT FK_F91D09D27F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE commande DROP CONSTRAINT FK_6EEAA67D41DEFADA');
        $this->addSql('ALTER TABLE commande DROP CONSTRAINT FK_6EEAA67D19EB6921');
        $this->addSql('ALTER TABLE commande DROP CONSTRAINT FK_6EEAA67DE7A1254A');
        $this->addSql('ALTER TABLE commande DROP CONSTRAINT FK_6EEAA67D7854071C');
        $this->addSql('ALTER TABLE commande_item DROP CONSTRAINT FK_747724FD82EA2E54');
        $this->addSql('ALTER TABLE facture DROP CONSTRAINT FK_FE86641082EA2E54');
        $this->addSql('ALTER TABLE facture DROP CONSTRAINT FK_FE86641019EB6921');
        $this->addSql('ALTER TABLE facture DROP CONSTRAINT FK_FE866410E7A1254A');
        $this->addSql('ALTER TABLE facture DROP CONSTRAINT FK_FE8664107854071C');
        $this->addSql('ALTER TABLE facture_item DROP CONSTRAINT FK_F91D09D27F2DEE08');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE commande_item');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE facture_item');
    }
}
