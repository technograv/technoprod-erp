<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250729100136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ALTER type_personne DROP DEFAULT');
        $this->addSql('ALTER TABLE forme_juridique ADD forme_par_defaut BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE forme_juridique ADD ordre INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE client ALTER type_personne SET DEFAULT \'morale\'');
        $this->addSql('ALTER TABLE forme_juridique DROP forme_par_defaut');
        $this->addSql('ALTER TABLE forme_juridique DROP ordre');
    }
}
