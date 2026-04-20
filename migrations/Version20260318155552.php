<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260318155552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_poste_travail DROP CONSTRAINT fk_ebdf57b9febda9b');
        $this->addSql('ALTER TABLE user_poste_travail DROP CONSTRAINT fk_ebdf57ba76ed395');
        $this->addSql('DROP TABLE user_poste_travail');
        $this->addSql('CREATE INDEX idx_entity ON alerte (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_resolved ON alerte (resolved)');
        $this->addSql('ALTER INDEX idx_div_admin_code_postal RENAME TO idx_code_postal');
        $this->addSql('ALTER TABLE frais_generaux ADD periode VARCHAR(7) NOT NULL');
        $this->addSql('ALTER TABLE frais_generaux DROP annee_exercice');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE user_poste_travail (user_id INT NOT NULL, poste_travail_id INT NOT NULL, PRIMARY KEY(user_id, poste_travail_id))');
        $this->addSql('CREATE INDEX idx_ebdf57b9febda9b ON user_poste_travail (poste_travail_id)');
        $this->addSql('CREATE INDEX idx_ebdf57ba76ed395 ON user_poste_travail (user_id)');
        $this->addSql('ALTER TABLE user_poste_travail ADD CONSTRAINT fk_ebdf57b9febda9b FOREIGN KEY (poste_travail_id) REFERENCES poste_travail (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_poste_travail ADD CONSTRAINT fk_ebdf57ba76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_entity');
        $this->addSql('DROP INDEX idx_resolved');
        $this->addSql('ALTER TABLE frais_generaux ADD annee_exercice VARCHAR(4) NOT NULL');
        $this->addSql('ALTER TABLE frais_generaux DROP periode');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
    }
}
