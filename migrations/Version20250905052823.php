<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250905052823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE devis_version (id SERIAL NOT NULL, devis_id INT NOT NULL, modified_by_id INT NOT NULL, version_number INT NOT NULL, snapshot_data JSON NOT NULL, modification_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, version_label VARCHAR(255) DEFAULT NULL, total_ttc_at_time NUMERIC(10, 2) NOT NULL, statut_at_time VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2DF2489F41DEFADA ON devis_version (devis_id)');
        $this->addSql('CREATE INDEX IDX_2DF2489F99049ECE ON devis_version (modified_by_id)');
        $this->addSql('COMMENT ON COLUMN devis_version.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis_version ADD CONSTRAINT FK_2DF2489F41DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_version ADD CONSTRAINT FK_2DF2489F99049ECE FOREIGN KEY (modified_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis_version DROP CONSTRAINT FK_2DF2489F41DEFADA');
        $this->addSql('ALTER TABLE devis_version DROP CONSTRAINT FK_2DF2489F99049ECE');
        $this->addSql('DROP TABLE devis_version');
    }
}
