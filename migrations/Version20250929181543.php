<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929181543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alerte_instance (id SERIAL NOT NULL, alerte_type_id INT NOT NULL, societe_id INT DEFAULT NULL, resolved_by_id INT DEFAULT NULL, entity_type VARCHAR(255) NOT NULL, entity_id INT NOT NULL, date_detection TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, resolved BOOLEAN NOT NULL, date_resolution TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, commentaire TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A25FFB9B7FEFD799 ON alerte_instance (alerte_type_id)');
        $this->addSql('CREATE INDEX IDX_A25FFB9B6713A32B ON alerte_instance (resolved_by_id)');
        $this->addSql('CREATE INDEX idx_entity ON alerte_instance (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_societe ON alerte_instance (societe_id)');
        $this->addSql('CREATE INDEX idx_resolved ON alerte_instance (resolved)');
        $this->addSql('COMMENT ON COLUMN alerte_instance.date_detection IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN alerte_instance.date_resolution IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE alerte_type (id SERIAL NOT NULL, nom VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, roles_cibles JSON DEFAULT NULL, societes_cibles JSON DEFAULT NULL, classe_detection VARCHAR(255) NOT NULL, actif BOOLEAN NOT NULL, ordre INT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, severity VARCHAR(50) DEFAULT \'warning\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN alerte_type.date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN alerte_type.date_modification IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE alerte_instance ADD CONSTRAINT FK_A25FFB9B7FEFD799 FOREIGN KEY (alerte_type_id) REFERENCES alerte_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alerte_instance ADD CONSTRAINT FK_A25FFB9BFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alerte_instance ADD CONSTRAINT FK_A25FFB9B6713A32B FOREIGN KEY (resolved_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE alerte_instance DROP CONSTRAINT FK_A25FFB9B7FEFD799');
        $this->addSql('ALTER TABLE alerte_instance DROP CONSTRAINT FK_A25FFB9BFCF77503');
        $this->addSql('ALTER TABLE alerte_instance DROP CONSTRAINT FK_A25FFB9B6713A32B');
        $this->addSql('DROP TABLE alerte_instance');
        $this->addSql('DROP TABLE alerte_type');
        $this->addSql('ALTER TABLE devis ADD current_version_created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN devis.current_version_created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b47625e59 FOREIGN KEY (current_version_created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8b27c52b47625e59 ON devis (current_version_created_by_id)');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
    }
}
