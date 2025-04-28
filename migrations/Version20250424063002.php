<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250424063002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the project table with fields for customer_id, name, status, start_date, and deadline.';
    }

    public function up(Schema $schema): void
    {
        // Create the project table
        $this->addSql('
        CREATE TABLE project (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(255) NOT NULL,
            start_date DATETIME DEFAULT NULL,
            deadline VARCHAR(255) DEFAULT NULL,
            budget DECIMAL(10, 2) DEFAULT NULL,
            INDEX IDX_PROJECT_CUSTOMER_ID (customer_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_PROJECT_CUSTOMER_ID FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE
        )
    ');
    }

    public function down(Schema $schema): void
    {
        // Drop the project table
        $this->addSql('DROP TABLE project');
    }
}
