<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312142631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes on customer, energy, and contact tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_contact_name ON contact (first_name, last_name)');
        $this->addSql('CREATE INDEX idx_customer_status ON customer (status)');
        $this->addSql('CREATE INDEX idx_customer_name ON customer (name)');
        $this->addSql('CREATE INDEX idx_customer_siret ON customer (siret)');
        $this->addSql('CREATE INDEX idx_energy_contract_end ON energy (contract_end)');
        $this->addSql('CREATE INDEX idx_energy_code ON energy (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_contact_name ON contact');
        $this->addSql('DROP INDEX idx_customer_status ON customer');
        $this->addSql('DROP INDEX idx_customer_name ON customer');
        $this->addSql('DROP INDEX idx_customer_siret ON customer');
        $this->addSql('DROP INDEX idx_energy_contract_end ON energy');
        $this->addSql('DROP INDEX idx_energy_code ON energy');
    }
}
