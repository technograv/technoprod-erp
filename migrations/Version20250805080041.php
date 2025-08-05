<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250805080041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exclusion_secteur (id SERIAL NOT NULL, attribution_secteur_id INT NOT NULL, division_administrative_id INT NOT NULL, type_exclusion VARCHAR(20) NOT NULL, valeur_exclusion VARCHAR(50) NOT NULL, motif VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ED4E7D1986660ECD ON exclusion_secteur (attribution_secteur_id)');
        $this->addSql('CREATE INDEX IDX_ED4E7D19488FE854 ON exclusion_secteur (division_administrative_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_exclusion ON exclusion_secteur (attribution_secteur_id, division_administrative_id)');
        $this->addSql('COMMENT ON COLUMN exclusion_secteur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE exclusion_secteur ADD CONSTRAINT FK_ED4E7D1986660ECD FOREIGN KEY (attribution_secteur_id) REFERENCES attribution_secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE exclusion_secteur ADD CONSTRAINT FK_ED4E7D19488FE854 FOREIGN KEY (division_administrative_id) REFERENCES division_administrative (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_div_admin_code_postal RENAME TO idx_code_postal');
        $this->addSql('ALTER INDEX idx_div_admin_insee_commune RENAME TO idx_insee_commune');
        $this->addSql('ALTER INDEX idx_div_admin_departement RENAME TO idx_departement');
        $this->addSql('ALTER TABLE forme_juridique ALTER forme_par_defaut DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE exclusion_secteur DROP CONSTRAINT FK_ED4E7D1986660ECD');
        $this->addSql('ALTER TABLE exclusion_secteur DROP CONSTRAINT FK_ED4E7D19488FE854');
        $this->addSql('DROP TABLE exclusion_secteur');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
        $this->addSql('ALTER TABLE forme_juridique ALTER forme_par_defaut SET DEFAULT false');
    }
}
