<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture principale qui regroupe toutes les autres fixtures.
 * On peut l'utiliser pour charger toutes les fixtures en une seule commande:
 * bin/console doctrine:fixtures:load --group=app.
 */
class AppFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // Cette méthode ne fait rien car toutes les données sont chargées
        // par les fixtures dont AppFixtures dépend
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
            UserFixtures::class,
            CustomerFixtures::class,
            DocumentTypeFixtures::class,
            TemplateFixtures::class,
            DocumentFixtures::class,
            EnergyProviderFixtures::class,
            FtaFixtures::class,
            EnergyFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }
}
