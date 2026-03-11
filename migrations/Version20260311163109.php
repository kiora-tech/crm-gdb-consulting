<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311163109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make energy.customer_id NOT NULL to prevent orphan energy records';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM energy WHERE customer_id IS NULL');
        $this->addSql('ALTER TABLE energy CHANGE customer_id customer_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE energy CHANGE customer_id customer_id INT DEFAULT NULL');
    }
}
