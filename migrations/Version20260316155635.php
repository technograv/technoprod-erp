<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316155635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajout du champ signature_mail_defaut à la table societe
        $this->addSql('ALTER TABLE societe ADD signature_mail_defaut TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Suppression du champ signature_mail_defaut de la table societe
        $this->addSql('ALTER TABLE societe DROP signature_mail_defaut');
    }
}
