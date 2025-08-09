<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250809055634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_permission (id SERIAL NOT NULL, user_id INT NOT NULL, societe_id INT NOT NULL, permissions JSON NOT NULL, niveau INT NOT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_472E5446A76ED395 ON user_permission (user_id)');
        $this->addSql('CREATE INDEX IDX_472E5446FCF77503 ON user_permission (societe_id)');
        $this->addSql('COMMENT ON COLUMN user_permission.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_permission.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_permission ADD CONSTRAINT FK_472E5446A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_permission ADD CONSTRAINT FK_472E5446FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_permission DROP CONSTRAINT FK_472E5446A76ED395');
        $this->addSql('ALTER TABLE user_permission DROP CONSTRAINT FK_472E5446FCF77503');
        $this->addSql('DROP TABLE user_permission');
    }
}
