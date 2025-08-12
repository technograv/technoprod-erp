<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250718143105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adresse (id SERIAL NOT NULL, client_id INT NOT NULL, type_adresse VARCHAR(50) NOT NULL, nom_lieu VARCHAR(150) DEFAULT NULL, adresse_ligne1 VARCHAR(200) NOT NULL, adresse_ligne2 VARCHAR(200) DEFAULT NULL, code_postal VARCHAR(5) NOT NULL, ville VARCHAR(100) NOT NULL, pays VARCHAR(100) NOT NULL, is_defaut BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C35F081619EB6921 ON adresse (client_id)');
        $this->addSql('COMMENT ON COLUMN adresse.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN adresse.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE client (id SERIAL NOT NULL, secteur_id INT NOT NULL, commercial_id INT NOT NULL, nom_entreprise VARCHAR(200) NOT NULL, siret VARCHAR(14) DEFAULT NULL, code_client VARCHAR(20) NOT NULL, is_active BOOLEAN NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455B8C25CF7 ON client (code_client)');
        $this->addSql('CREATE INDEX IDX_C74404559F7E4405 ON client (secteur_id)');
        $this->addSql('CREATE INDEX IDX_C74404557854071C ON client (commercial_id)');
        $this->addSql('COMMENT ON COLUMN client.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE contact (id SERIAL NOT NULL, client_id INT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, fonction VARCHAR(150) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, telephone_mobile VARCHAR(20) DEFAULT NULL, is_defaut BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4C62E63819EB6921 ON contact (client_id)');
        $this->addSql('COMMENT ON COLUMN contact.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contact.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE devis (id SERIAL NOT NULL, client_id INT NOT NULL, contact_id INT NOT NULL, adresse_facturation_id INT NOT NULL, adresse_livraison_id INT NOT NULL, commercial_id INT NOT NULL, numero_devis VARCHAR(20) NOT NULL, date_creation DATE NOT NULL, date_validite DATE NOT NULL, statut VARCHAR(20) NOT NULL, total_ht NUMERIC(10, 2) NOT NULL, total_tva NUMERIC(10, 2) NOT NULL, total_ttc NUMERIC(10, 2) NOT NULL, remise_globale_percent NUMERIC(5, 2) DEFAULT NULL, remise_globale_montant NUMERIC(10, 2) DEFAULT NULL, notes_internes TEXT DEFAULT NULL, notes_client TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B27C52B2478EE16 ON devis (numero_devis)');
        $this->addSql('CREATE INDEX IDX_8B27C52B19EB6921 ON devis (client_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52BE7A1254A ON devis (contact_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52B5BBD1224 ON devis (adresse_facturation_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52BBE2F0A35 ON devis (adresse_livraison_id)');
        $this->addSql('CREATE INDEX IDX_8B27C52B7854071C ON devis (commercial_id)');
        $this->addSql('COMMENT ON COLUMN devis.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN devis.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE devis_item (id SERIAL NOT NULL, devis_id INT NOT NULL, designation VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, quantite NUMERIC(10, 2) NOT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, remise_percent NUMERIC(5, 2) DEFAULT NULL, remise_montant NUMERIC(10, 2) DEFAULT NULL, total_ligne_ht NUMERIC(10, 2) NOT NULL, tva_percent NUMERIC(5, 2) NOT NULL, ordre_affichage INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_50C944C141DEFADA ON devis_item (devis_id)');
        $this->addSql('COMMENT ON COLUMN devis_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN devis_item.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE secteur (id SERIAL NOT NULL, commercial_id INT NOT NULL, nom_secteur VARCHAR(150) NOT NULL, couleur_hex VARCHAR(7) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8045251F7854071C ON secteur (commercial_id)');
        $this->addSql('COMMENT ON COLUMN secteur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN secteur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE secteur_zone (id SERIAL NOT NULL, secteur_id INT NOT NULL, code_postal VARCHAR(5) NOT NULL, ville VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C54038239F7E4405 ON secteur_zone (secteur_id)');
        $this->addSql('COMMENT ON COLUMN secteur_zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE adresse ADD CONSTRAINT FK_C35F081619EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C74404559F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C74404557854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63819EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B5BBD1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BBE2F0A35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B7854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_item ADD CONSTRAINT FK_50C944C141DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE secteur ADD CONSTRAINT FK_8045251F7854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE secteur_zone ADD CONSTRAINT FK_C54038239F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE adresse DROP CONSTRAINT FK_C35F081619EB6921');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C74404559F7E4405');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C74404557854071C');
        $this->addSql('ALTER TABLE contact DROP CONSTRAINT FK_4C62E63819EB6921');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B19EB6921');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BE7A1254A');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B5BBD1224');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BBE2F0A35');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B7854071C');
        $this->addSql('ALTER TABLE devis_item DROP CONSTRAINT FK_50C944C141DEFADA');
        $this->addSql('ALTER TABLE secteur DROP CONSTRAINT FK_8045251F7854071C');
        $this->addSql('ALTER TABLE secteur_zone DROP CONSTRAINT FK_C54038239F7E4405');
        $this->addSql('DROP TABLE adresse');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE devis_item');
        $this->addSql('DROP TABLE secteur');
        $this->addSql('DROP TABLE secteur_zone');
        $this->addSql('DROP TABLE "user"');
    }
}
