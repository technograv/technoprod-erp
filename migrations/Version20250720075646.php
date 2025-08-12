<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720075646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adresse_facturation (id SERIAL NOT NULL, ligne1 VARCHAR(200) NOT NULL, ligne2 VARCHAR(200) DEFAULT NULL, ligne3 VARCHAR(200) DEFAULT NULL, code_postal VARCHAR(10) NOT NULL, ville VARCHAR(100) NOT NULL, pays VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN adresse_facturation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN adresse_facturation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE adresse_livraison (id SERIAL NOT NULL, identique_facturation BOOLEAN NOT NULL, ligne1 VARCHAR(200) DEFAULT NULL, ligne2 VARCHAR(200) DEFAULT NULL, ligne3 VARCHAR(200) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, pays VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN adresse_livraison.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN adresse_livraison.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE contact_facturation (id SERIAL NOT NULL, civilite VARCHAR(10) DEFAULT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) DEFAULT NULL, fonction VARCHAR(150) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, telephone_mobile VARCHAR(20) DEFAULT NULL, fax VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN contact_facturation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contact_facturation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE contact_livraison (id SERIAL NOT NULL, identique_facturation BOOLEAN NOT NULL, civilite VARCHAR(10) DEFAULT NULL, nom VARCHAR(100) DEFAULT NULL, prenom VARCHAR(100) DEFAULT NULL, fonction VARCHAR(150) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, telephone_mobile VARCHAR(20) DEFAULT NULL, fax VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN contact_livraison.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contact_livraison.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE prospect (id SERIAL NOT NULL, commercial_id INT DEFAULT NULL, secteur_id INT DEFAULT NULL, adresse_facturation_id INT DEFAULT NULL, adresse_livraison_id INT DEFAULT NULL, contact_facturation_id INT DEFAULT NULL, contact_livraison_id INT DEFAULT NULL, code VARCHAR(20) NOT NULL, famille VARCHAR(100) DEFAULT NULL, type_personne VARCHAR(20) NOT NULL, civilite VARCHAR(10) DEFAULT NULL, nom VARCHAR(200) NOT NULL, prenom VARCHAR(100) DEFAULT NULL, statut VARCHAR(20) NOT NULL, regime_comptable VARCHAR(50) DEFAULT NULL, mode_paiement VARCHAR(50) DEFAULT NULL, delai_paiement INT DEFAULT NULL, taux_tva NUMERIC(5, 2) DEFAULT NULL, assujetti_tva BOOLEAN DEFAULT NULL, conditions_tarifs VARCHAR(50) DEFAULT NULL, notes TEXT DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_conversion_client TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7D77153098 ON prospect (code)');
        $this->addSql('CREATE INDEX IDX_C9CE8C7D7854071C ON prospect (commercial_id)');
        $this->addSql('CREATE INDEX IDX_C9CE8C7D9F7E4405 ON prospect (secteur_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7D5BBD1224 ON prospect (adresse_facturation_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7DBE2F0A35 ON prospect (adresse_livraison_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7DA8387C44 ON prospect (contact_facturation_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7DA2E3C911 ON prospect (contact_livraison_id)');
        $this->addSql('COMMENT ON COLUMN prospect.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN prospect.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN prospect.date_conversion_client IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D7854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D9F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D5BBD1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7DBE2F0A35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7DA8387C44 FOREIGN KEY (contact_facturation_id) REFERENCES contact_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7DA2E3C911 FOREIGN KEY (contact_livraison_id) REFERENCES contact_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7D7854071C');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7D9F7E4405');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7D5BBD1224');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7DBE2F0A35');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7DA8387C44');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7DA2E3C911');
        $this->addSql('DROP TABLE adresse_facturation');
        $this->addSql('DROP TABLE adresse_livraison');
        $this->addSql('DROP TABLE contact_facturation');
        $this->addSql('DROP TABLE contact_livraison');
        $this->addSql('DROP TABLE prospect');
    }
}
