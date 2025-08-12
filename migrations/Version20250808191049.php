<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250808191049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajouter le champ ordre à la table societe
        $this->addSql('ALTER TABLE societe ADD ordre INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // Supprimer le champ ordre de la table societe
        $this->addSql('ALTER TABLE societe DROP ordre');
    }
}
