<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250221103132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template ADD document_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE template ADD CONSTRAINT FK_97601F8361232A4F FOREIGN KEY (document_type_id) REFERENCES document_type (id)');
        $this->addSql('CREATE INDEX IDX_97601F8361232A4F ON template (document_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE template DROP FOREIGN KEY FK_97601F8361232A4F');
        $this->addSql('DROP INDEX IDX_97601F8361232A4F ON template');
        $this->addSql('ALTER TABLE template DROP document_type_id');
    }
}
