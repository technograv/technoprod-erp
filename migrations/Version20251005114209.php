<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005114209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie_poste (id SERIAL NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, icone VARCHAR(50) DEFAULT NULL, couleur VARCHAR(7) DEFAULT NULL, ordre INT NOT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_89F1E27B77153098 ON categorie_poste (code)');
        $this->addSql('COMMENT ON COLUMN categorie_poste.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN categorie_poste.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE fiche_production (id SERIAL NOT NULL, devis_id INT DEFAULT NULL, devis_item_id INT DEFAULT NULL, produit_catalogue_id INT NOT NULL, numero VARCHAR(20) NOT NULL, configuration JSON NOT NULL, nomenclature_explosee JSON DEFAULT NULL, gamme_calculee JSON DEFAULT NULL, cout_revient JSON DEFAULT NULL, quantite NUMERIC(10, 4) NOT NULL, statut VARCHAR(20) NOT NULL, priorite INT NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, valide_par VARCHAR(255) DEFAULT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_livraison_prevue TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, pdf_path VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7DBBB517F55AE19E ON fiche_production (numero)');
        $this->addSql('CREATE INDEX IDX_7DBBB51741DEFADA ON fiche_production (devis_id)');
        $this->addSql('CREATE INDEX IDX_7DBBB5178081C902 ON fiche_production (devis_item_id)');
        $this->addSql('CREATE INDEX IDX_7DBBB517A0D31CA1 ON fiche_production (produit_catalogue_id)');
        $this->addSql('COMMENT ON COLUMN fiche_production.date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN fiche_production.date_validation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN fiche_production.date_debut IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN fiche_production.date_fin IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN fiche_production.date_livraison_prevue IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN fiche_production.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE gamme (id SERIAL NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, valide_par VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, temps_total_theorique INT DEFAULT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C32E146877153098 ON gamme (code)');
        $this->addSql('COMMENT ON COLUMN gamme.date_validation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gamme.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gamme.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE gamme_operation (id SERIAL NOT NULL, gamme_id INT NOT NULL, poste_travail_id INT NOT NULL, ordre INT NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, type_temps VARCHAR(30) NOT NULL, temps_fixe INT NOT NULL, formule_temps TEXT DEFAULT NULL, temps_parallele BOOLEAN NOT NULL, condition_execution TEXT DEFAULT NULL, instructions TEXT DEFAULT NULL, parametres_machine JSON DEFAULT NULL, controle_qualite BOOLEAN NOT NULL, description_controle TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7608A5FBD2FD85F1 ON gamme_operation (gamme_id)');
        $this->addSql('CREATE INDEX IDX_7608A5FB9FEBDA9B ON gamme_operation (poste_travail_id)');
        $this->addSql('COMMENT ON COLUMN gamme_operation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN gamme_operation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE nomenclature (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, valide_par VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_799A365277153098 ON nomenclature (code)');
        $this->addSql('CREATE INDEX IDX_799A3652727ACA70 ON nomenclature (parent_id)');
        $this->addSql('COMMENT ON COLUMN nomenclature.date_validation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN nomenclature.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN nomenclature.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE nomenclature_ligne (id SERIAL NOT NULL, nomenclature_id INT NOT NULL, produit_simple_id INT DEFAULT NULL, nomenclature_enfant_id INT DEFAULT NULL, unite_quantite_id INT DEFAULT NULL, ordre INT NOT NULL, type VARCHAR(30) NOT NULL, designation VARCHAR(255) NOT NULL, quantite_base NUMERIC(10, 4) NOT NULL, formule_quantite TEXT DEFAULT NULL, taux_chute NUMERIC(5, 2) NOT NULL, obligatoire BOOLEAN NOT NULL, condition_affichage TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, valoriser_chutes BOOLEAN NOT NULL, reference_fournisseur VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3B3281DA90BFD4B8 ON nomenclature_ligne (nomenclature_id)');
        $this->addSql('CREATE INDEX IDX_3B3281DA4C6DAF79 ON nomenclature_ligne (produit_simple_id)');
        $this->addSql('CREATE INDEX IDX_3B3281DA2FEB56D8 ON nomenclature_ligne (nomenclature_enfant_id)');
        $this->addSql('CREATE INDEX IDX_3B3281DAD54979BB ON nomenclature_ligne (unite_quantite_id)');
        $this->addSql('COMMENT ON COLUMN nomenclature_ligne.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN nomenclature_ligne.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE option_produit (id SERIAL NOT NULL, produit_catalogue_id INT NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type_champ VARCHAR(30) NOT NULL, obligatoire BOOLEAN NOT NULL, ordre INT NOT NULL, parametres JSON DEFAULT NULL, condition_affichage TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3151FB52A0D31CA1 ON option_produit (produit_catalogue_id)');
        $this->addSql('COMMENT ON COLUMN option_produit.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN option_produit.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE poste_travail (id SERIAL NOT NULL, categorie_id INT NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, cout_horaire NUMERIC(10, 2) NOT NULL, temps_setup INT NOT NULL, temps_nettoyage INT NOT NULL, capacite_journaliere NUMERIC(5, 2) DEFAULT NULL, necessite_operateur BOOLEAN NOT NULL, polyvalent BOOLEAN NOT NULL, specifications JSON DEFAULT NULL, consommables JSON DEFAULT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E033582B77153098 ON poste_travail (code)');
        $this->addSql('CREATE INDEX IDX_E033582BBCF5E72D ON poste_travail (categorie_id)');
        $this->addSql('COMMENT ON COLUMN poste_travail.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN poste_travail.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE produit_catalogue (id SERIAL NOT NULL, produit_id INT NOT NULL, nomenclature_id INT NOT NULL, gamme_id INT NOT NULL, parametres_defaut JSON DEFAULT NULL, variables_calculees JSON DEFAULT NULL, personnalisable BOOLEAN NOT NULL, afficher_sur_devis BOOLEAN NOT NULL, marge_defaut NUMERIC(5, 2) DEFAULT NULL, instructions_configuration TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DD31C719F347EFB ON produit_catalogue (produit_id)');
        $this->addSql('CREATE INDEX IDX_DD31C71990BFD4B8 ON produit_catalogue (nomenclature_id)');
        $this->addSql('CREATE INDEX IDX_DD31C719D2FD85F1 ON produit_catalogue (gamme_id)');
        $this->addSql('COMMENT ON COLUMN produit_catalogue.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN produit_catalogue.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE regle_compatibilite (id SERIAL NOT NULL, produit_catalogue_id INT NOT NULL, code VARCHAR(100) NOT NULL, nom VARCHAR(255) NOT NULL, type_regle VARCHAR(20) NOT NULL, expression TEXT NOT NULL, message_erreur TEXT NOT NULL, priorite INT NOT NULL, severite VARCHAR(20) NOT NULL, actions_auto JSON DEFAULT NULL, description TEXT DEFAULT NULL, actif BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_70C804BFA0D31CA1 ON regle_compatibilite (produit_catalogue_id)');
        $this->addSql('COMMENT ON COLUMN regle_compatibilite.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN regle_compatibilite.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tache (id SERIAL NOT NULL, fiche_production_id INT NOT NULL, gamme_operation_id INT DEFAULT NULL, poste_travail_id INT NOT NULL, operateur_assigne_id INT DEFAULT NULL, ordre INT NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, temps_prevu_minutes INT NOT NULL, temps_reel_minutes INT DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, instructions TEXT DEFAULT NULL, parametres_machine JSON DEFAULT NULL, commentaire_operateur TEXT DEFAULT NULL, motif_blocage TEXT DEFAULT NULL, controle_qualite BOOLEAN NOT NULL, controle_effectue BOOLEAN NOT NULL, resultat_controle VARCHAR(20) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_93872075BA294041 ON tache (fiche_production_id)');
        $this->addSql('CREATE INDEX IDX_9387207527B99E9 ON tache (gamme_operation_id)');
        $this->addSql('CREATE INDEX IDX_938720759FEBDA9B ON tache (poste_travail_id)');
        $this->addSql('CREATE INDEX IDX_938720756D5314C1 ON tache (operateur_assigne_id)');
        $this->addSql('COMMENT ON COLUMN tache.date_debut IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tache.date_fin IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tache.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tache.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE valeur_option (id SERIAL NOT NULL, option_id INT NOT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, supplement_prix NUMERIC(10, 2) NOT NULL, impact_cout NUMERIC(10, 2) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, couleur_hexa VARCHAR(7) DEFAULT NULL, ordre INT NOT NULL, par_defaut BOOLEAN NOT NULL, disponible BOOLEAN NOT NULL, stock INT DEFAULT NULL, donnees JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A10A264DA7C41D6F ON valeur_option (option_id)');
        $this->addSql('COMMENT ON COLUMN valeur_option.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN valeur_option.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE fiche_production ADD CONSTRAINT FK_7DBBB51741DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE fiche_production ADD CONSTRAINT FK_7DBBB5178081C902 FOREIGN KEY (devis_item_id) REFERENCES devis_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE fiche_production ADD CONSTRAINT FK_7DBBB517A0D31CA1 FOREIGN KEY (produit_catalogue_id) REFERENCES produit_catalogue (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gamme_operation ADD CONSTRAINT FK_7608A5FBD2FD85F1 FOREIGN KEY (gamme_id) REFERENCES gamme (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gamme_operation ADD CONSTRAINT FK_7608A5FB9FEBDA9B FOREIGN KEY (poste_travail_id) REFERENCES poste_travail (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE nomenclature ADD CONSTRAINT FK_799A3652727ACA70 FOREIGN KEY (parent_id) REFERENCES nomenclature (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE nomenclature_ligne ADD CONSTRAINT FK_3B3281DA90BFD4B8 FOREIGN KEY (nomenclature_id) REFERENCES nomenclature (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE nomenclature_ligne ADD CONSTRAINT FK_3B3281DA4C6DAF79 FOREIGN KEY (produit_simple_id) REFERENCES produit (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE nomenclature_ligne ADD CONSTRAINT FK_3B3281DA2FEB56D8 FOREIGN KEY (nomenclature_enfant_id) REFERENCES nomenclature (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE nomenclature_ligne ADD CONSTRAINT FK_3B3281DAD54979BB FOREIGN KEY (unite_quantite_id) REFERENCES unite (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE option_produit ADD CONSTRAINT FK_3151FB52A0D31CA1 FOREIGN KEY (produit_catalogue_id) REFERENCES produit_catalogue (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste_travail ADD CONSTRAINT FK_E033582BBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_poste (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_catalogue ADD CONSTRAINT FK_DD31C719F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_catalogue ADD CONSTRAINT FK_DD31C71990BFD4B8 FOREIGN KEY (nomenclature_id) REFERENCES nomenclature (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE produit_catalogue ADD CONSTRAINT FK_DD31C719D2FD85F1 FOREIGN KEY (gamme_id) REFERENCES gamme (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE regle_compatibilite ADD CONSTRAINT FK_70C804BFA0D31CA1 FOREIGN KEY (produit_catalogue_id) REFERENCES produit_catalogue (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_93872075BA294041 FOREIGN KEY (fiche_production_id) REFERENCES fiche_production (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_9387207527B99E9 FOREIGN KEY (gamme_operation_id) REFERENCES gamme_operation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_938720759FEBDA9B FOREIGN KEY (poste_travail_id) REFERENCES poste_travail (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tache ADD CONSTRAINT FK_938720756D5314C1 FOREIGN KEY (operateur_assigne_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE valeur_option ADD CONSTRAINT FK_A10A264DA7C41D6F FOREIGN KEY (option_id) REFERENCES option_produit (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE fiche_production DROP CONSTRAINT FK_7DBBB51741DEFADA');
        $this->addSql('ALTER TABLE fiche_production DROP CONSTRAINT FK_7DBBB5178081C902');
        $this->addSql('ALTER TABLE fiche_production DROP CONSTRAINT FK_7DBBB517A0D31CA1');
        $this->addSql('ALTER TABLE gamme_operation DROP CONSTRAINT FK_7608A5FBD2FD85F1');
        $this->addSql('ALTER TABLE gamme_operation DROP CONSTRAINT FK_7608A5FB9FEBDA9B');
        $this->addSql('ALTER TABLE nomenclature DROP CONSTRAINT FK_799A3652727ACA70');
        $this->addSql('ALTER TABLE nomenclature_ligne DROP CONSTRAINT FK_3B3281DA90BFD4B8');
        $this->addSql('ALTER TABLE nomenclature_ligne DROP CONSTRAINT FK_3B3281DA4C6DAF79');
        $this->addSql('ALTER TABLE nomenclature_ligne DROP CONSTRAINT FK_3B3281DA2FEB56D8');
        $this->addSql('ALTER TABLE nomenclature_ligne DROP CONSTRAINT FK_3B3281DAD54979BB');
        $this->addSql('ALTER TABLE option_produit DROP CONSTRAINT FK_3151FB52A0D31CA1');
        $this->addSql('ALTER TABLE poste_travail DROP CONSTRAINT FK_E033582BBCF5E72D');
        $this->addSql('ALTER TABLE produit_catalogue DROP CONSTRAINT FK_DD31C719F347EFB');
        $this->addSql('ALTER TABLE produit_catalogue DROP CONSTRAINT FK_DD31C71990BFD4B8');
        $this->addSql('ALTER TABLE produit_catalogue DROP CONSTRAINT FK_DD31C719D2FD85F1');
        $this->addSql('ALTER TABLE regle_compatibilite DROP CONSTRAINT FK_70C804BFA0D31CA1');
        $this->addSql('ALTER TABLE tache DROP CONSTRAINT FK_93872075BA294041');
        $this->addSql('ALTER TABLE tache DROP CONSTRAINT FK_9387207527B99E9');
        $this->addSql('ALTER TABLE tache DROP CONSTRAINT FK_938720759FEBDA9B');
        $this->addSql('ALTER TABLE tache DROP CONSTRAINT FK_938720756D5314C1');
        $this->addSql('ALTER TABLE valeur_option DROP CONSTRAINT FK_A10A264DA7C41D6F');
        $this->addSql('DROP TABLE categorie_poste');
        $this->addSql('DROP TABLE fiche_production');
        $this->addSql('DROP TABLE gamme');
        $this->addSql('DROP TABLE gamme_operation');
        $this->addSql('DROP TABLE nomenclature');
        $this->addSql('DROP TABLE nomenclature_ligne');
        $this->addSql('DROP TABLE option_produit');
        $this->addSql('DROP TABLE poste_travail');
        $this->addSql('DROP TABLE produit_catalogue');
        $this->addSql('DROP TABLE regle_compatibilite');
        $this->addSql('DROP TABLE tache');
        $this->addSql('DROP TABLE valeur_option');
    }
}
