<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250903162056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // Add sync fields to comment (already has created_at and updated_at)
        $this->addSql('ALTER TABLE comment ADD synced_at DATETIME DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD client_id VARCHAR(255) DEFAULT NULL');

        // Add sync fields and timestamps to contact
        $this->addSql('ALTER TABLE contact ADD synced_at DATETIME DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD client_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE contact ADD updated_at DATETIME DEFAULT NULL');

        // Add sync fields and timestamps to customer
        $this->addSql('ALTER TABLE customer ADD synced_at DATETIME DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD client_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE customer ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE customer ADD updated_at DATETIME DEFAULT NULL');

        // Add sync fields and timestamps to energy
        $this->addSql('ALTER TABLE energy ADD synced_at DATETIME DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, ADD client_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE energy ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE energy ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP synced_at, DROP version, DROP client_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE contact DROP synced_at, DROP version, DROP client_id, DROP created_at, DROP updated_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE customer DROP synced_at, DROP version, DROP client_id, DROP created_at, DROP updated_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE energy DROP synced_at, DROP version, DROP client_id, DROP created_at, DROP updated_at
        SQL);
    }
}
