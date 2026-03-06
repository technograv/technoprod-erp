<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015140026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE poste_travail ADD unite_capacite_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE poste_travail ALTER capacite_journaliere TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE poste_travail ADD CONSTRAINT FK_E033582BCD74C3FD FOREIGN KEY (unite_capacite_id) REFERENCES unite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E033582BCD74C3FD ON poste_travail (unite_capacite_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE poste_travail DROP CONSTRAINT FK_E033582BCD74C3FD');
        $this->addSql('DROP INDEX IDX_E033582BCD74C3FD');
        $this->addSql('ALTER TABLE poste_travail DROP unite_capacite_id');
        $this->addSql('ALTER TABLE poste_travail ALTER capacite_journaliere TYPE NUMERIC(5, 2)');
    }
}
