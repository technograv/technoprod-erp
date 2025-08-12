<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720205918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE produit ALTER type DROP DEFAULT');
        $this->addSql('ALTER TABLE produit ALTER prix_vente_ht DROP DEFAULT');
        $this->addSql('ALTER TABLE produit ALTER gestion_stock DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE produit ALTER type SET DEFAULT \'produit\'');
        $this->addSql('ALTER TABLE produit ALTER prix_vente_ht SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE produit ALTER gestion_stock SET DEFAULT false');
    }
}
