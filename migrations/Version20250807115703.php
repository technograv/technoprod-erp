<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250807115703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE societe (id SERIAL NOT NULL, societe_parent_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, siret VARCHAR(100) DEFAULT NULL, numero_tva VARCHAR(100) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, pays VARCHAR(100) DEFAULT NULL, telephone VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, couleur_primaire VARCHAR(7) DEFAULT NULL, couleur_secondaire VARCHAR(7) DEFAULT NULL, parametres_custom JSON DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_19653DBDF1DD3BA3 ON societe (societe_parent_id)');
        $this->addSql('COMMENT ON COLUMN societe.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN societe.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE user_societe_role (id SERIAL NOT NULL, user_id INT NOT NULL, societe_id INT NOT NULL, role VARCHAR(50) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, permissions_specifiques JSON DEFAULT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B58ACA79A76ED395 ON user_societe_role (user_id)');
        $this->addSql('CREATE INDEX IDX_B58ACA79FCF77503 ON user_societe_role (societe_id)');
        $this->addSql('CREATE UNIQUE INDEX user_societe_unique ON user_societe_role (user_id, societe_id)');
        $this->addSql('COMMENT ON COLUMN user_societe_role.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_societe_role.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE societe ADD CONSTRAINT FK_19653DBDF1DD3BA3 FOREIGN KEY (societe_parent_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_societe_role ADD CONSTRAINT FK_B58ACA79A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_societe_role ADD CONSTRAINT FK_B58ACA79FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE societe DROP CONSTRAINT FK_19653DBDF1DD3BA3');
        $this->addSql('ALTER TABLE user_societe_role DROP CONSTRAINT FK_B58ACA79A76ED395');
        $this->addSql('ALTER TABLE user_societe_role DROP CONSTRAINT FK_B58ACA79FCF77503');
        $this->addSql('DROP TABLE societe');
        $this->addSql('DROP TABLE user_societe_role');
    }
}
