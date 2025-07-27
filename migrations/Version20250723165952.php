<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723165952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE compte_pcg (numero_compte VARCHAR(10) NOT NULL, compte_parent_numero VARCHAR(10) DEFAULT NULL, libelle VARCHAR(255) NOT NULL, classe VARCHAR(1) NOT NULL, nature VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, is_actif BOOLEAN NOT NULL, is_pour_lettrage BOOLEAN NOT NULL, is_pour_analytique BOOLEAN NOT NULL, solde_debiteur NUMERIC(15, 2) NOT NULL, solde_crediteur NUMERIC(15, 2) NOT NULL, parametres_comptables JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(numero_compte))');
        $this->addSql('CREATE INDEX IDX_66FEF0E069F3FC27 ON compte_pcg (compte_parent_numero)');
        $this->addSql('CREATE TABLE ecriture_comptable (id SERIAL NOT NULL, journal_id INT NOT NULL, valide_par_id INT DEFAULT NULL, exercice_comptable_id INT NOT NULL, integrite_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, numero_ecriture VARCHAR(20) NOT NULL, date_ecriture DATE NOT NULL, libelle_ecriture VARCHAR(255) NOT NULL, numero_piece VARCHAR(20) NOT NULL, date_piece DATE NOT NULL, date_validation DATE DEFAULT NULL, is_validee BOOLEAN NOT NULL, is_equilibree BOOLEAN NOT NULL, lettrage VARCHAR(3) DEFAULT NULL, date_lettrage DATE DEFAULT NULL, document_type VARCHAR(50) DEFAULT NULL, document_id INT DEFAULT NULL, total_debit NUMERIC(15, 2) NOT NULL, total_credit NUMERIC(15, 2) NOT NULL, metadonnees JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6047F252478E8802 ON ecriture_comptable (journal_id)');
        $this->addSql('CREATE INDEX IDX_6047F2526AF12ED9 ON ecriture_comptable (valide_par_id)');
        $this->addSql('CREATE INDEX IDX_6047F25214A88B2F ON ecriture_comptable (exercice_comptable_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6047F2523252A7EF ON ecriture_comptable (integrite_id)');
        $this->addSql('CREATE INDEX IDX_6047F252B03A8386 ON ecriture_comptable (created_by_id)');
        $this->addSql('CREATE INDEX IDX_6047F252896DBBDE ON ecriture_comptable (updated_by_id)');
        $this->addSql('CREATE TABLE exercice_comptable (id SERIAL NOT NULL, cloture_par_id INT DEFAULT NULL, valide_par_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, annee_exercice INT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(20) NOT NULL, date_cloture DATE DEFAULT NULL, date_validation DATE DEFAULT NULL, total_debit NUMERIC(15, 2) NOT NULL, total_credit NUMERIC(15, 2) NOT NULL, nombre_ecritures INT NOT NULL, nombre_lignes_ecriture INT NOT NULL, metadonnees JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F9ABA871D596D79F ON exercice_comptable (cloture_par_id)');
        $this->addSql('CREATE INDEX IDX_F9ABA8716AF12ED9 ON exercice_comptable (valide_par_id)');
        $this->addSql('CREATE INDEX IDX_F9ABA871B03A8386 ON exercice_comptable (created_by_id)');
        $this->addSql('CREATE TABLE journal_comptable (id SERIAL NOT NULL, compte_contrepartie_defaut VARCHAR(10) DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, code VARCHAR(3) NOT NULL, libelle VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, is_actif BOOLEAN NOT NULL, is_obligatoire BOOLEAN NOT NULL, is_controle_numero_ecriture BOOLEAN NOT NULL, dernier_numero_ecriture INT NOT NULL, format_numero_ecriture VARCHAR(20) DEFAULT NULL, parametres JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E909F8E277153098 ON journal_comptable (code)');
        $this->addSql('CREATE INDEX IDX_E909F8E2D0932E64 ON journal_comptable (compte_contrepartie_defaut)');
        $this->addSql('CREATE INDEX IDX_E909F8E2B03A8386 ON journal_comptable (created_by_id)');
        $this->addSql('CREATE INDEX IDX_E909F8E2896DBBDE ON journal_comptable (updated_by_id)');
        $this->addSql('CREATE TABLE ligne_ecriture (id SERIAL NOT NULL, ecriture_id INT NOT NULL, compte_pcg_numero VARCHAR(10) NOT NULL, montant_debit NUMERIC(15, 2) NOT NULL, montant_credit NUMERIC(15, 2) NOT NULL, libelle_ligne VARCHAR(255) NOT NULL, compte_auxiliaire VARCHAR(17) DEFAULT NULL, compte_auxiliaire_libelle VARCHAR(255) DEFAULT NULL, date_echeance DATE DEFAULT NULL, lettrage VARCHAR(3) DEFAULT NULL, date_lettrage DATE DEFAULT NULL, montant_devise NUMERIC(15, 2) DEFAULT NULL, code_devise VARCHAR(3) DEFAULT NULL, taux_change NUMERIC(10, 6) DEFAULT NULL, code_analytique VARCHAR(20) DEFAULT NULL, pourcentage_analytique NUMERIC(5, 2) DEFAULT NULL, quantite NUMERIC(15, 3) DEFAULT NULL, unite VARCHAR(10) DEFAULT NULL, metadonnees JSON NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5C939CDD3407A4D0 ON ligne_ecriture (ecriture_id)');
        $this->addSql('CREATE INDEX IDX_5C939CDDF312FFF0 ON ligne_ecriture (compte_pcg_numero)');
        $this->addSql('ALTER TABLE compte_pcg ADD CONSTRAINT FK_66FEF0E069F3FC27 FOREIGN KEY (compte_parent_numero) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ecriture_comptable ADD CONSTRAINT FK_6047F252478E8802 FOREIGN KEY (journal_id) REFERENCES journal_comptable (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ecriture_comptable ADD CONSTRAINT FK_6047F2526AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ecriture_comptable ADD CONSTRAINT FK_6047F25214A88B2F FOREIGN KEY (exercice_comptable_id) REFERENCES exercice_comptable (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ecriture_comptable ADD CONSTRAINT FK_6047F2523252A7EF FOREIGN KEY (integrite_id) REFERENCES document_integrity (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ecriture_comptable ADD CONSTRAINT FK_6047F252B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ecriture_comptable ADD CONSTRAINT FK_6047F252896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE exercice_comptable ADD CONSTRAINT FK_F9ABA871D596D79F FOREIGN KEY (cloture_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE exercice_comptable ADD CONSTRAINT FK_F9ABA8716AF12ED9 FOREIGN KEY (valide_par_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE exercice_comptable ADD CONSTRAINT FK_F9ABA871B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE journal_comptable ADD CONSTRAINT FK_E909F8E2D0932E64 FOREIGN KEY (compte_contrepartie_defaut) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE journal_comptable ADD CONSTRAINT FK_E909F8E2B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE journal_comptable ADD CONSTRAINT FK_E909F8E2896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ligne_ecriture ADD CONSTRAINT FK_5C939CDD3407A4D0 FOREIGN KEY (ecriture_id) REFERENCES ecriture_comptable (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ligne_ecriture ADD CONSTRAINT FK_5C939CDDF312FFF0 FOREIGN KEY (compte_pcg_numero) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE compte_pcg DROP CONSTRAINT FK_66FEF0E069F3FC27');
        $this->addSql('ALTER TABLE ecriture_comptable DROP CONSTRAINT FK_6047F252478E8802');
        $this->addSql('ALTER TABLE ecriture_comptable DROP CONSTRAINT FK_6047F2526AF12ED9');
        $this->addSql('ALTER TABLE ecriture_comptable DROP CONSTRAINT FK_6047F25214A88B2F');
        $this->addSql('ALTER TABLE ecriture_comptable DROP CONSTRAINT FK_6047F2523252A7EF');
        $this->addSql('ALTER TABLE ecriture_comptable DROP CONSTRAINT FK_6047F252B03A8386');
        $this->addSql('ALTER TABLE ecriture_comptable DROP CONSTRAINT FK_6047F252896DBBDE');
        $this->addSql('ALTER TABLE exercice_comptable DROP CONSTRAINT FK_F9ABA871D596D79F');
        $this->addSql('ALTER TABLE exercice_comptable DROP CONSTRAINT FK_F9ABA8716AF12ED9');
        $this->addSql('ALTER TABLE exercice_comptable DROP CONSTRAINT FK_F9ABA871B03A8386');
        $this->addSql('ALTER TABLE journal_comptable DROP CONSTRAINT FK_E909F8E2D0932E64');
        $this->addSql('ALTER TABLE journal_comptable DROP CONSTRAINT FK_E909F8E2B03A8386');
        $this->addSql('ALTER TABLE journal_comptable DROP CONSTRAINT FK_E909F8E2896DBBDE');
        $this->addSql('ALTER TABLE ligne_ecriture DROP CONSTRAINT FK_5C939CDD3407A4D0');
        $this->addSql('ALTER TABLE ligne_ecriture DROP CONSTRAINT FK_5C939CDDF312FFF0');
        $this->addSql('DROP TABLE compte_pcg');
        $this->addSql('DROP TABLE ecriture_comptable');
        $this->addSql('DROP TABLE exercice_comptable');
        $this->addSql('DROP TABLE journal_comptable');
        $this->addSql('DROP TABLE ligne_ecriture');
    }
}
