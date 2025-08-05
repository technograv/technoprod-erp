<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250804211220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Augmenter la taille du champ code_region de 2 à 5 caractères
        $this->addSql('ALTER TABLE division_administrative ALTER code_region TYPE VARCHAR(5)');
    }

    public function down(Schema $schema): void
    {
        // Remettre la taille du champ code_region à 2 caractères
        $this->addSql('ALTER TABLE division_administrative ALTER code_region TYPE VARCHAR(2)');
    }
}
