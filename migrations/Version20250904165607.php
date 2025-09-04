<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904165607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Nouveaux index pour optimiser les performances critiques
        
        // Index pour alertes actives avec ordre
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_alerte_active_ordre ON alerte (is_active, ordre)');
        
        // Index pour alertes avec expiration
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_alerte_expiration ON alerte (date_expiration) WHERE date_expiration IS NOT NULL');
        
        // Index pour lookup alertes utilisateur
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_alerte_utilisateur_lookup ON alerte_utilisateur (user_id, alerte_id)');
        
        // Index pour secteurs par commercial
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_secteur_commercial ON secteur (commercial_id) WHERE is_active = true');
        
        // Index pour rôles utilisateur (standard pour JSON)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_roles ON "user" ((roles::text))');
        
        // Index pour attributions secteur par type
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_attribution_secteur_type ON attribution_secteur (type_critere, valeur_critere)');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les nouveaux index
        $this->addSql('DROP INDEX idx_alerte_active_ordre');
        $this->addSql('DROP INDEX idx_alerte_expiration');
        $this->addSql('DROP INDEX idx_alerte_utilisateur_lookup');
        $this->addSql('DROP INDEX idx_secteur_commercial');
        $this->addSql('DROP INDEX IF EXISTS idx_user_roles');
        $this->addSql('DROP INDEX idx_attribution_secteur_type');
        
        // Rétablir noms index précédents
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
    }
}
