<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250725163157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE prospect_id_seq1 CASCADE');
        $this->addSql('ALTER TABLE client_old DROP CONSTRAINT fk_c74404557854071c');
        $this->addSql('ALTER TABLE client_old DROP CONSTRAINT fk_c74404559f7e4405');
        $this->addSql('DROP TABLE client_old');
        // La structure adresse est déjà correcte, pas de modifications nécessaires
        $this->addSql('ALTER TABLE client DROP CONSTRAINT fk_c9ce8c7d5bbd1224');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT fk_c9ce8c7da2e3c911');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT fk_c9ce8c7da8387c44');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT fk_c9ce8c7dbe2f0a35');
        $this->addSql('DROP INDEX uniq_c9ce8c7d5bbd1224');
        $this->addSql('DROP INDEX uniq_c9ce8c7da2e3c911');
        $this->addSql('DROP INDEX uniq_c9ce8c7da8387c44');
        $this->addSql('DROP INDEX uniq_c9ce8c7dbe2f0a35');
        $this->addSql('ALTER TABLE client ADD contact_facturation_default_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD contact_livraison_default_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client DROP adresse_facturation_id');
        $this->addSql('ALTER TABLE client DROP adresse_livraison_id');
        $this->addSql('ALTER TABLE client DROP contact_facturation_id');
        $this->addSql('ALTER TABLE client DROP contact_livraison_id');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455E8F3A4FF FOREIGN KEY (contact_facturation_default_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455B531CD17 FOREIGN KEY (contact_livraison_default_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C7440455E8F3A4FF ON client (contact_facturation_default_id)');
        $this->addSql('CREATE INDEX IDX_C7440455B531CD17 ON client (contact_livraison_default_id)');
        $this->addSql('ALTER INDEX uniq_c9ce8c7d77153098 RENAME TO UNIQ_C744045577153098');
        $this->addSql('ALTER INDEX idx_c9ce8c7d7854071c RENAME TO IDX_C74404557854071C');
        $this->addSql('ALTER INDEX idx_c9ce8c7d9f7e4405 RENAME TO IDX_C74404559F7E4405');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contact DROP CONSTRAINT fk_contact_client');
        $this->addSql('ALTER TABLE contact DROP is_defaut');
        $this->addSql('ALTER TABLE contact ALTER is_facturation_default DROP DEFAULT');
        $this->addSql('ALTER TABLE contact ALTER is_facturation_default SET NOT NULL');
        $this->addSql('ALTER TABLE contact ALTER is_livraison_default DROP DEFAULT');
        $this->addSql('ALTER TABLE contact ALTER is_livraison_default SET NOT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63819EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B5BBD1224');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BA2E3C911');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BA8387C44');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52BBE2F0A35');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B5BBD1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BA2E3C911 FOREIGN KEY (contact_livraison_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BA8387C44 FOREIGN KEY (contact_facturation_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52BBE2F0A35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE prospect ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE prospect ALTER date_conversion_client TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN prospect.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN prospect.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN prospect.date_conversion_client IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D7854071C FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D9F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7D5BBD1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7DBE2F0A35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7DA8387C44 FOREIGN KEY (contact_facturation_id) REFERENCES contact_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect ADD CONSTRAINT FK_C9CE8C7DA2E3C911 FOREIGN KEY (contact_livraison_id) REFERENCES contact_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7D77153098 ON prospect (code)');
        $this->addSql('CREATE INDEX IDX_C9CE8C7D7854071C ON prospect (commercial_id)');
        $this->addSql('CREATE INDEX IDX_C9CE8C7D9F7E4405 ON prospect (secteur_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7D5BBD1224 ON prospect (adresse_facturation_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7DBE2F0A35 ON prospect (adresse_livraison_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7DA8387C44 ON prospect (contact_facturation_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C9CE8C7DA2E3C911 ON prospect (contact_livraison_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE prospect_id_seq1 INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE client_old (id SERIAL NOT NULL, secteur_id INT NOT NULL, commercial_id INT NOT NULL, nom_entreprise VARCHAR(200) NOT NULL, siret VARCHAR(14) DEFAULT NULL, code_client VARCHAR(20) NOT NULL, is_active BOOLEAN NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_c74404557854071c ON client_old (commercial_id)');
        $this->addSql('CREATE INDEX idx_c74404559f7e4405 ON client_old (secteur_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_c7440455b8c25cf7 ON client_old (code_client)');
        $this->addSql('COMMENT ON COLUMN client_old.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client_old.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client_old ADD CONSTRAINT fk_c74404557854071c FOREIGN KEY (commercial_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client_old ADD CONSTRAINT fk_c74404559f7e4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contact DROP CONSTRAINT FK_4C62E63819EB6921');
        $this->addSql('ALTER TABLE contact ADD is_defaut BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE contact ALTER is_facturation_default SET DEFAULT false');
        $this->addSql('ALTER TABLE contact ALTER is_facturation_default DROP NOT NULL');
        $this->addSql('ALTER TABLE contact ALTER is_livraison_default SET DEFAULT false');
        $this->addSql('ALTER TABLE contact ALTER is_livraison_default DROP NOT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT fk_contact_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C7440455E8F3A4FF');
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C7440455B531CD17');
        $this->addSql('DROP INDEX IDX_C7440455E8F3A4FF');
        $this->addSql('DROP INDEX IDX_C7440455B531CD17');
        $this->addSql('ALTER TABLE client ADD adresse_facturation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD adresse_livraison_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD contact_facturation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD contact_livraison_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client DROP contact_facturation_default_id');
        $this->addSql('ALTER TABLE client DROP contact_livraison_default_id');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT fk_c9ce8c7d5bbd1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT fk_c9ce8c7da2e3c911 FOREIGN KEY (contact_livraison_id) REFERENCES contact_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT fk_c9ce8c7da8387c44 FOREIGN KEY (contact_facturation_id) REFERENCES contact_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT fk_c9ce8c7dbe2f0a35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_c9ce8c7d5bbd1224 ON client (adresse_facturation_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_c9ce8c7da2e3c911 ON client (contact_livraison_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_c9ce8c7da8387c44 ON client (contact_facturation_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_c9ce8c7dbe2f0a35 ON client (adresse_livraison_id)');
        $this->addSql('ALTER INDEX idx_c74404557854071c RENAME TO idx_c9ce8c7d7854071c');
        $this->addSql('ALTER INDEX idx_c74404559f7e4405 RENAME TO idx_c9ce8c7d9f7e4405');
        $this->addSql('ALTER INDEX uniq_c744045577153098 RENAME TO uniq_c9ce8c7d77153098');
        $this->addSql('ALTER TABLE facture DROP CONSTRAINT FK_FE86641019EB6921');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52ba8387c44');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52ba2e3c911');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52b5bbd1224');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52bbe2f0a35');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52ba8387c44 FOREIGN KEY (contact_facturation_id) REFERENCES contact_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52ba2e3c911 FOREIGN KEY (contact_livraison_id) REFERENCES contact_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b5bbd1224 FOREIGN KEY (adresse_facturation_id) REFERENCES adresse_facturation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52bbe2f0a35 FOREIGN KEY (adresse_livraison_id) REFERENCES adresse_livraison (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7D7854071C');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7D9F7E4405');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7D5BBD1224');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7DBE2F0A35');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7DA8387C44');
        $this->addSql('ALTER TABLE prospect DROP CONSTRAINT FK_C9CE8C7DA2E3C911');
        $this->addSql('DROP INDEX UNIQ_C9CE8C7D77153098');
        $this->addSql('DROP INDEX IDX_C9CE8C7D7854071C');
        $this->addSql('DROP INDEX IDX_C9CE8C7D9F7E4405');
        $this->addSql('DROP INDEX UNIQ_C9CE8C7D5BBD1224');
        $this->addSql('DROP INDEX UNIQ_C9CE8C7DBE2F0A35');
        $this->addSql('DROP INDEX UNIQ_C9CE8C7DA8387C44');
        $this->addSql('DROP INDEX UNIQ_C9CE8C7DA2E3C911');
        $this->addSql('ALTER TABLE prospect ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE prospect ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE prospect ALTER date_conversion_client TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN prospect.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN prospect.updated_at IS NULL');
        $this->addSql('COMMENT ON COLUMN prospect.date_conversion_client IS NULL');
        $this->addSql('ALTER TABLE adresse DROP CONSTRAINT FK_C35F0816E7A1254A');
        $this->addSql('DROP INDEX IDX_C35F0816E7A1254A');
        $this->addSql('ALTER TABLE adresse ADD type_adresse VARCHAR(50) DEFAULT \'principale\'');
        $this->addSql('ALTER TABLE adresse ADD nom_lieu VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE adresse ADD is_defaut BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE adresse ADD is_facturation_default BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE adresse ADD is_livraison_default BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE adresse DROP nom');
        $this->addSql('ALTER TABLE adresse RENAME COLUMN contact_id TO client_id');
        $this->addSql('ALTER TABLE adresse ADD CONSTRAINT fk_adresse_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c35f081619eb6921 ON adresse (client_id)');
        $this->addSql('ALTER TABLE commande DROP CONSTRAINT FK_6EEAA67D19EB6921');
    }
}
