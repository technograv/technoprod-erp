<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251004120908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renommer colonne periode en annee_exercice dans frais_generaux (YYYY au lieu de YYYY-MM)';
    }

    public function up(Schema $schema): void
    {
        // Créer index si n'existe pas
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_entity ON alerte (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_resolved ON alerte (resolved)');

        // Renommer index seulement s'ils existent
        $this->addSql('DO $$ BEGIN ALTER INDEX IF EXISTS idx_div_admin_code_postal RENAME TO idx_code_postal; EXCEPTION WHEN OTHERS THEN NULL; END $$');
        $this->addSql('DO $$ BEGIN ALTER INDEX IF EXISTS idx_div_admin_insee_commune RENAME TO idx_insee_commune; EXCEPTION WHEN OTHERS THEN NULL; END $$');
        $this->addSql('DO $$ BEGIN ALTER INDEX IF EXISTS idx_div_admin_departement RENAME TO idx_departement; EXCEPTION WHEN OTHERS THEN NULL; END $$');

        // Renommer la colonne periode en annee_exercice
        $this->addSql('ALTER TABLE frais_generaux RENAME COLUMN periode TO annee_exercice');

        // Extraire seulement l'année (YYYY) des valeurs YYYY-MM existantes AVANT de changer le type
        $this->addSql("UPDATE frais_generaux SET annee_exercice = SUBSTRING(annee_exercice, 1, 4)");

        // Maintenant on peut réduire la longueur de la colonne en toute sécurité
        $this->addSql('ALTER TABLE frais_generaux ALTER COLUMN annee_exercice TYPE VARCHAR(4)');
    }

    public function down(Schema $schema): void
    {
        // Rollback: renommer annee_exercice en periode
        $this->addSql('ALTER TABLE frais_generaux RENAME COLUMN annee_exercice TO periode');
        $this->addSql('ALTER TABLE frais_generaux ALTER COLUMN periode TYPE VARCHAR(7)');
        // Ajouter -01 par défaut pour retrouver le format YYYY-MM
        $this->addSql("UPDATE frais_generaux SET periode = periode || '-01' WHERE LENGTH(periode) = 4");

        // Rollback index
        $this->addSql('DROP INDEX IF EXISTS idx_entity');
        $this->addSql('DROP INDEX IF EXISTS idx_resolved');
        $this->addSql('DO $$ BEGIN ALTER INDEX IF EXISTS idx_code_postal RENAME TO idx_div_admin_code_postal; EXCEPTION WHEN OTHERS THEN NULL; END $$');
        $this->addSql('DO $$ BEGIN ALTER INDEX IF EXISTS idx_departement RENAME TO idx_div_admin_departement; EXCEPTION WHEN OTHERS THEN NULL; END $$');
        $this->addSql('DO $$ BEGIN ALTER INDEX IF EXISTS idx_insee_commune RENAME TO idx_div_admin_insee_commune; EXCEPTION WHEN OTHERS THEN NULL; END $$');
    }
}
