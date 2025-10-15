<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014152032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD category VARCHAR(100) DEFAULT NULL, ADD contact_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_57FA09C9E7A1254A ON calendar_event (contact_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9E7A1254A
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_57FA09C9E7A1254A ON calendar_event
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event DROP category, DROP contact_id
        SQL);
    }
}
