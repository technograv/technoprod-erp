<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250730144118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mode_paiement ADD banque_par_defaut VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement ADD remettre_en_banque BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE mode_paiement ADD code_journal_remise VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement ADD compte_remise VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement DROP description');
        $this->addSql('ALTER TABLE mode_paiement RENAME COLUMN type_comptable TO nature');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE mode_paiement ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement ADD type_comptable VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE mode_paiement DROP nature');
        $this->addSql('ALTER TABLE mode_paiement DROP banque_par_defaut');
        $this->addSql('ALTER TABLE mode_paiement DROP remettre_en_banque');
        $this->addSql('ALTER TABLE mode_paiement DROP code_journal_remise');
        $this->addSql('ALTER TABLE mode_paiement DROP compte_remise');
    }
}
