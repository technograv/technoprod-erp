<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250902211658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add commercial objectives fields to User table
        $this->addSql('ALTER TABLE "user" ADD objectif_mensuel NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD objectif_semestriel NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD notes_objectifs TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove commercial objectives fields from User table
        $this->addSql('ALTER TABLE "user" DROP objectif_mensuel');
        $this->addSql('ALTER TABLE "user" DROP objectif_semestriel');
        $this->addSql('ALTER TABLE "user" DROP notes_objectifs');
    }
}
