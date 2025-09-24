<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924085313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD mode_reglement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455E04B7BE2 FOREIGN KEY (mode_reglement_id) REFERENCES mode_reglement (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C7440455E04B7BE2 ON client (mode_reglement_id)');
        // Skip index renaming to avoid conflicts
        // $this->addSql('ALTER INDEX idx_div_admin_code_postal RENAME TO idx_code_postal');
        // $this->addSql('ALTER INDEX idx_div_admin_insee_commune RENAME TO idx_insee_commune');
        // $this->addSql('ALTER INDEX idx_div_admin_departement RENAME TO idx_departement');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        // Skip index renaming to avoid conflicts
        // $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        // $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        // $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C7440455E04B7BE2');
        $this->addSql('DROP INDEX IDX_C7440455E04B7BE2');
        $this->addSql('ALTER TABLE client DROP mode_reglement_id');
    }
}
