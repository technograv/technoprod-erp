<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326160346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add societe_id field to devis table
        $this->addSql('ALTER TABLE devis ADD societe_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B27C52BFCF77503 ON devis (societe_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove societe_id field from devis table
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BFCF77503');
        $this->addSql('DROP INDEX IDX_8B27C52BFCF77503');
        $this->addSql('ALTER TABLE devis DROP societe_id');
    }
}
