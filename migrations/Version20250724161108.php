<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250724161108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Augmente la longueur des champs téléphone de 20 à 25 caractères pour supporter les numéros internationaux';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact ALTER telephone TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact ALTER telephone_mobile TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact_facturation ALTER telephone TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact_facturation ALTER telephone_mobile TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact_facturation ALTER fax TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact_livraison ALTER telephone TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact_livraison ALTER telephone_mobile TYPE VARCHAR(25)');
        $this->addSql('ALTER TABLE contact_livraison ALTER fax TYPE VARCHAR(25)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE contact ALTER telephone TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact ALTER telephone_mobile TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact_facturation ALTER telephone TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact_facturation ALTER telephone_mobile TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact_facturation ALTER fax TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact_livraison ALTER telephone TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact_livraison ALTER telephone_mobile TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE contact_livraison ALTER fax TYPE VARCHAR(20)');
    }
}
