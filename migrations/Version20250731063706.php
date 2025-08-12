<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731063706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_tag DROP CONSTRAINT fk_242d242719eb6921');
        $this->addSql('ALTER TABLE client_tag DROP CONSTRAINT fk_242d2427bad26311');
        $this->addSql('ALTER TABLE produit_tag DROP CONSTRAINT fk_423dc0fabad26311');
        $this->addSql('ALTER TABLE produit_tag DROP CONSTRAINT fk_423dc0faf347efb');
        $this->addSql('DROP TABLE client_tag');
        $this->addSql('DROP TABLE produit_tag');
        $this->addSql('ALTER TABLE client DROP type_personne');
        $this->addSql('ALTER TABLE forme_juridique DROP forme_par_defaut');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE client_tag (client_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY(client_id, tag_id))');
        $this->addSql('CREATE INDEX idx_242d242719eb6921 ON client_tag (client_id)');
        $this->addSql('CREATE INDEX idx_242d2427bad26311 ON client_tag (tag_id)');
        $this->addSql('CREATE TABLE produit_tag (produit_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY(produit_id, tag_id))');
        $this->addSql('CREATE INDEX idx_423dc0fabad26311 ON produit_tag (tag_id)');
        $this->addSql('CREATE INDEX idx_423dc0faf347efb ON produit_tag (produit_id)');
        $this->addSql('ALTER TABLE client_tag ADD CONSTRAINT fk_242d242719eb6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client_tag ADD CONSTRAINT fk_242d2427bad26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_tag ADD CONSTRAINT fk_423dc0fabad26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_tag ADD CONSTRAINT fk_423dc0faf347efb FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD type_personne VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE forme_juridique ADD forme_par_defaut BOOLEAN NOT NULL');
    }
}
