<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250808062354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parametres_enseigne (id SERIAL NOT NULL, societe_id INT NOT NULL, nom_entete_document VARCHAR(255) DEFAULT NULL, adresse_entete_document TEXT DEFAULT NULL, telephone_entete_document VARCHAR(20) DEFAULT NULL, email_entete_document VARCHAR(255) DEFAULT NULL, site_web_entete_document VARCHAR(255) DEFAULT NULL, conditions_generales_vente TEXT DEFAULT NULL, conditions_particulieres_vente TEXT DEFAULT NULL, mentions_legales_document TEXT DEFAULT NULL, titre_pages_accueil VARCHAR(255) DEFAULT NULL, message_accueil TEXT DEFAULT NULL, contenu_dashboard TEXT DEFAULT NULL, format_date_defaut VARCHAR(50) DEFAULT NULL, devise_defaut VARCHAR(10) DEFAULT NULL, langue_defaut VARCHAR(10) DEFAULT NULL, widgets_dashboard_personnalises JSON DEFAULT NULL, modules_actives JSON DEFAULT NULL, configuration_avancee JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4A6CA939FCF77503 ON parametres_enseigne (societe_id)');
        $this->addSql('COMMENT ON COLUMN parametres_enseigne.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN parametres_enseigne.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE parametres_enseigne ADD CONSTRAINT FK_4A6CA939FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parametres_enseigne DROP CONSTRAINT FK_4A6CA939FCF77503');
        $this->addSql('DROP TABLE parametres_enseigne');
    }
}
