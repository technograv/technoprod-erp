<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250917084813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add delivery address fields to devis table
        $this->addSql('ALTER TABLE devis ADD tiers_adresse_livraison VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_code_postal_livraison VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_ville_livraison VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove delivery address fields from devis table
        $this->addSql('ALTER TABLE devis DROP tiers_adresse_livraison');
        $this->addSql('ALTER TABLE devis DROP tiers_code_postal_livraison');
        $this->addSql('ALTER TABLE devis DROP tiers_ville_livraison');
    }
}
