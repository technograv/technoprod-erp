<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924182943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Migration pour ajouter la relation ModeReglement au devis
        $this->addSql('ALTER TABLE devis ADD mode_reglement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis DROP COLUMN tiers_mode_reglement');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BE04B7BE2 FOREIGN KEY (mode_reglement_id) REFERENCES mode_reglement (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B27C52BE04B7BE2 ON devis (mode_reglement_id)');
    }

    public function down(Schema $schema): void
    {
        // Rollback de la relation ModeReglement
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BE04B7BE2');
        $this->addSql('DROP INDEX IDX_8B27C52BE04B7BE2');
        $this->addSql('ALTER TABLE devis ADD tiers_mode_reglement VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis DROP COLUMN mode_reglement_id');
    }
}
