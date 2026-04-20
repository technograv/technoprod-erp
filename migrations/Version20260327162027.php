<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter le token d'accès client sécurisé aux devis
 * Remplace le calcul MD5 par un token random_bytes stocké en base
 */
final class Version20260327162027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne client_access_token à la table devis pour stocker les tokens sécurisés';
    }

    public function up(Schema $schema): void
    {
        // Ajouter la colonne client_access_token pour stocker les tokens sécurisés
        $this->addSql('ALTER TABLE devis ADD client_access_token VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la colonne client_access_token
        $this->addSql('ALTER TABLE devis DROP client_access_token');
    }
}
