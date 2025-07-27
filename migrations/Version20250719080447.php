<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250719080447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis ALTER contact_id DROP NOT NULL');
        $this->addSql('ALTER TABLE devis ALTER adresse_facturation_id DROP NOT NULL');
        $this->addSql('ALTER TABLE devis ALTER adresse_livraison_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE devis ALTER contact_id SET NOT NULL');
        $this->addSql('ALTER TABLE devis ALTER adresse_facturation_id SET NOT NULL');
        $this->addSql('ALTER TABLE devis ALTER adresse_livraison_id SET NOT NULL');
    }
}
