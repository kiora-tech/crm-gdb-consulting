<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix incorrect dates set to 31/12/2023 in contract_end column.
 * These dates were caused by Excel import conversion issues in production.
 */
final class Version20250811154448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix production issue: Set contract_end dates that are 31/12/2023 to NULL (Excel import error)';
    }

    public function up(Schema $schema): void
    {
        // Count how many records will be affected
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM energy WHERE contract_end = '2023-12-31'"
        );
        
        if ($count > 0) {
            $this->write(sprintf('Found %d contract_end dates set to 2023-12-31 (import error)', $count));
            
            // Update all dates that are 31/12/2023 to NULL
            $this->addSql("
                UPDATE energy 
                SET contract_end = NULL 
                WHERE contract_end = '2023-12-31'
            ");
            
            $this->write(sprintf('✓ Fixed %d records by setting contract_end from 2023-12-31 to NULL', $count));
        } else {
            $this->write('No dates to fix - no records found with contract_end = 2023-12-31');
        }
    }

    public function down(Schema $schema): void
    {
        // This migration cannot be reversed as we don't know which dates were originally 31/12/2023
        // and which were set to this date due to the Excel import error
        $this->write('WARNING: This migration cannot be reversed - original dates before import error are unknown');
    }
}