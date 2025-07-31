<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731202508 extends AbstractMigration
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
        $this->addSql('ALTER TABLE client_tag ADD CONSTRAINT FK_242D242719EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client_tag ADD CONSTRAINT FK_242D2427BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE client_tag DROP CONSTRAINT FK_242D242719EB6921');
        $this->addSql('ALTER TABLE client_tag DROP CONSTRAINT FK_242D2427BAD26311');
        $this->addSql('DROP TABLE client_tag');
    }
}
