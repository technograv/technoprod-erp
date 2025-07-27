<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250721080223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis ADD tiers_civilite VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_nom VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_prenom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_adresse VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_code_postal VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_ville VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE devis ADD tiers_mode_reglement VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE devis DROP tiers_civilite');
        $this->addSql('ALTER TABLE devis DROP tiers_nom');
        $this->addSql('ALTER TABLE devis DROP tiers_prenom');
        $this->addSql('ALTER TABLE devis DROP tiers_adresse');
        $this->addSql('ALTER TABLE devis DROP tiers_code_postal');
        $this->addSql('ALTER TABLE devis DROP tiers_ville');
        $this->addSql('ALTER TABLE devis DROP tiers_mode_reglement');
    }
}
