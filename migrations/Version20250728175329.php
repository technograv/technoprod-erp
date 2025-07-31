<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250728175329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_numerotation (id SERIAL NOT NULL, prefixe VARCHAR(2) NOT NULL, libelle VARCHAR(50) NOT NULL, compteur INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E695AA0AE54965FB ON document_numerotation (prefixe)');
        $this->addSql('COMMENT ON COLUMN document_numerotation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN document_numerotation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client ALTER actif DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN client.actif IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE document_numerotation');
        $this->addSql('ALTER TABLE client ALTER actif SET DEFAULT true');
        $this->addSql('COMMENT ON COLUMN client.actif IS \'true = actif, false = archivé\'');
    }
}
