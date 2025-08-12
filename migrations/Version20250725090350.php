<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour renommer prospect -> client et unifier les entités
 */
final class Version20250725090350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme la table prospect en client et unifie les entités Client/Prospect';
    }

    public function up(Schema $schema): void
    {
        // 1. Sauvegarder l'ancienne table client simple vers client_old
        $this->addSql('ALTER TABLE client RENAME TO client_old');
        
        // 2. Renommer la table prospect vers client (notre nouvelle table principale)
        $this->addSql('ALTER TABLE prospect RENAME TO client');
        
        // 3. Mettre à jour la référence dans la table devis : prospect_id -> client_id
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52bd182060a');
        $this->addSql('DROP INDEX idx_8b27c52bd182060a');
        $this->addSql('ALTER TABLE devis RENAME COLUMN prospect_id TO client_id');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT FK_8B27C52B19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B27C52B19EB6921 ON devis (client_id)');
    }

    public function down(Schema $schema): void
    {
        // Restaurer l'état précédent
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT FK_8B27C52B19EB6921');
        $this->addSql('DROP INDEX IDX_8B27C52B19EB6921');
        $this->addSql('ALTER TABLE devis RENAME COLUMN client_id TO prospect_id');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52bd182060a FOREIGN KEY (prospect_id) REFERENCES prospect (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8b27c52bd182060a ON devis (prospect_id)');
        
        // Restaurer les noms de tables
        $this->addSql('ALTER TABLE client RENAME TO prospect');
        $this->addSql('ALTER TABLE client_old RENAME TO client');
    }
}