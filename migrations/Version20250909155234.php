<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909155234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajouter le champ acompte_defaut_percent à la table societe
        $this->addSql('ALTER TABLE societe ADD acompte_defaut_percent NUMERIC(5, 2) DEFAULT \'30.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Supprimer le champ acompte_defaut_percent de la table societe
        $this->addSql('ALTER TABLE societe DROP acompte_defaut_percent');
    }
}
