<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250413080031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE energy_provider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE energy ADD energy_provider_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE energy ADD CONSTRAINT FK_9711799140637D70 FOREIGN KEY (energy_provider_id) REFERENCES energy_provider (id)');
        $this->addSql('CREATE INDEX IDX_9711799140637D70 ON energy (energy_provider_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE energy_provider');
        $this->addSql('ALTER TABLE energy DROP FOREIGN KEY FK_9711799140637D70');
        $this->addSql('DROP INDEX IDX_9711799140637D70 ON energy');
        $this->addSql('ALTER TABLE energy DROP energy_provider_id');
    }
}
