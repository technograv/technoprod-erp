<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005175152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_poste_travail (user_id INT NOT NULL, poste_travail_id INT NOT NULL, PRIMARY KEY(user_id, poste_travail_id))');
        $this->addSql('CREATE INDEX IDX_EBDF57BA76ED395 ON user_poste_travail (user_id)');
        $this->addSql('CREATE INDEX IDX_EBDF57B9FEBDA9B ON user_poste_travail (poste_travail_id)');
        $this->addSql('ALTER TABLE user_poste_travail ADD CONSTRAINT FK_EBDF57BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_poste_travail ADD CONSTRAINT FK_EBDF57B9FEBDA9B FOREIGN KEY (poste_travail_id) REFERENCES poste_travail (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_poste_travail DROP CONSTRAINT FK_EBDF57BA76ED395');
        $this->addSql('ALTER TABLE user_poste_travail DROP CONSTRAINT FK_EBDF57B9FEBDA9B');
        $this->addSql('DROP TABLE user_poste_travail');
    }
}
