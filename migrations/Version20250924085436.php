<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924085436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Skip renaming to avoid conflicts - the indexes might already exist with new names
        // $this->addSql('ALTER INDEX idx_div_admin_code_postal RENAME TO idx_code_postal');
        // $this->addSql('ALTER INDEX idx_div_admin_insee_commune RENAME TO idx_insee_commune');
        // $this->addSql('ALTER INDEX idx_div_admin_departement RENAME TO idx_departement');
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
