<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250807215156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_template (id SERIAL NOT NULL, societe_id INT DEFAULT NULL, type_document VARCHAR(50) NOT NULL, nom VARCHAR(100) NOT NULL, chemin_fichier VARCHAR(255) NOT NULL, description VARCHAR(50) DEFAULT NULL, est_actif BOOLEAN NOT NULL, est_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_18A1EEDAFCF77503 ON document_template (societe_id)');
        $this->addSql('COMMENT ON COLUMN document_template.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN document_template.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE document_template ADD CONSTRAINT FK_18A1EEDAFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_template DROP CONSTRAINT FK_18A1EEDAFCF77503');
        $this->addSql('DROP TABLE document_template');
    }
}
