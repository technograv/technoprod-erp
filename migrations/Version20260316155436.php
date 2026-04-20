<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316155436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE template (id SERIAL NOT NULL, societe_id INT NOT NULL, conditions_vente_id INT DEFAULT NULL, banque_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, type_document VARCHAR(50) NOT NULL, couleur_primaire VARCHAR(7) DEFAULT NULL, couleur_secondaire VARCHAR(7) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, options_mise_en_page JSON DEFAULT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_97601F83FCF77503 ON template (societe_id)');
        $this->addSql('CREATE INDEX IDX_97601F83622A5F26 ON template (conditions_vente_id)');
        $this->addSql('CREATE INDEX IDX_97601F8337E080D9 ON template (banque_id)');
        $this->addSql('COMMENT ON COLUMN template.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN template.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F83FCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F83622A5F26 FOREIGN KEY (conditions_vente_id) REFERENCES conditions_vente (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F8337E080D9 FOREIGN KEY (banque_id) REFERENCES banque (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE template DROP CONSTRAINT FK_97601F83FCF77503');
        $this->addSql('ALTER TABLE template DROP CONSTRAINT FK_97601F83622A5F26');
        $this->addSql('ALTER TABLE template DROP CONSTRAINT FK_97601F8337E080D9');
        $this->addSql('DROP TABLE template');
    }
}
