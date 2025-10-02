<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002070107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add configuration JSON field to alerte_type table for configurable alert detectors';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Only adding the configuration field to alerte_type
        $this->addSql('ALTER TABLE alerte_type ADD configuration JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Only removing the configuration field from alerte_type
        $this->addSql('ALTER TABLE alerte_type DROP configuration');
    }
}
