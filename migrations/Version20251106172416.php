<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106172416 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create import module tables: import, import_error, and import_analysis_result';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE import (id INT AUTO_INCREMENT NOT NULL, original_filename VARCHAR(255) NOT NULL, stored_filename VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, total_rows INT NOT NULL, processed_rows INT NOT NULL, success_rows INT NOT NULL, error_rows INT NOT NULL, user_id INT NOT NULL, INDEX idx_import_status (status), INDEX idx_import_created_at (created_at), INDEX idx_import_user_id (user_id), INDEX idx_import_type (type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE import_analysis_result (id INT AUTO_INCREMENT NOT NULL, operation_type VARCHAR(50) NOT NULL, entity_type VARCHAR(100) NOT NULL, count INT NOT NULL, details JSON DEFAULT NULL, created_at DATETIME NOT NULL, import_id INT NOT NULL, INDEX idx_import_analysis_result_import_id (import_id), INDEX idx_import_analysis_result_operation_type (operation_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE import_error (id INT AUTO_INCREMENT NOT NULL, `row_number` INT NOT NULL, severity VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, field_name VARCHAR(100) DEFAULT NULL, row_data JSON DEFAULT NULL, created_at DATETIME NOT NULL, import_id INT NOT NULL, INDEX idx_import_error_import_id (import_id), INDEX idx_import_error_severity (severity), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import ADD CONSTRAINT FK_9D4ECE1DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_analysis_result ADD CONSTRAINT FK_8A8C26C2B6A263D9 FOREIGN KEY (import_id) REFERENCES import (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_error ADD CONSTRAINT FK_B08813BFB6A263D9 FOREIGN KEY (import_id) REFERENCES import (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE import DROP FOREIGN KEY FK_9D4ECE1DA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_analysis_result DROP FOREIGN KEY FK_8A8C26C2B6A263D9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE import_error DROP FOREIGN KEY FK_B08813BFB6A263D9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE import
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE import_analysis_result
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE import_error
        SQL);
    }
}
