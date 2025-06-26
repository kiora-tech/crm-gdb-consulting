<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250626143553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add detailed address fields to Customer and Contact entities';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contact ADD address_number VARCHAR(10) DEFAULT NULL, ADD address_street VARCHAR(255) DEFAULT NULL, ADD address_postal_code VARCHAR(10) DEFAULT NULL, ADD address_city VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE customer ADD address_number VARCHAR(10) DEFAULT NULL, ADD address_street VARCHAR(255) DEFAULT NULL, ADD address_postal_code VARCHAR(10) DEFAULT NULL, ADD address_city VARCHAR(100) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contact DROP address_number, DROP address_street, DROP address_postal_code, DROP address_city
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE customer DROP address_number, DROP address_street, DROP address_postal_code, DROP address_city
        SQL);
    }
}
