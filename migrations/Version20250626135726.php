<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250626135726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isPrimary to Contact, add User fields for templates, add legalForm to Customer';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contact ADD is_primary TINYINT(1) NOT NULL DEFAULT 0
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE customer ADD legal_form VARCHAR(50) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD first_name VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(255) DEFAULT NULL, ADD title VARCHAR(255) DEFAULT NULL, ADD signature LONGTEXT DEFAULT NULL
        SQL);

        // Définir automatiquement le premier contact de chaque client comme principal
        $this->addSql(<<<'SQL'
            UPDATE contact c1
            SET c1.is_primary = 1
            WHERE c1.id = (
                SELECT MIN(c2.id)
                FROM (SELECT * FROM contact) c2
                WHERE c2.customer_id = c1.customer_id
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE contact DROP is_primary
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE customer DROP legal_form
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP first_name, DROP phone, DROP title, DROP signature
        SQL);
    }
}
