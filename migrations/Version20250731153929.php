<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731153929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE taux_tva (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, taux NUMERIC(5, 2) NOT NULL, actif BOOLEAN NOT NULL, par_defaut BOOLEAN NOT NULL, ordre INT NOT NULL, vente_compte_debits VARCHAR(20) DEFAULT NULL, vente_compte_encaissements VARCHAR(20) DEFAULT NULL, vente_compte_biens VARCHAR(20) DEFAULT NULL, vente_compte_services VARCHAR(20) DEFAULT NULL, vente_compte_ports VARCHAR(20) DEFAULT NULL, vente_compte_eco_contribution VARCHAR(20) DEFAULT NULL, vente_compte_eco_contribution_mobilier VARCHAR(20) DEFAULT NULL, achat_compte_debits VARCHAR(20) DEFAULT NULL, achat_compte_encaissements VARCHAR(20) DEFAULT NULL, achat_compte_autoliquidation_biens VARCHAR(20) DEFAULT NULL, achat_compte_autoliquidation_services VARCHAR(20) DEFAULT NULL, achat_compte_biens VARCHAR(20) DEFAULT NULL, achat_compte_services VARCHAR(20) DEFAULT NULL, achat_compte_ports VARCHAR(20) DEFAULT NULL, achat_compte_eco_contribution VARCHAR(20) DEFAULT NULL, achat_compte_eco_contribution_mobilier VARCHAR(20) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN taux_tva.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN taux_tva.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE taux_tva');
    }
}
