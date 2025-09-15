<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915131834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE devis_log (id SERIAL NOT NULL, devis_id INT NOT NULL, user_id INT DEFAULT NULL, action VARCHAR(100) NOT NULL, details TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_82ACE56641DEFADA ON devis_log (devis_id)');
        $this->addSql('CREATE INDEX IDX_82ACE566A76ED395 ON devis_log (user_id)');
        $this->addSql('COMMENT ON COLUMN devis_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis_log ADD CONSTRAINT FK_82ACE56641DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_log ADD CONSTRAINT FK_82ACE566A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis_log DROP CONSTRAINT FK_82ACE56641DEFADA');
        $this->addSql('ALTER TABLE devis_log DROP CONSTRAINT FK_82ACE566A76ED395');
        $this->addSql('DROP TABLE devis_log');
    }
}
