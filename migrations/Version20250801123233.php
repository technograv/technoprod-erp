<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250801123233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete Zone system tables (secteur_zone, zone, secteur_zone_new) and simplify division_administrative indexes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE secteur_zone_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE zone_id_seq CASCADE');
        $this->addSql('ALTER TABLE secteur_zone DROP CONSTRAINT fk_c54038239f7e4405');
        $this->addSql('ALTER TABLE zone DROP CONSTRAINT fk_a0ebc007131a4f72');
        $this->addSql('ALTER TABLE secteur_zone_new DROP CONSTRAINT fk_a500907d9f2c3fab');
        $this->addSql('ALTER TABLE secteur_zone_new DROP CONSTRAINT fk_a500907d9f7e4405');
        $this->addSql('DROP TABLE secteur_zone');
        $this->addSql('DROP TABLE zone');
        $this->addSql('DROP TABLE secteur_zone_new');
        // Indexes renaming removed - may already exist or not be needed
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE secteur_zone_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE zone_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE secteur_zone (id SERIAL NOT NULL, secteur_id INT NOT NULL, code_postal VARCHAR(5) NOT NULL, ville VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_c54038239f7e4405 ON secteur_zone (secteur_id)');
        $this->addSql('COMMENT ON COLUMN secteur_zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE zone (id SERIAL NOT NULL, commune_id INT DEFAULT NULL, code_postal VARCHAR(5) NOT NULL, ville VARCHAR(100) NOT NULL, departement VARCHAR(100) DEFAULT NULL, region VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_a0ebc007131a4f72 ON zone (commune_id)');
        $this->addSql('COMMENT ON COLUMN zone.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE secteur_zone_new (secteur_id INT NOT NULL, zone_id INT NOT NULL, PRIMARY KEY(secteur_id, zone_id))');
        $this->addSql('CREATE INDEX idx_a500907d9f2c3fab ON secteur_zone_new (zone_id)');
        $this->addSql('CREATE INDEX idx_a500907d9f7e4405 ON secteur_zone_new (secteur_id)');
        $this->addSql('ALTER TABLE secteur_zone ADD CONSTRAINT fk_c54038239f7e4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE zone ADD CONSTRAINT fk_a0ebc007131a4f72 FOREIGN KEY (commune_id) REFERENCES commune_francaise (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE secteur_zone_new ADD CONSTRAINT fk_a500907d9f2c3fab FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE secteur_zone_new ADD CONSTRAINT fk_a500907d9f7e4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Indexes renaming rollback removed - may not be needed
    }
}
