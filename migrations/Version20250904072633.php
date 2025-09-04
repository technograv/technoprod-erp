<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904072633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alerte (id SERIAL NOT NULL, titre VARCHAR(255) NOT NULL, message TEXT NOT NULL, type VARCHAR(50) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_expiration TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ordre INT NOT NULL, cibles JSON DEFAULT NULL, dismissible BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE alerte_utilisateur (id SERIAL NOT NULL, user_id INT NOT NULL, alerte_id INT NOT NULL, dismissed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BCD6F73EA76ED395 ON alerte_utilisateur (user_id)');
        $this->addSql('CREATE INDEX IDX_BCD6F73E2C9BA629 ON alerte_utilisateur (alerte_id)');
        $this->addSql('ALTER TABLE alerte_utilisateur ADD CONSTRAINT FK_BCD6F73EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alerte_utilisateur ADD CONSTRAINT FK_BCD6F73E2C9BA629 FOREIGN KEY (alerte_id) REFERENCES alerte (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ALTER date_envoi TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE alerte_utilisateur DROP CONSTRAINT FK_BCD6F73EA76ED395');
        $this->addSql('ALTER TABLE alerte_utilisateur DROP CONSTRAINT FK_BCD6F73E2C9BA629');
        $this->addSql('DROP TABLE alerte');
        $this->addSql('DROP TABLE alerte_utilisateur');
        $this->addSql('ALTER TABLE devis ALTER date_envoi TYPE DATE');
    }
}
