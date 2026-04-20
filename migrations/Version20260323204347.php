<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260323204347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add client_access_token field to devis_version for version tracking
        $this->addSql('ALTER TABLE devis_version ADD client_access_token VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove client_access_token field from devis_version
        $this->addSql('ALTER TABLE devis_version DROP client_access_token');
    }
}
