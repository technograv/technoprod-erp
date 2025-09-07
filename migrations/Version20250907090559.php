<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907090559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis_item ALTER remise_percent TYPE NUMERIC(6, 3)');
        $this->addSql('ALTER TABLE devis_item ALTER tva_percent TYPE NUMERIC(6, 3)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis_item ALTER remise_percent TYPE NUMERIC(5, 2)');
        $this->addSql('ALTER TABLE devis_item ALTER tva_percent TYPE NUMERIC(5, 2)');
    }
}
