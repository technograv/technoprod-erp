<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260327101756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove modele_document table (obsolete, replaced by template entity)';
    }

    public function up(Schema $schema): void
    {
        // Drop modele_document table if exists (obsolete entity replaced by Template)
        $this->addSql('DROP SEQUENCE IF EXISTS modele_document_id_seq CASCADE');
        $this->addSql('DROP TABLE IF EXISTS modele_document CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE modele_document_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE modele_document (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, type_document VARCHAR(50) NOT NULL, template_file VARCHAR(100) DEFAULT NULL, css TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, modele_par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN modele_document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN modele_document.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE user_poste_travail (user_id INT NOT NULL, poste_travail_id INT NOT NULL, PRIMARY KEY(user_id, poste_travail_id))');
        $this->addSql('CREATE INDEX idx_ebdf57b9febda9b ON user_poste_travail (poste_travail_id)');
        $this->addSql('CREATE INDEX idx_ebdf57ba76ed395 ON user_poste_travail (user_id)');
        $this->addSql('ALTER TABLE user_poste_travail ADD CONSTRAINT fk_ebdf57b9febda9b FOREIGN KEY (poste_travail_id) REFERENCES poste_travail (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_poste_travail ADD CONSTRAINT fk_ebdf57ba76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE frais_generaux ADD annee_exercice VARCHAR(4) NOT NULL');
        $this->addSql('ALTER TABLE frais_generaux DROP periode');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('DROP INDEX idx_entity');
        $this->addSql('DROP INDEX idx_resolved');
    }
}
