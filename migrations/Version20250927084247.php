<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250927084247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52b47625e59');
        $this->addSql('DROP INDEX idx_8b27c52b47625e59');
        $this->addSql('ALTER TABLE devis DROP current_version_created_by_id');
        $this->addSql('ALTER TABLE devis DROP current_version_reason');
        $this->addSql('ALTER TABLE devis DROP current_version_created_at');
        $this->addSql('ALTER INDEX idx_div_admin_code_postal RENAME TO idx_code_postal');
        $this->addSql('ALTER INDEX idx_div_admin_insee_commune RENAME TO idx_insee_commune');
        $this->addSql('ALTER INDEX idx_div_admin_departement RENAME TO idx_departement');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE devis ADD current_version_created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN devis.current_version_created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b47625e59 FOREIGN KEY (current_version_created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8b27c52b47625e59 ON devis (current_version_created_by_id)');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
    }
}
