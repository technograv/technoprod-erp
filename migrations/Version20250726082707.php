<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726082707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adresse DROP CONSTRAINT fk_c35f0816e7a1254a');
        $this->addSql('DROP INDEX idx_c35f0816e7a1254a');
        $this->addSql('ALTER TABLE adresse RENAME COLUMN contact_id TO client_id');
        $this->addSql('ALTER TABLE adresse ADD CONSTRAINT FK_C35F081619EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C35F081619EB6921 ON adresse (client_id)');
        $this->addSql('ALTER TABLE contact ADD adresse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E6384DE7DC5C FOREIGN KEY (adresse_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4C62E6384DE7DC5C ON contact (adresse_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE adresse DROP CONSTRAINT FK_C35F081619EB6921');
        $this->addSql('DROP INDEX IDX_C35F081619EB6921');
        $this->addSql('ALTER TABLE adresse RENAME COLUMN client_id TO contact_id');
        $this->addSql('ALTER TABLE adresse ADD CONSTRAINT fk_c35f0816e7a1254a FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c35f0816e7a1254a ON adresse (contact_id)');
        $this->addSql('ALTER TABLE contact DROP CONSTRAINT FK_4C62E6384DE7DC5C');
        $this->addSql('DROP INDEX IDX_4C62E6384DE7DC5C');
        $this->addSql('ALTER TABLE contact DROP adresse_id');
    }
}
