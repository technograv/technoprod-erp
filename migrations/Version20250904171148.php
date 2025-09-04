<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904171148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for dashboard queries';
    }

    public function up(Schema $schema): void
    {
        // Performance indexes for dashboard queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_client_statut ON client (statut)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_client_secteur_statut ON client (secteur_id, statut)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_devis_statut ON devis (statut)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_devis_client_statut ON devis (client_id, statut)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_devis_date_envoi ON devis (date_envoi) WHERE statut = \'ENVOYE\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_active ON "user" (is_active)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_secteur_commercial_active ON secteur (commercial_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove performance indexes
        $this->addSql('DROP INDEX IF EXISTS idx_client_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_client_secteur_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_devis_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_devis_client_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_devis_date_envoi');
        $this->addSql('DROP INDEX IF EXISTS idx_user_active');
        $this->addSql('DROP INDEX IF EXISTS idx_secteur_commercial_active');
    }
}
