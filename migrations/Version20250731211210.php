<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731211210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE frais_port (id SERIAL NOT NULL, taux_tva_id INT NOT NULL, transporteur_id INT DEFAULT NULL, code VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, mode_calcul VARCHAR(50) NOT NULL, valeur NUMERIC(10, 2) DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B669FBA477153098 ON frais_port (code)');
        $this->addSql('CREATE INDEX IDX_B669FBA4F7FEBCCE ON frais_port (taux_tva_id)');
        $this->addSql('CREATE INDEX IDX_B669FBA497C86FA4 ON frais_port (transporteur_id)');
        $this->addSql('COMMENT ON COLUMN frais_port.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN frais_port.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE palier_frais_port (id SERIAL NOT NULL, frais_port_id INT NOT NULL, limite_jusqua NUMERIC(12, 3) NOT NULL, valeur NUMERIC(10, 2) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7A3A73A07B16D1B0 ON palier_frais_port (frais_port_id)');
        $this->addSql('COMMENT ON COLUMN palier_frais_port.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN palier_frais_port.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE transporteur (id SERIAL NOT NULL, code VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, contact VARCHAR(100) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, pays VARCHAR(50) DEFAULT NULL, telephone VARCHAR(25) DEFAULT NULL, fax VARCHAR(25) DEFAULT NULL, email VARCHAR(150) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, numero_compte VARCHAR(50) DEFAULT NULL, api_url VARCHAR(100) DEFAULT NULL, api_key VARCHAR(100) DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A256497577153098 ON transporteur (code)');
        $this->addSql('COMMENT ON COLUMN transporteur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN transporteur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE frais_port ADD CONSTRAINT FK_B669FBA4F7FEBCCE FOREIGN KEY (taux_tva_id) REFERENCES taux_tva (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE frais_port ADD CONSTRAINT FK_B669FBA497C86FA4 FOREIGN KEY (transporteur_id) REFERENCES transporteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE palier_frais_port ADD CONSTRAINT FK_7A3A73A07B16D1B0 FOREIGN KEY (frais_port_id) REFERENCES frais_port (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE frais_port DROP CONSTRAINT FK_B669FBA4F7FEBCCE');
        $this->addSql('ALTER TABLE frais_port DROP CONSTRAINT FK_B669FBA497C86FA4');
        $this->addSql('ALTER TABLE palier_frais_port DROP CONSTRAINT FK_7A3A73A07B16D1B0');
        $this->addSql('DROP TABLE frais_port');
        $this->addSql('DROP TABLE palier_frais_port');
        $this->addSql('DROP TABLE transporteur');
    }
}
