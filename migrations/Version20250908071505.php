<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908071505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE layout_element (id SERIAL NOT NULL, devis_id INT NOT NULL, type VARCHAR(50) NOT NULL, ordre_affichage INT NOT NULL, titre VARCHAR(255) DEFAULT NULL, contenu TEXT DEFAULT NULL, parametres JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_333C01AB41DEFADA ON layout_element (devis_id)');
        $this->addSql('COMMENT ON COLUMN layout_element.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN layout_element.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE layout_element ADD CONSTRAINT FK_333C01AB41DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE layout_element DROP CONSTRAINT FK_333C01AB41DEFADA');
        $this->addSql('DROP TABLE layout_element');
    }
}
