<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250925141019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add metadata fields for current version (reason, created_by, created_at) to devis table';
    }

    public function up(Schema $schema): void
    {
        // Add current version metadata fields to devis table
        $this->addSql('ALTER TABLE devis ADD current_version_created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN devis.current_version_created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B47625E59 FOREIGN KEY (current_version_created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B27C52B47625E59 ON devis (current_version_created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove current version metadata fields from devis table
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B47625E59');
        $this->addSql('DROP INDEX IDX_8B27C52B47625E59');
        $this->addSql('ALTER TABLE devis DROP current_version_created_by_id');
        $this->addSql('ALTER TABLE devis DROP current_version_reason');
        $this->addSql('ALTER TABLE devis DROP current_version_created_at');
    }
}
