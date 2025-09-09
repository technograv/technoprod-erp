<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908193731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refonte complète: Création de DevisElement unifié et migration des données DevisItem et LayoutElement';
    }

    public function up(Schema $schema): void
    {
        // 1. Créer la nouvelle table devis_element
        $this->addSql('CREATE TABLE devis_element (id SERIAL NOT NULL, devis_id INT NOT NULL, produit_id INT DEFAULT NULL, type VARCHAR(50) NOT NULL, position INT NOT NULL, designation VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, quantite NUMERIC(10, 2) DEFAULT NULL, prix_unitaire_ht NUMERIC(10, 2) DEFAULT NULL, remise_percent NUMERIC(5, 2) DEFAULT NULL, remise_montant NUMERIC(10, 2) DEFAULT NULL, tva_percent NUMERIC(5, 2) DEFAULT NULL, total_ligne_ht NUMERIC(10, 2) DEFAULT NULL, titre VARCHAR(255) DEFAULT NULL, contenu TEXT DEFAULT NULL, parametres JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D3AEC56541DEFADA ON devis_element (devis_id)');
        $this->addSql('CREATE INDEX IDX_D3AEC565F347EFB ON devis_element (produit_id)');
        $this->addSql('COMMENT ON COLUMN devis_element.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN devis_element.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE devis_element ADD CONSTRAINT FK_D3AEC56541DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_element ADD CONSTRAINT FK_D3AEC565F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        
        // 2. Migrer les données de devis_item vers devis_element
        $this->addSql("
            INSERT INTO devis_element (
                devis_id, produit_id, type, position, designation, description, quantite, 
                prix_unitaire_ht, remise_percent, remise_montant, tva_percent, total_ligne_ht,
                created_at, updated_at
            )
            SELECT 
                devis_id, produit_id, 'product' as type, 
                COALESCE(ordre_affichage, ROW_NUMBER() OVER (PARTITION BY devis_id ORDER BY id)) as position,
                designation, description, quantite, prix_unitaire_ht, remise_percent, remise_montant, 
                tva_percent, total_ligne_ht, NOW(), NOW()
            FROM devis_item
            ORDER BY devis_id, COALESCE(ordre_affichage, id)
        ");
        
        // 3. Migrer les données de layout_element vers devis_element
        $this->addSql("
            INSERT INTO devis_element (
                devis_id, type, position, titre, contenu, parametres, created_at, updated_at
            )
            SELECT 
                devis_id, type, 
                COALESCE(ordre_affichage, 1000 + ROW_NUMBER() OVER (PARTITION BY devis_id ORDER BY id)) as position,
                titre, contenu, parametres, created_at, updated_at
            FROM layout_element
            ORDER BY devis_id, COALESCE(ordre_affichage, id)
        ");
        
        // 4. Corriger les positions pour avoir une séquence continue par devis
        $this->addSql("
            WITH ordered_elements AS (
                SELECT 
                    id,
                    ROW_NUMBER() OVER (PARTITION BY devis_id ORDER BY position, id) as new_position
                FROM devis_element
            )
            UPDATE devis_element 
            SET position = ordered_elements.new_position
            FROM ordered_elements
            WHERE devis_element.id = ordered_elements.id
        ");
        
        // 5. Créer les index spécifiques pour devis_element
        $this->addSql('CREATE INDEX IDX_DEVIS_ELEMENT_POSITION ON devis_element (devis_id, position)');
        $this->addSql('CREATE INDEX IDX_DEVIS_ELEMENT_TYPE ON devis_element (type)');
    }

    public function down(Schema $schema): void
    {
        // Rollback: supprimer la table devis_element
        $this->addSql('ALTER TABLE devis_element DROP CONSTRAINT FK_D3AEC56541DEFADA');
        $this->addSql('ALTER TABLE devis_element DROP CONSTRAINT FK_D3AEC565F347EFB');
        $this->addSql('DROP INDEX IDX_DEVIS_ELEMENT_POSITION');
        $this->addSql('DROP INDEX IDX_DEVIS_ELEMENT_TYPE');
        $this->addSql('DROP TABLE devis_element');
    }
}
