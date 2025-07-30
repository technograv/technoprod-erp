<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730151406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE banque (id SERIAL NOT NULL, code VARCHAR(10) NOT NULL, nom VARCHAR(100) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, pays VARCHAR(50) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, fax VARCHAR(20) DEFAULT NULL, email VARCHAR(100) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, code_journal VARCHAR(50) DEFAULT NULL, compte_comptable VARCHAR(50) DEFAULT NULL, code_journal_remise VARCHAR(50) DEFAULT NULL, compte_paiements_encaisser VARCHAR(50) DEFAULT NULL, rib_bban VARCHAR(23) DEFAULT NULL, iban VARCHAR(34) DEFAULT NULL, bic VARCHAR(11) DEFAULT NULL, numero_national_emetteur VARCHAR(6) DEFAULT NULL, identifiant_creancier_sepa VARCHAR(35) DEFAULT NULL, notes TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN banque.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN banque.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE frais_bancaire (id SERIAL NOT NULL, banque_id INT NOT NULL, libelle VARCHAR(100) NOT NULL, montant NUMERIC(10, 2) NOT NULL, type VARCHAR(50) NOT NULL, moyen_paiement VARCHAR(50) DEFAULT NULL, compte VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E0D9213B37E080D9 ON frais_bancaire (banque_id)');
        $this->addSql('COMMENT ON COLUMN frais_bancaire.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN frais_bancaire.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE frais_bancaire ADD CONSTRAINT FK_E0D9213B37E080D9 FOREIGN KEY (banque_id) REFERENCES banque (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE mode_paiement ADD banque_par_defaut_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement DROP banque_par_defaut');
        $this->addSql('ALTER TABLE mode_paiement ALTER remettre_en_banque DROP DEFAULT');
        $this->addSql('ALTER TABLE mode_paiement ADD CONSTRAINT FK_B2BB0E85F7EBC788 FOREIGN KEY (banque_par_defaut_id) REFERENCES banque (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_B2BB0E85F7EBC788 ON mode_paiement (banque_par_defaut_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE mode_paiement DROP CONSTRAINT FK_B2BB0E85F7EBC788');
        $this->addSql('ALTER TABLE frais_bancaire DROP CONSTRAINT FK_E0D9213B37E080D9');
        $this->addSql('DROP TABLE banque');
        $this->addSql('DROP TABLE frais_bancaire');
        $this->addSql('DROP INDEX IDX_B2BB0E85F7EBC788');
        $this->addSql('ALTER TABLE mode_paiement ADD banque_par_defaut VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement DROP banque_par_defaut_id');
        $this->addSql('ALTER TABLE mode_paiement ALTER remettre_en_banque SET DEFAULT false');
    }
}
