<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250719071241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE secteur_zone_new (secteur_id INT NOT NULL, zone_id INT NOT NULL, PRIMARY KEY(secteur_id, zone_id))');
        $this->addSql('CREATE INDEX IDX_A500907D9F7E4405 ON secteur_zone_new (secteur_id)');
        $this->addSql('CREATE INDEX IDX_A500907D9F2C3FAB ON secteur_zone_new (zone_id)');
        $this->addSql('CREATE TABLE zone (id SERIAL NOT NULL, code_postal VARCHAR(5) NOT NULL, ville VARCHAR(100) NOT NULL, departement VARCHAR(100) DEFAULT NULL, region VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE secteur_zone_new ADD CONSTRAINT FK_A500907D9F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE secteur_zone_new ADD CONSTRAINT FK_A500907D9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE secteur_zone_new DROP CONSTRAINT FK_A500907D9F7E4405');
        $this->addSql('ALTER TABLE secteur_zone_new DROP CONSTRAINT FK_A500907D9F2C3FAB');
        $this->addSql('DROP TABLE secteur_zone_new');
        $this->addSql('DROP TABLE zone');
    }
}
