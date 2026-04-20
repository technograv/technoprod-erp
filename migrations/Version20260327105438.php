<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327105438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove duplicate fields (logo, colors) from Template entity - these fields already exist in Societe';
    }

    public function up(Schema $schema): void
    {
        // Only remove duplicate fields from template table
        $this->addSql('ALTER TABLE template DROP COLUMN IF EXISTS couleur_primaire');
        $this->addSql('ALTER TABLE template DROP COLUMN IF EXISTS couleur_secondaire');
        $this->addSql('ALTER TABLE template DROP COLUMN IF EXISTS logo');
    }

    public function down(Schema $schema): void
    {
        // Restore removed columns if needed
        $this->addSql('ALTER TABLE template ADD COLUMN couleur_primaire VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE template ADD COLUMN couleur_secondaire VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE template ADD COLUMN logo VARCHAR(255) DEFAULT NULL');
    }
}
