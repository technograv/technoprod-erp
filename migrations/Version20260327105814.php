<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327105814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove DocumentTemplate entity - unused (single Twig template for PDF generation)';
    }

    public function up(Schema $schema): void
    {
        // Remove DocumentTemplate table and sequence
        $this->addSql('DROP TABLE IF EXISTS document_template CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS document_template_id_seq CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Recreate DocumentTemplate table if rollback needed
        $this->addSql('CREATE SEQUENCE document_template_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE document_template (id SERIAL NOT NULL, societe_id INT DEFAULT NULL, type_document VARCHAR(50) NOT NULL, nom VARCHAR(100) NOT NULL, chemin_fichier VARCHAR(255) NOT NULL, description VARCHAR(50) DEFAULT NULL, est_actif BOOLEAN NOT NULL, est_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_18a1eedafcf77503 ON document_template (societe_id)');
        $this->addSql('COMMENT ON COLUMN document_template.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN document_template.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE document_template ADD CONSTRAINT fk_18a1eedafcf77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
