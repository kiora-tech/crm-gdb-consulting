<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250413080032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migre les données de Energy.provider vers EnergyProvider.name et établit les relations';
    }

    public function up(Schema $schema): void
    {
        // 1. Insérer les fournisseurs uniques avec la même collation
        $this->addSql('INSERT INTO energy_provider (name) 
                       SELECT DISTINCT provider COLLATE utf8mb4_unicode_ci FROM energy 
                       WHERE provider IS NOT NULL AND provider != ""');

        // 2. Mettre à jour les références en forçant la même collation pour la comparaison
        $this->addSql('UPDATE energy e 
                       JOIN energy_provider ep ON e.provider COLLATE utf8mb4_unicode_ci = ep.name COLLATE utf8mb4_unicode_ci
                       SET e.energy_provider_id = ep.id 
                       WHERE e.provider IS NOT NULL AND e.provider != ""');
    }

    public function down(Schema $schema): void
    {
        // Restaurer en forçant également la collation pour la down migration
        $this->addSql('UPDATE energy e 
                       JOIN energy_provider ep ON e.energy_provider_id = ep.id 
                       SET e.provider = ep.name COLLATE utf8mb4_unicode_ci
                       WHERE e.energy_provider_id IS NOT NULL');

        // Réinitialiser les clés étrangères
        $this->addSql('UPDATE energy SET energy_provider_id = NULL');
    }
}