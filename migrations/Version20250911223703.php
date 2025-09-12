<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250911223703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajout du champ date_livraison à la table devis
        $this->addSql('ALTER TABLE devis ADD date_livraison DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Suppression du champ date_livraison
        $this->addSql('ALTER TABLE devis DROP date_livraison');
    }
}
