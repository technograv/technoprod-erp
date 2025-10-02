<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001124330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unification du système d\'alertes - Fusion des alertes manuelles et automatiques';
    }

    public function up(Schema $schema): void
    {
        // Ajout des champs pour unifier les alertes manuelles et automatiques
        $this->addSql('ALTER TABLE alerte ADD resolved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD societes_cibles JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD detector_class VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD entity_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD entity_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD resolved BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE alerte ADD date_resolution TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte ADD commentaire TEXT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN alerte.date_resolution IS \'(DC2Type:datetime_immutable)\'');

        // Contraintes et index
        $this->addSql('ALTER TABLE alerte ADD CONSTRAINT FK_3AE753A6713A32B FOREIGN KEY (resolved_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3AE753A6713A32B ON alerte (resolved_by_id)');
        $this->addSql('CREATE INDEX idx_detector ON alerte (detector_class)');
        $this->addSql('CREATE INDEX idx_entity ON alerte (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_resolved ON alerte (resolved)');
    }

    public function down(Schema $schema): void
    {
        // Retour en arrière - Suppression des champs ajoutés
        $this->addSql('ALTER TABLE alerte DROP CONSTRAINT FK_3AE753A6713A32B');
        $this->addSql('DROP INDEX IDX_3AE753A6713A32B');
        $this->addSql('DROP INDEX idx_detector');
        $this->addSql('DROP INDEX idx_entity');
        $this->addSql('DROP INDEX idx_resolved');
        $this->addSql('ALTER TABLE alerte DROP resolved_by_id');
        $this->addSql('ALTER TABLE alerte DROP societes_cibles');
        $this->addSql('ALTER TABLE alerte DROP detector_class');
        $this->addSql('ALTER TABLE alerte DROP entity_type');
        $this->addSql('ALTER TABLE alerte DROP entity_id');
        $this->addSql('ALTER TABLE alerte DROP metadata');
        $this->addSql('ALTER TABLE alerte DROP resolved');
        $this->addSql('ALTER TABLE alerte DROP date_resolution');
        $this->addSql('ALTER TABLE alerte DROP commentaire');
    }
}
