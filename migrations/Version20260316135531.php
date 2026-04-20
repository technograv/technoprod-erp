<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316135531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Création de la table conditions_vente pour gérer les CGV/CPV
        $this->addSql('CREATE TABLE conditions_vente (id SERIAL NOT NULL, societe_id INT DEFAULT NULL, code VARCHAR(10) NOT NULL, nom VARCHAR(100) NOT NULL, contenu TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9D319754FCF77503 ON conditions_vente (societe_id)');
        $this->addSql('COMMENT ON COLUMN conditions_vente.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN conditions_vente.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE conditions_vente ADD CONSTRAINT FK_9D319754FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conditions_vente DROP CONSTRAINT FK_9D319754FCF77503');
        $this->addSql('DROP TABLE conditions_vente');
    }
}
