<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250922123932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactoring Client entity: remove TVA fields and replace delaiPaiement with ModeReglement relation';
    }

    public function up(Schema $schema): void
    {
        // Supprimer les champs TVA inutiles (TVA gérée au niveau des produits)
        $this->addSql('ALTER TABLE client DROP COLUMN taux_tva');
        $this->addSql('ALTER TABLE client DROP COLUMN assujetti_tva');
        
        // Supprimer l'ancien champ delai_paiement (simple entier)
        $this->addSql('ALTER TABLE client DROP COLUMN delai_paiement');
        
        // Ajouter la nouvelle relation vers ModeReglement
        $this->addSql('ALTER TABLE client ADD mode_reglement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455E04B7BE2 FOREIGN KEY (mode_reglement_id) REFERENCES mode_reglement (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C7440455E04B7BE2 ON client (mode_reglement_id)');
    }

    public function down(Schema $schema): void
    {
        // Restaurer l'état précédent (rollback)
        $this->addSql('ALTER TABLE client DROP CONSTRAINT FK_C7440455E04B7BE2');
        $this->addSql('DROP INDEX IDX_C7440455E04B7BE2');
        $this->addSql('ALTER TABLE client DROP COLUMN mode_reglement_id');
        
        // Restaurer les anciens champs
        $this->addSql('ALTER TABLE client ADD delai_paiement INT DEFAULT 30');
        $this->addSql('ALTER TABLE client ADD taux_tva NUMERIC(5, 2) DEFAULT \'20.00\'');
        $this->addSql('ALTER TABLE client ADD assujetti_tva BOOLEAN DEFAULT true');
    }
}
