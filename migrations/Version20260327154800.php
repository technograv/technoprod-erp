<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour agrandir la colonne statut de devis de 20 à 30 caractères
 * pour supporter le nouveau statut 'actualisation_demandee' (24 caractères)
 */
final class Version20260327154800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrandir la colonne statut de la table devis de VARCHAR(20) à VARCHAR(30)';
    }

    public function up(Schema $schema): void
    {
        // Agrandir la colonne statut pour supporter 'actualisation_demandee'
        $this->addSql('ALTER TABLE devis ALTER COLUMN statut TYPE VARCHAR(30)');
    }

    public function down(Schema $schema): void
    {
        // Revenir à VARCHAR(20)
        $this->addSql('ALTER TABLE devis ALTER COLUMN statut TYPE VARCHAR(20)');
    }
}
