<?php

namespace App\DataFixtures;

use App\Entity\EnergyProvider;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EnergyProviderFixtures extends Fixture
{
    private const ENERGY_PROVIDERS = [
        'provider_edf' => 'EDF',
        'provider_engie' => 'Engie',
        'provider_total_energies' => 'TotalEnergies',
        'provider_ekwateur' => 'Ekwateur',
        'provider_eni' => 'ENI',
        'provider_grdf' => 'GRDF',
        'provider_vattenfall' => 'Vattenfall',
        'provider_iberdrola' => 'Iberdrola',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ENERGY_PROVIDERS as $reference => $name) {
            $provider = new EnergyProvider();
            $provider->setName($name);

            $manager->persist($provider);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $provider);
        }

        $manager->flush();
    }
}
