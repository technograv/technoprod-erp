<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251004110550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_lie (id SERIAL NOT NULL, produit_principal_id INT NOT NULL, produit_lie_id INT NOT NULL, type_relation VARCHAR(20) NOT NULL, ordre INT NOT NULL, quantite_defaut NUMERIC(10, 4) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A5748D15ADE32F93 ON article_lie (produit_principal_id)');
        $this->addSql('CREATE INDEX IDX_A5748D15387CAB97 ON article_lie (produit_lie_id)');
        $this->addSql('COMMENT ON COLUMN article_lie.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE famille_produit (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, ordre INT NOT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E5CFE6C977153098 ON famille_produit (code)');
        $this->addSql('CREATE INDEX IDX_E5CFE6C9727ACA70 ON famille_produit (parent_id)');
        $this->addSql('COMMENT ON COLUMN famille_produit.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN famille_produit.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE fournisseur (id SERIAL NOT NULL, forme_juridique_id INT DEFAULT NULL, mode_reglement_id INT DEFAULT NULL, contact_facturation_default_id INT DEFAULT NULL, contact_livraison_default_id INT DEFAULT NULL, code VARCHAR(20) NOT NULL, raison_sociale VARCHAR(200) NOT NULL, siren VARCHAR(20) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, numero_tva VARCHAR(50) DEFAULT NULL, code_naf VARCHAR(10) DEFAULT NULL, email VARCHAR(100) DEFAULT NULL, telephone VARCHAR(25) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, conditions_paiement VARCHAR(100) DEFAULT NULL, remise_generale NUMERIC(5, 2) DEFAULT NULL, statut VARCHAR(20) NOT NULL, notes_internes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_369ECA3277153098 ON fournisseur (code)');
        $this->addSql('CREATE INDEX IDX_369ECA329AEE68EB ON fournisseur (forme_juridique_id)');
        $this->addSql('CREATE INDEX IDX_369ECA32E04B7BE2 ON fournisseur (mode_reglement_id)');
        $this->addSql('CREATE INDEX IDX_369ECA32E8F3A4FF ON fournisseur (contact_facturation_default_id)');
        $this->addSql('CREATE INDEX IDX_369ECA32B531CD17 ON fournisseur (contact_livraison_default_id)');
        $this->addSql('COMMENT ON COLUMN fournisseur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN fournisseur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE frais_generaux (id SERIAL NOT NULL, libelle VARCHAR(255) NOT NULL, montant_mensuel NUMERIC(10, 2) NOT NULL, type_repartition VARCHAR(30) NOT NULL, volume_devis_mensuel_estime INT DEFAULT NULL, heures_momensuelles INT DEFAULT NULL, coefficient_majoration NUMERIC(5, 4) DEFAULT NULL, actif BOOLEAN NOT NULL, periode VARCHAR(7) NOT NULL, description TEXT DEFAULT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN frais_generaux.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN frais_generaux.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE produit_fournisseur (id SERIAL NOT NULL, produit_id INT NOT NULL, fournisseur_id INT NOT NULL, unite_achat_id INT DEFAULT NULL, reference_fournisseur VARCHAR(100) DEFAULT NULL, prix_vente_conseille NUMERIC(10, 4) DEFAULT NULL, remise_sur_pvc NUMERIC(5, 2) DEFAULT NULL, prix_achat_public NUMERIC(10, 4) NOT NULL, remise_achat NUMERIC(5, 2) NOT NULL, multiple_commande INT NOT NULL, delai_livraison_jours INT DEFAULT NULL, code_eco_contribution VARCHAR(50) DEFAULT NULL, priorite INT NOT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_48868EB6F347EFB ON produit_fournisseur (produit_id)');
        $this->addSql('CREATE INDEX IDX_48868EB6670C757F ON produit_fournisseur (fournisseur_id)');
        $this->addSql('CREATE INDEX IDX_48868EB6B1F04A04 ON produit_fournisseur (unite_achat_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_produit_fournisseur ON produit_fournisseur (produit_id, fournisseur_id)');
        $this->addSql('COMMENT ON COLUMN produit_fournisseur.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN produit_fournisseur.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE article_lie ADD CONSTRAINT FK_A5748D15ADE32F93 FOREIGN KEY (produit_principal_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_lie ADD CONSTRAINT FK_A5748D15387CAB97 FOREIGN KEY (produit_lie_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE famille_produit ADD CONSTRAINT FK_E5CFE6C9727ACA70 FOREIGN KEY (parent_id) REFERENCES famille_produit (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_369ECA329AEE68EB FOREIGN KEY (forme_juridique_id) REFERENCES forme_juridique (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_369ECA32E04B7BE2 FOREIGN KEY (mode_reglement_id) REFERENCES mode_reglement (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_369ECA32E8F3A4FF FOREIGN KEY (contact_facturation_default_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE fournisseur ADD CONSTRAINT FK_369ECA32B531CD17 FOREIGN KEY (contact_livraison_default_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_fournisseur ADD CONSTRAINT FK_48868EB6F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_fournisseur ADD CONSTRAINT FK_48868EB6670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_fournisseur ADD CONSTRAINT FK_48868EB6B1F04A04 FOREIGN KEY (unite_achat_id) REFERENCES unite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE adresse ADD fournisseur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE adresse ALTER client_id DROP NOT NULL');
        $this->addSql('ALTER TABLE adresse ADD CONSTRAINT FK_C35F0816670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C35F0816670C757F ON adresse (fournisseur_id)');
        $this->addSql('ALTER TABLE alerte ALTER resolved DROP DEFAULT');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_entity ON alerte (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_resolved ON alerte (resolved)');
        $this->addSql('ALTER TABLE contact ADD fournisseur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ALTER client_id DROP NOT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4C62E638670C757F ON contact (fournisseur_id)');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52b47625e59');
        $this->addSql('DROP INDEX idx_8b27c52b47625e59');
        $this->addSql('ALTER TABLE devis DROP COLUMN IF EXISTS current_version_created_by_id');
        $this->addSql('ALTER TABLE devis DROP COLUMN IF EXISTS current_version_reason');
        $this->addSql('ALTER TABLE devis DROP COLUMN IF EXISTS current_version_created_at');
        $this->addSql('ALTER TABLE produit ADD famille_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD fournisseur_principal_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD unite_vente_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD unite_achat_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD compte_vente_numero VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD compte_achat_numero VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD compte_stock_numero VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD compte_variation_stock_numero VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD frais_pourcentage NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE produit ADD quantite_defaut NUMERIC(10, 4) DEFAULT \'1.0000\' NOT NULL');
        $this->addSql('ALTER TABLE produit ADD nombre_decimales_prix INT DEFAULT 2 NOT NULL');
        $this->addSql('ALTER TABLE produit ADD type_destination VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD est_catalogue BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE produit ADD type_production VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD configurateur JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD complexite VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD est_concurrent BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC2797A77B84 FOREIGN KEY (famille_id) REFERENCES famille_produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC272E29527F FOREIGN KEY (fournisseur_principal_id) REFERENCES fournisseur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC2732A28C19 FOREIGN KEY (unite_vente_id) REFERENCES unite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC27B1F04A04 FOREIGN KEY (unite_achat_id) REFERENCES unite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC2796648DC0 FOREIGN KEY (compte_vente_numero) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC275D09053C FOREIGN KEY (compte_achat_numero) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC275356B76F FOREIGN KEY (compte_stock_numero) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC2791509822 FOREIGN KEY (compte_variation_stock_numero) REFERENCES compte_pcg (numero_compte) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_29A5EC2797A77B84 ON produit (famille_id)');
        $this->addSql('CREATE INDEX IDX_29A5EC272E29527F ON produit (fournisseur_principal_id)');
        $this->addSql('CREATE INDEX IDX_29A5EC2732A28C19 ON produit (unite_vente_id)');
        $this->addSql('CREATE INDEX IDX_29A5EC27B1F04A04 ON produit (unite_achat_id)');
        $this->addSql('CREATE INDEX IDX_29A5EC2796648DC0 ON produit (compte_vente_numero)');
        $this->addSql('CREATE INDEX IDX_29A5EC275D09053C ON produit (compte_achat_numero)');
        $this->addSql('CREATE INDEX IDX_29A5EC275356B76F ON produit (compte_stock_numero)');
        $this->addSql('CREATE INDEX IDX_29A5EC2791509822 ON produit (compte_variation_stock_numero)');
        $this->addSql('ALTER TABLE unite ADD symbole VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC2797A77B84');
        $this->addSql('ALTER TABLE adresse DROP CONSTRAINT FK_C35F0816670C757F');
        $this->addSql('ALTER TABLE contact DROP CONSTRAINT FK_4C62E638670C757F');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC272E29527F');
        $this->addSql('ALTER TABLE article_lie DROP CONSTRAINT FK_A5748D15ADE32F93');
        $this->addSql('ALTER TABLE article_lie DROP CONSTRAINT FK_A5748D15387CAB97');
        $this->addSql('ALTER TABLE famille_produit DROP CONSTRAINT FK_E5CFE6C9727ACA70');
        $this->addSql('ALTER TABLE fournisseur DROP CONSTRAINT FK_369ECA329AEE68EB');
        $this->addSql('ALTER TABLE fournisseur DROP CONSTRAINT FK_369ECA32E04B7BE2');
        $this->addSql('ALTER TABLE fournisseur DROP CONSTRAINT FK_369ECA32E8F3A4FF');
        $this->addSql('ALTER TABLE fournisseur DROP CONSTRAINT FK_369ECA32B531CD17');
        $this->addSql('ALTER TABLE produit_fournisseur DROP CONSTRAINT FK_48868EB6F347EFB');
        $this->addSql('ALTER TABLE produit_fournisseur DROP CONSTRAINT FK_48868EB6670C757F');
        $this->addSql('ALTER TABLE produit_fournisseur DROP CONSTRAINT FK_48868EB6B1F04A04');
        $this->addSql('DROP TABLE article_lie');
        $this->addSql('DROP TABLE famille_produit');
        $this->addSql('DROP TABLE fournisseur');
        $this->addSql('DROP TABLE frais_generaux');
        $this->addSql('DROP TABLE produit_fournisseur');
        $this->addSql('ALTER INDEX idx_code_postal RENAME TO idx_div_admin_code_postal');
        $this->addSql('ALTER INDEX idx_departement RENAME TO idx_div_admin_departement');
        $this->addSql('ALTER INDEX idx_insee_commune RENAME TO idx_div_admin_insee_commune');
        $this->addSql('ALTER TABLE devis ADD current_version_created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD current_version_created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN devis.current_version_created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b47625e59 FOREIGN KEY (current_version_created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8b27c52b47625e59 ON devis (current_version_created_by_id)');
        $this->addSql('DROP INDEX idx_entity');
        $this->addSql('DROP INDEX idx_resolved');
        $this->addSql('ALTER TABLE alerte ALTER resolved SET DEFAULT false');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC2732A28C19');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC27B1F04A04');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC2796648DC0');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC275D09053C');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC275356B76F');
        $this->addSql('ALTER TABLE produit DROP CONSTRAINT FK_29A5EC2791509822');
        $this->addSql('DROP INDEX IDX_29A5EC2797A77B84');
        $this->addSql('DROP INDEX IDX_29A5EC272E29527F');
        $this->addSql('DROP INDEX IDX_29A5EC2732A28C19');
        $this->addSql('DROP INDEX IDX_29A5EC27B1F04A04');
        $this->addSql('DROP INDEX IDX_29A5EC2796648DC0');
        $this->addSql('DROP INDEX IDX_29A5EC275D09053C');
        $this->addSql('DROP INDEX IDX_29A5EC275356B76F');
        $this->addSql('DROP INDEX IDX_29A5EC2791509822');
        $this->addSql('ALTER TABLE produit DROP famille_id');
        $this->addSql('ALTER TABLE produit DROP fournisseur_principal_id');
        $this->addSql('ALTER TABLE produit DROP unite_vente_id');
        $this->addSql('ALTER TABLE produit DROP unite_achat_id');
        $this->addSql('ALTER TABLE produit DROP compte_vente_numero');
        $this->addSql('ALTER TABLE produit DROP compte_achat_numero');
        $this->addSql('ALTER TABLE produit DROP compte_stock_numero');
        $this->addSql('ALTER TABLE produit DROP compte_variation_stock_numero');
        $this->addSql('ALTER TABLE produit DROP frais_pourcentage');
        $this->addSql('ALTER TABLE produit DROP quantite_defaut');
        $this->addSql('ALTER TABLE produit DROP nombre_decimales_prix');
        $this->addSql('ALTER TABLE produit DROP type_destination');
        $this->addSql('ALTER TABLE produit DROP est_catalogue');
        $this->addSql('ALTER TABLE produit DROP type_production');
        $this->addSql('ALTER TABLE produit DROP configurateur');
        $this->addSql('ALTER TABLE produit DROP complexite');
        $this->addSql('ALTER TABLE produit DROP est_concurrent');
        $this->addSql('ALTER TABLE unite DROP symbole');
        $this->addSql('DROP INDEX IDX_4C62E638670C757F');
        $this->addSql('ALTER TABLE contact DROP fournisseur_id');
        $this->addSql('ALTER TABLE contact ALTER client_id SET NOT NULL');
        $this->addSql('DROP INDEX IDX_C35F0816670C757F');
        $this->addSql('ALTER TABLE adresse DROP fournisseur_id');
        $this->addSql('ALTER TABLE adresse ALTER client_id SET NOT NULL');
    }
}
