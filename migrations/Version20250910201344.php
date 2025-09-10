<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910201344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajouter le champ email_envoi_automatique à la table devis
        $this->addSql('ALTER TABLE devis ADD email_envoi_automatique VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Supprimer le champ email_envoi_automatique de la table devis
        $this->addSql('ALTER TABLE devis DROP email_envoi_automatique');
    }
}
