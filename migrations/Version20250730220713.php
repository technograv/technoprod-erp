<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730220713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_tag (client_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY(client_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_242D242719EB6921 ON client_tag (client_id)');
        $this->addSql('CREATE INDEX IDX_242D2427BAD26311 ON client_tag (tag_id)');
        $this->addSql('CREATE TABLE produit_tag (produit_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY(produit_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_423DC0FAF347EFB ON produit_tag (produit_id)');
        $this->addSql('CREATE INDEX IDX_423DC0FABAD26311 ON produit_tag (tag_id)');
        $this->addSql('CREATE TABLE tag (id SERIAL NOT NULL, nom VARCHAR(50) NOT NULL, couleur VARCHAR(7) DEFAULT NULL, description TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, assignation_automatique BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN tag.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tag.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client_tag ADD CONSTRAINT FK_242D242719EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client_tag ADD CONSTRAINT FK_242D2427BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_tag ADD CONSTRAINT FK_423DC0FAF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_tag ADD CONSTRAINT FK_423DC0FABAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE client_tag DROP CONSTRAINT FK_242D242719EB6921');
        $this->addSql('ALTER TABLE client_tag DROP CONSTRAINT FK_242D2427BAD26311');
        $this->addSql('ALTER TABLE produit_tag DROP CONSTRAINT FK_423DC0FAF347EFB');
        $this->addSql('ALTER TABLE produit_tag DROP CONSTRAINT FK_423DC0FABAD26311');
        $this->addSql('DROP TABLE client_tag');
        $this->addSql('DROP TABLE produit_tag');
        $this->addSql('DROP TABLE tag');
    }
}
