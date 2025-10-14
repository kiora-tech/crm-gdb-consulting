<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014124359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE calendar_event (id INT AUTO_INCREMENT NOT NULL, microsoft_event_id VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, start_date_time DATETIME NOT NULL, end_date_time DATETIME NOT NULL, location VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, synced_at DATETIME DEFAULT NULL, is_cancelled TINYINT(1) NOT NULL, created_by_id INT NOT NULL, customer_id INT NOT NULL, INDEX IDX_57FA09C9B03A8386 (created_by_id), INDEX IDX_57FA09C99395C3F3 (customer_id), INDEX idx_microsoft_event_id (microsoft_event_id), INDEX idx_start_date_time (start_date_time), INDEX idx_created_by_customer (created_by_id, customer_id), UNIQUE INDEX UNIQ_MICROSOFT_EVENT_ID (microsoft_event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C99395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C99395C3F3
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE calendar_event
        SQL);
    }
}
