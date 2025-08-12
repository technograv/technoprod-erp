<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730075452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Créer la table mode_paiement
        $this->addSql('CREATE TABLE mode_paiement (id SERIAL NOT NULL, code VARCHAR(10) NOT NULL, nom VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, type_comptable VARCHAR(50) DEFAULT NULL, actif BOOLEAN NOT NULL, mode_paiement_par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, note TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN mode_paiement.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN mode_paiement.updated_at IS \'(DC2Type:datetime_immutable)\'');
        
        // Insérer des modes de paiement par défaut
        $this->addSql("INSERT INTO mode_paiement (code, nom, description, type_comptable, actif, mode_paiement_par_defaut, ordre, created_at, updated_at) VALUES 
            ('VIR', 'Virement bancaire', 'Virement SEPA', 'VIREMENT', true, true, 1, NOW(), NOW()),
            ('CHQ', 'Chèque', 'Paiement par chèque', 'CHEQUE', true, false, 2, NOW(), NOW()),
            ('ESP', 'Espèces', 'Paiement en espèces', 'ESPECES', true, false, 3, NOW(), NOW()),
            ('CB', 'Carte bancaire', 'Paiement par CB', 'CB', true, false, 4, NOW(), NOW()),
            ('PREL', 'Prélèvement', 'Prélèvement automatique', 'PRELEVEMENT', true, false, 5, NOW(), NOW())");
        
        // Ajouter les nouvelles colonnes à mode_reglement (nullables temporairement)
        $this->addSql('ALTER TABLE mode_reglement ADD mode_paiement_id INT NULL');
        $this->addSql('ALTER TABLE mode_reglement ADD code VARCHAR(6) NULL');
        $this->addSql('ALTER TABLE mode_reglement ADD nombre_jours INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_reglement ADD type_reglement VARCHAR(50) NULL');
        $this->addSql('ALTER TABLE mode_reglement ADD jour_reglement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_reglement ADD note TEXT DEFAULT NULL');
        
        // Migrer les données existantes
        $this->addSql("UPDATE mode_reglement SET 
            mode_paiement_id = (SELECT id FROM mode_paiement WHERE mode_paiement_par_defaut = true LIMIT 1),
            code = 'REG' || LPAD(id::text, 2, '0'),
            type_reglement = 'comptant'
            WHERE mode_paiement_id IS NULL");
        
        // Rendre les colonnes obligatoires
        $this->addSql('ALTER TABLE mode_reglement ALTER COLUMN mode_paiement_id SET NOT NULL');
        $this->addSql('ALTER TABLE mode_reglement ALTER COLUMN code SET NOT NULL');
        $this->addSql('ALTER TABLE mode_reglement ALTER COLUMN type_reglement SET NOT NULL');
        
        // Supprimer les anciennes colonnes
        $this->addSql('ALTER TABLE mode_reglement DROP description');
        $this->addSql('ALTER TABLE mode_reglement DROP type_comptable');
        
        // Ajouter les contraintes
        $this->addSql('ALTER TABLE mode_reglement ADD CONSTRAINT FK_8C3AECF4438F5B63 FOREIGN KEY (mode_paiement_id) REFERENCES mode_paiement (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8C3AECF4438F5B63 ON mode_reglement (mode_paiement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE mode_reglement DROP CONSTRAINT FK_8C3AECF4438F5B63');
        $this->addSql('DROP TABLE mode_paiement');
        $this->addSql('DROP INDEX IDX_8C3AECF4438F5B63');
        $this->addSql('ALTER TABLE mode_reglement ADD description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_reglement ADD type_comptable VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_reglement DROP mode_paiement_id');
        $this->addSql('ALTER TABLE mode_reglement DROP code');
        $this->addSql('ALTER TABLE mode_reglement DROP nombre_jours');
        $this->addSql('ALTER TABLE mode_reglement DROP type_reglement');
        $this->addSql('ALTER TABLE mode_reglement DROP jour_reglement');
        $this->addSql('ALTER TABLE mode_reglement DROP note');
    }
}
