<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250808064230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE groupe_utilisateur (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, nom VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, permissions JSON NOT NULL, niveau INT NOT NULL, couleur VARCHAR(7) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_92C1107D727ACA70 ON groupe_utilisateur (parent_id)');
        $this->addSql('COMMENT ON COLUMN groupe_utilisateur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN groupe_utilisateur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE groupe_utilisateur_societe (groupe_utilisateur_id INT NOT NULL, societe_id INT NOT NULL, PRIMARY KEY(groupe_utilisateur_id, societe_id))');
        $this->addSql('CREATE INDEX IDX_4CD47D2E9CC9DED6 ON groupe_utilisateur_societe (groupe_utilisateur_id)');
        $this->addSql('CREATE INDEX IDX_4CD47D2EFCF77503 ON groupe_utilisateur_societe (societe_id)');
        $this->addSql('CREATE TABLE user_groupe_utilisateur (user_id INT NOT NULL, groupe_utilisateur_id INT NOT NULL, PRIMARY KEY(user_id, groupe_utilisateur_id))');
        $this->addSql('CREATE INDEX IDX_3E85F686A76ED395 ON user_groupe_utilisateur (user_id)');
        $this->addSql('CREATE INDEX IDX_3E85F6869CC9DED6 ON user_groupe_utilisateur (groupe_utilisateur_id)');
        $this->addSql('ALTER TABLE groupe_utilisateur ADD CONSTRAINT FK_92C1107D727ACA70 FOREIGN KEY (parent_id) REFERENCES groupe_utilisateur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE groupe_utilisateur_societe ADD CONSTRAINT FK_4CD47D2E9CC9DED6 FOREIGN KEY (groupe_utilisateur_id) REFERENCES groupe_utilisateur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE groupe_utilisateur_societe ADD CONSTRAINT FK_4CD47D2EFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_groupe_utilisateur ADD CONSTRAINT FK_3E85F686A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_groupe_utilisateur ADD CONSTRAINT FK_3E85F6869CC9DED6 FOREIGN KEY (groupe_utilisateur_id) REFERENCES groupe_utilisateur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE groupe_utilisateur DROP CONSTRAINT FK_92C1107D727ACA70');
        $this->addSql('ALTER TABLE groupe_utilisateur_societe DROP CONSTRAINT FK_4CD47D2E9CC9DED6');
        $this->addSql('ALTER TABLE groupe_utilisateur_societe DROP CONSTRAINT FK_4CD47D2EFCF77503');
        $this->addSql('ALTER TABLE user_groupe_utilisateur DROP CONSTRAINT FK_3E85F686A76ED395');
        $this->addSql('ALTER TABLE user_groupe_utilisateur DROP CONSTRAINT FK_3E85F6869CC9DED6');
        $this->addSql('DROP TABLE groupe_utilisateur');
        $this->addSql('DROP TABLE groupe_utilisateur_societe');
        $this->addSql('DROP TABLE user_groupe_utilisateur');
    }
}
