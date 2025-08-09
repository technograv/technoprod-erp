<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250809063033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Skip index renames if they already exist to avoid conflicts
        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = \'idx_div_admin_code_postal\') THEN
                ALTER INDEX idx_div_admin_code_postal RENAME TO idx_code_postal;
            END IF;
        END $$');
        
        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = \'idx_div_admin_insee_commune\') THEN
                ALTER INDEX idx_div_admin_insee_commune RENAME TO idx_insee_commune;
            END IF;
        END $$');
        
        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = \'idx_div_admin_departement\') THEN
                ALTER INDEX idx_div_admin_departement RENAME TO idx_departement;
            END IF;
        END $$');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
    }
}
