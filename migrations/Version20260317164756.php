<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260317164756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajout relation Template sur Devis
        $this->addSql('ALTER TABLE devis ADD template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B5DA0FB8 FOREIGN KEY (template_id) REFERENCES template (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B27C52B5DA0FB8 ON devis (template_id)');
    }

    public function down(Schema $schema): void
    {
        // Rollback relation Template sur Devis
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B5DA0FB8');
        $this->addSql('DROP INDEX IDX_8B27C52B5DA0FB8');
        $this->addSql('ALTER TABLE devis DROP template_id');
    }
}
