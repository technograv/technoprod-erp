<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915185438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Create the client_log table only
        $this->addSql('CREATE TABLE client_log (id SERIAL NOT NULL, client_id INT NOT NULL, user_id INT DEFAULT NULL, action VARCHAR(100) NOT NULL, details TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A89BFB6119EB6921 ON client_log (client_id)');
        $this->addSql('CREATE INDEX IDX_A89BFB61A76ED395 ON client_log (user_id)');
        $this->addSql('COMMENT ON COLUMN client_log.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE client_log ADD CONSTRAINT FK_A89BFB6119EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE client_log ADD CONSTRAINT FK_A89BFB61A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // Drop the client_log table only
        $this->addSql('ALTER TABLE client_log DROP CONSTRAINT FK_A89BFB6119EB6921');
        $this->addSql('ALTER TABLE client_log DROP CONSTRAINT FK_A89BFB61A76ED395');
        $this->addSql('DROP TABLE client_log');
    }
}
