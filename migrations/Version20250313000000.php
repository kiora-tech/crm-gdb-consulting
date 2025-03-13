<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250313000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corriger la valeur "courier" en "courtier" dans l\'enum CanalSignature';
    }

    public function up(Schema $schema): void
    {
        // Mettre à jour les valeurs existantes dans la table customer
        $this->addSql('UPDATE customer SET canal_signature = "courtier" WHERE canal_signature = "courier"');

        // Note: Comme nous utilisons un PHP enum, nous n'avons pas besoin de modifier
        // la structure de la table ou la contrainte d'enum dans la base de données.
        // Le changement de l'enum en PHP est suffisant car les valeurs sont stockées
        // sous forme de chaînes de caractères.

        // Si vous utilisez MySQL avec une colonne ENUM définie explicitement (peu probable avec Doctrine + PHP enums)
        // vous auriez besoin de quelque chose comme:
        // $this->addSql('ALTER TABLE customer MODIFY COLUMN canal_signature ENUM("courtier", "fournisseur", "gdb")');
    }

    public function down(Schema $schema): void
    {
        // Restaurer les anciennes valeurs en cas de rollback
        $this->addSql('UPDATE customer SET canal_signature = "courier" WHERE canal_signature = "courtier"');

        // Même remarque que pour la méthode up() concernant la structure de la table
    }
}
