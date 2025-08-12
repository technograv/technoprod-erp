<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250801061037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attribution_secteur (id SERIAL NOT NULL, secteur_id INT NOT NULL, division_administrative_id INT NOT NULL, valeur_critere VARCHAR(50) NOT NULL, type_critere VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3C4F35EA9F7E4405 ON attribution_secteur (secteur_id)');
        $this->addSql('CREATE INDEX IDX_3C4F35EA488FE854 ON attribution_secteur (division_administrative_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_secteur_division ON attribution_secteur (secteur_id, division_administrative_id)');
        $this->addSql('COMMENT ON COLUMN attribution_secteur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN attribution_secteur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE division_administrative (id SERIAL NOT NULL, code_postal VARCHAR(5) NOT NULL, code_insee_commune VARCHAR(5) NOT NULL, nom_commune VARCHAR(150) NOT NULL, code_canton VARCHAR(10) DEFAULT NULL, nom_canton VARCHAR(150) DEFAULT NULL, code_epci VARCHAR(10) DEFAULT NULL, nom_epci VARCHAR(200) DEFAULT NULL, type_epci VARCHAR(50) DEFAULT NULL, code_departement VARCHAR(3) NOT NULL, nom_departement VARCHAR(100) NOT NULL, code_region VARCHAR(2) NOT NULL, nom_region VARCHAR(100) NOT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, population INT DEFAULT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_div_admin_code_postal ON division_administrative (code_postal)');
        $this->addSql('CREATE INDEX idx_div_admin_insee_commune ON division_administrative (code_insee_commune)');
        $this->addSql('CREATE INDEX idx_div_admin_departement ON division_administrative (code_departement)');
        $this->addSql('COMMENT ON COLUMN division_administrative.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN division_administrative.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE type_secteur (id SERIAL NOT NULL, code VARCHAR(50) NOT NULL, nom VARCHAR(100) NOT NULL, type VARCHAR(20) NOT NULL, description TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A8644AEA77153098 ON type_secteur (code)');
        $this->addSql('COMMENT ON COLUMN type_secteur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN type_secteur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE attribution_secteur ADD CONSTRAINT FK_3C4F35EA9F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE attribution_secteur ADD CONSTRAINT FK_3C4F35EA488FE854 FOREIGN KEY (division_administrative_id) REFERENCES division_administrative (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE secteur ADD type_secteur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE secteur ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE secteur ADD CONSTRAINT FK_8045251F827D9220 FOREIGN KEY (type_secteur_id) REFERENCES type_secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8045251F827D9220 ON secteur (type_secteur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE secteur DROP CONSTRAINT FK_8045251F827D9220');
        $this->addSql('ALTER TABLE attribution_secteur DROP CONSTRAINT FK_3C4F35EA9F7E4405');
        $this->addSql('ALTER TABLE attribution_secteur DROP CONSTRAINT FK_3C4F35EA488FE854');
        $this->addSql('DROP TABLE attribution_secteur');
        $this->addSql('DROP TABLE division_administrative');
        $this->addSql('DROP TABLE type_secteur');
        $this->addSql('DROP INDEX IDX_8045251F827D9220');
        $this->addSql('ALTER TABLE secteur DROP type_secteur_id');
        $this->addSql('ALTER TABLE secteur DROP description');
    }
}
