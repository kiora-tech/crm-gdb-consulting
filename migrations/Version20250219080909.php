<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250219080909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fta (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, fixed_cost DOUBLE PRECISION NOT NULL, power_reservation_peak DOUBLE PRECISION NOT NULL, power_reservation_hph DOUBLE PRECISION NOT NULL, power_reservation_hch DOUBLE PRECISION NOT NULL, power_reservation_hpe DOUBLE PRECISION NOT NULL, power_reservation_hce DOUBLE PRECISION NOT NULL, consumption_peak DOUBLE PRECISION NOT NULL, consumption_hph DOUBLE PRECISION NOT NULL, consumption_hch DOUBLE PRECISION NOT NULL, consumption_hpe DOUBLE PRECISION NOT NULL, consumption_hce DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql("INSERT INTO fta (label, fixed_cost, power_reservation_peak, power_reservation_hph, power_reservation_hch, power_reservation_hpe, power_reservation_hce, consumption_peak, consumption_hph, consumption_hch, consumption_hpe, consumption_hce) VALUES
            ('HTACU5 - Pointe Fixe', 837.96, 13.12, 13.12, 13.12, 13.12, 13.12, 6.28, 4.50, 2.63, 0.76, 0.50),
            ('HTALU5 - Pointe Fixe', 837.96, 32.01, 28.89, 17.28, 14.10, 13.17, 2.93, 2.24, 1.70, 0.65, 0.49),
            ('HTACU5 - Pointe Mobile', 837.96, 13.12, 13.12, 13.12, 13.12, 13.12, 7.48, 4.33, 2.63, 0.76, 0.50),
            ('HTALU5 - Pointe Mobile', 837.96, 34.79, 30.78, 17.28, 14.10, 13.17, 3.40, 2.03, 1.70, 0.65, 0.49),
            ('BTSUPCU4', 477.60, 16.44, 16.44, 13.70, 13.28, 12.92, 5.91, 5.91, 4.53, 2.43, 1.68),
            ('BTSUPLU4', 477.60, 26.85, 26.85, 17.16, 15.14, 13.60, 4.94, 4.94, 3.93, 2.25, 1.38),
            ('BTINFCU4', 37.08, 9.36, 9.36, 9.36, 9.36, 9.36, 6.96, 6.96, 4.76, 1.48, 0.92),
            ('BTINFMU4', 37.08, 11.04, 11.04, 11.04, 11.04, 11.04, 6.39, 6.39, 4.43, 1.46, 0.91),
            ('BTINFMUDT', 37.08, 12.72, 12.72, 12.72, 12.72, 12.72, 4.68, 4.68, 3.31, 4.68, 3.31),
            ('BTINFCUST', 37.08, 10.44, 10.44, 10.44, 10.44, 10.44, 4.58, 4.58, 4.58, 4.58, 4.58),
            ('BTINFLU', 37.08, 84.96, 84.96, 84.96, 84.96, 84.96, 1.15, 1.15, 1.15, 1.15, 1.15)
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fta');
    }
}
