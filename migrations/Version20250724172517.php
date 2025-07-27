<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250724172517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_preferences (id SERIAL NOT NULL, user_id INT NOT NULL, email_signature_type VARCHAR(20) DEFAULT \'company\' NOT NULL, custom_email_signature TEXT DEFAULT NULL, language VARCHAR(10) DEFAULT \'fr\' NOT NULL, timezone VARCHAR(50) DEFAULT \'Europe/Paris\' NOT NULL, email_notifications BOOLEAN DEFAULT true NOT NULL, sms_notifications BOOLEAN DEFAULT false NOT NULL, dashboard_widgets JSON DEFAULT NULL, table_preferences JSON DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_402A6F60A76ED395 ON user_preferences (user_id)');
        $this->addSql('COMMENT ON COLUMN user_preferences.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_preferences.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT FK_402A6F60A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE user_preferences DROP CONSTRAINT FK_402A6F60A76ED395');
        $this->addSql('DROP TABLE user_preferences');
    }
}
