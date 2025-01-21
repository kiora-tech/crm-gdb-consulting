<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250121125549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_signing_document (id BINARY(16) NOT NULL, signature_request_status VARCHAR(255) NOT NULL, downloaded TINYINT(1) NOT NULL, client_document_id INT NOT NULL, UNIQUE INDEX UNIQ_835747D8C2745D0C (client_document_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_signing_document_signer (status VARCHAR(255) NOT NULL, decline_reason LONGTEXT DEFAULT NULL, client_id INT NOT NULL, client_signing_document_id BINARY(16) NOT NULL, INDEX IDX_ECF2F7E919EB6921 (client_id), INDEX IDX_ECF2F7E9AFECBCAE (client_signing_document_id), PRIMARY KEY(client_id, client_signing_document_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client_signing_document ADD CONSTRAINT FK_835747D8C2745D0C FOREIGN KEY (client_document_id) REFERENCES document (id)');
        $this->addSql('ALTER TABLE client_signing_document_signer ADD CONSTRAINT FK_ECF2F7E919EB6921 FOREIGN KEY (client_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE client_signing_document_signer ADD CONSTRAINT FK_ECF2F7E9AFECBCAE FOREIGN KEY (client_signing_document_id) REFERENCES client_signing_document (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_signing_document DROP FOREIGN KEY FK_835747D8C2745D0C');
        $this->addSql('ALTER TABLE client_signing_document_signer DROP FOREIGN KEY FK_ECF2F7E919EB6921');
        $this->addSql('ALTER TABLE client_signing_document_signer DROP FOREIGN KEY FK_ECF2F7E9AFECBCAE');
        $this->addSql('DROP TABLE client_signing_document');
        $this->addSql('DROP TABLE client_signing_document_signer');
    }
}
