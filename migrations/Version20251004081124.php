<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251004081124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Ajouter le champ duree_validite_devis_defaut dans la table societe
        $this->addSql('ALTER TABLE societe ADD duree_validite_devis_defaut INT DEFAULT 30 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Supprimer le champ duree_validite_devis_defaut de la table societe
        $this->addSql('ALTER TABLE societe DROP duree_validite_devis_defaut');
    }
}
