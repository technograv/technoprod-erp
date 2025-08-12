<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726165047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commune_francaise (id SERIAL NOT NULL, code_postal VARCHAR(5) NOT NULL, nom_commune VARCHAR(100) NOT NULL, code_departement VARCHAR(50) DEFAULT NULL, nom_departement VARCHAR(100) DEFAULT NULL, code_region VARCHAR(50) DEFAULT NULL, nom_region VARCHAR(100) DEFAULT NULL, population INT DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_code_postal ON commune_francaise (code_postal)');
        $this->addSql('CREATE INDEX idx_nom_commune ON commune_francaise (nom_commune)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE commune_francaise');
    }
}
