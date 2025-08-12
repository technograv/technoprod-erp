<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250803135809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commune_geometry_cache (id SERIAL NOT NULL, code_insee VARCHAR(5) NOT NULL, nom_commune VARCHAR(255) NOT NULL, geometry_data JSON NOT NULL, points_count INT NOT NULL, source VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_updated TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_valid BOOLEAN NOT NULL, error_message TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F3E787DB1649A761 ON commune_geometry_cache (code_insee)');
        $this->addSql('CREATE INDEX idx_code_insee ON commune_geometry_cache (code_insee)');
        $this->addSql('CREATE INDEX idx_last_updated ON commune_geometry_cache (last_updated)');
        $this->addSql('COMMENT ON COLUMN commune_geometry_cache.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN commune_geometry_cache.last_updated IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE commune_geometry_cache');
    }
}
