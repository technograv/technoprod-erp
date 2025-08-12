<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730063823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE condition_reglement (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, nb_jours INT DEFAULT NULL, actif BOOLEAN NOT NULL, condition_par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN condition_reglement.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN condition_reglement.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE methode_expedition (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, tarif_base NUMERIC(10, 2) DEFAULT NULL, delai_moyen VARCHAR(50) DEFAULT NULL, actif BOOLEAN NOT NULL, methode_par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN methode_expedition.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN methode_expedition.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE mode_reglement (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, type_comptable VARCHAR(50) DEFAULT NULL, actif BOOLEAN NOT NULL, mode_par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN mode_reglement.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN mode_reglement.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE modele_document (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, type_document VARCHAR(50) NOT NULL, template_file VARCHAR(100) DEFAULT NULL, css TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, modele_par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN modele_document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN modele_document.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE forme_juridique ALTER forme_par_defaut DROP DEFAULT');
        $this->addSql('ALTER TABLE forme_juridique ALTER ordre DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE condition_reglement');
        $this->addSql('DROP TABLE methode_expedition');
        $this->addSql('DROP TABLE mode_reglement');
        $this->addSql('DROP TABLE modele_document');
        $this->addSql('ALTER TABLE forme_juridique ALTER forme_par_defaut SET DEFAULT false');
        $this->addSql('ALTER TABLE forme_juridique ALTER ordre SET DEFAULT 0');
    }
}
