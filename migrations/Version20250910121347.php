<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910121347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_image (id SERIAL NOT NULL, produit_id INT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, is_default BOOLEAN NOT NULL, alt VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_64617F03F347EFB ON product_image (produit_id)');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F03F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_element ADD product_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devis_element ADD image_visible BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE devis_element ADD CONSTRAINT FK_D3AEC565F6154FFA FOREIGN KEY (product_image_id) REFERENCES product_image (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D3AEC565F6154FFA ON devis_element (product_image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE devis_element DROP CONSTRAINT FK_D3AEC565F6154FFA');
        $this->addSql('ALTER TABLE product_image DROP CONSTRAINT FK_64617F03F347EFB');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP INDEX IDX_D3AEC565F6154FFA');
        $this->addSql('ALTER TABLE devis_element DROP product_image_id');
        $this->addSql('ALTER TABLE devis_element DROP image_visible');
    }
}
