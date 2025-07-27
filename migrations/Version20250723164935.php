<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723164935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_trail (id SERIAL NOT NULL, user_id INT NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT NOT NULL, action VARCHAR(20) NOT NULL, old_values JSON NOT NULL, new_values JSON NOT NULL, changed_fields JSON NOT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent TEXT DEFAULT NULL, session_id VARCHAR(100) DEFAULT NULL, justification TEXT DEFAULT NULL, approved_by VARCHAR(50) DEFAULT NULL, record_hash VARCHAR(64) NOT NULL, previous_record_hash VARCHAR(64) DEFAULT NULL, metadata JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_entity_lookup ON audit_trail (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_timestamp ON audit_trail (timestamp)');
        $this->addSql('CREATE INDEX idx_action ON audit_trail (action)');
        $this->addSql('CREATE INDEX idx_user ON audit_trail (user_id)');
        $this->addSql('CREATE TABLE document_integrity (id SERIAL NOT NULL, created_by_id INT NOT NULL, modified_by_id INT DEFAULT NULL, document_type VARCHAR(50) NOT NULL, document_id INT NOT NULL, document_number VARCHAR(20) NOT NULL, hash_algorithm VARCHAR(10) NOT NULL, document_hash VARCHAR(64) NOT NULL, previous_hash VARCHAR(64) DEFAULT NULL, signature_data TEXT NOT NULL, timestamp_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, timestamp_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, qualified_timestamp VARCHAR(128) DEFAULT NULL, ip_address VARCHAR(45) NOT NULL, user_agent TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, last_verification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, integrity_valid BOOLEAN DEFAULT NULL, blockchain_tx_hash VARCHAR(66) DEFAULT NULL, blockchain_block_number INT DEFAULT NULL, blockchain_timestamp TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, compliance_metadata JSON NOT NULL, archival_reference VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4E269527B03A8386 ON document_integrity (created_by_id)');
        $this->addSql('CREATE INDEX IDX_4E26952799049ECE ON document_integrity (modified_by_id)');
        $this->addSql('CREATE INDEX idx_document_lookup ON document_integrity (document_type, document_id)');
        $this->addSql('CREATE INDEX idx_timestamp_creation ON document_integrity (timestamp_creation)');
        $this->addSql('CREATE INDEX idx_status ON document_integrity (status)');
        $this->addSql('ALTER TABLE audit_trail ADD CONSTRAINT FK_B523E178A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE document_integrity ADD CONSTRAINT FK_4E269527B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE document_integrity ADD CONSTRAINT FK_4E26952799049ECE FOREIGN KEY (modified_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE audit_trail DROP CONSTRAINT FK_B523E178A76ED395');
        $this->addSql('ALTER TABLE document_integrity DROP CONSTRAINT FK_4E269527B03A8386');
        $this->addSql('ALTER TABLE document_integrity DROP CONSTRAINT FK_4E26952799049ECE');
        $this->addSql('DROP TABLE audit_trail');
        $this->addSql('DROP TABLE document_integrity');
    }
}
