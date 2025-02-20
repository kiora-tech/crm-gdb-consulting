<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250220062251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE energy ADD fta_id INT DEFAULT NULL, DROP fta');
        $this->addSql('ALTER TABLE energy ADD CONSTRAINT FK_9711799179A787AF FOREIGN KEY (fta_id) REFERENCES fta (id)');
        $this->addSql('CREATE INDEX IDX_9711799179A787AF ON energy (fta_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE energy DROP FOREIGN KEY FK_9711799179A787AF');
        $this->addSql('DROP INDEX IDX_9711799179A787AF ON energy');
        $this->addSql('ALTER TABLE energy ADD fta VARCHAR(255) DEFAULT NULL, DROP fta_id');
    }
}
