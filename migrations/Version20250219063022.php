<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250219063022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE energy ADD power_kva INT DEFAULT NULL, ADD fta VARCHAR(255) DEFAULT NULL, ADD peak_consumption DOUBLE PRECISION DEFAULT NULL, ADD hph_consumption DOUBLE PRECISION DEFAULT NULL, ADD hch_consumption DOUBLE PRECISION DEFAULT NULL, ADD hpe_consumption DOUBLE PRECISION DEFAULT NULL, ADD hce_consumption DOUBLE PRECISION DEFAULT NULL, ADD base_consumption DOUBLE PRECISION DEFAULT NULL, ADD hp_consumption DOUBLE PRECISION DEFAULT NULL, ADD hc_consumption DOUBLE PRECISION DEFAULT NULL, ADD profile VARCHAR(255) DEFAULT NULL, ADD transport_rate VARCHAR(255) DEFAULT NULL, ADD total_consumption DOUBLE PRECISION DEFAULT NULL, DROP power, DROP base_price, DROP peak_hour, DROP off_peak_hour, DROP horo_season, DROP peak_hour_winter, DROP peak_hour_summer, DROP off_peak_hour_winter, DROP off_peak_hour_summer, DROP total, CHANGE type type VARCHAR(255) NOT NULL, CHANGE code code VARCHAR(255) DEFAULT NULL, CHANGE segment segment VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE energy ADD base_price VARCHAR(255) DEFAULT NULL, ADD peak_hour VARCHAR(255) DEFAULT NULL, ADD off_peak_hour VARCHAR(255) DEFAULT NULL, ADD horo_season VARCHAR(255) DEFAULT NULL, ADD peak_hour_winter INT DEFAULT NULL, ADD peak_hour_summer INT DEFAULT NULL, ADD off_peak_hour_winter INT DEFAULT NULL, ADD off_peak_hour_summer INT DEFAULT NULL, ADD total INT DEFAULT NULL, DROP fta, DROP peak_consumption, DROP hph_consumption, DROP hch_consumption, DROP hpe_consumption, DROP hce_consumption, DROP base_consumption, DROP hp_consumption, DROP hc_consumption, DROP profile, DROP transport_rate, DROP total_consumption, CHANGE type type VARCHAR(255) DEFAULT NULL, CHANGE code code BIGINT DEFAULT NULL, CHANGE segment segment VARCHAR(255) DEFAULT \'C1\', CHANGE power_kva power INT DEFAULT NULL');
    }
}
