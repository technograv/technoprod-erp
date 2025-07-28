<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250727165658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD forme_juridique_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client DROP forme_juridique');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C74404559AEE68EB FOREIGN KEY (forme_juridique_id) REFERENCES forme_juridique (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C74404559AEE68EB ON client (forme_juridique_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C74404559AEE68EB');
        $this->addSql('DROP INDEX IDX_C74404559AEE68EB');
        $this->addSql('ALTER TABLE client ADD forme_juridique VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE client DROP forme_juridique_id');
    }
}
