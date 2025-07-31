<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730221944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE consent (id SERIAL NOT NULL, user_id INT NOT NULL, purpose VARCHAR(100) NOT NULL, granted BOOLEAN NOT NULL, granted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, withdrawn_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, legal_basis TEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_63120810A76ED395 ON consent (user_id)');
        $this->addSql('COMMENT ON COLUMN consent.granted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consent.withdrawn_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consent.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consent.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE consent ADD CONSTRAINT FK_63120810A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE consent DROP CONSTRAINT FK_63120810A76ED395');
        $this->addSql('DROP TABLE consent');
    }
}
