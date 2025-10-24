<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014135000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cascade delete for CalendarEvent relationships, add isArchived field, and add timezone field to User';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C99395C3F3
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD is_archived TINYINT(1) NOT NULL, CHANGE created_by_id created_by_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C99395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD timezone VARCHAR(50) DEFAULT 'Europe/Paris' NOT NULL
        SQL);
        // Set default timezone for existing users
        $this->addSql(<<<'SQL'
            UPDATE user SET timezone = 'Europe/Paris' WHERE timezone IS NULL OR timezone = ''
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
            ALTER TABLE calendar_event DROP is_archived, CHANGE created_by_id created_by_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C99395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP timezone
        SQL);
    }
}
