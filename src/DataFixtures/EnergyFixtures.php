<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyProvider;
use App\Entity\EnergyType;
use App\Entity\Fta;
use App\Entity\GasTransportRate;
use App\Entity\Segment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EnergyFixtures extends Fixture implements DependentFixtureInterface
{
    private const ENERGIES = [
        // Énergies pour le premier client de l'admin
        'energy_admin_1_elec' => [
            'customer' => 'customer_admin_1',
            'type' => EnergyType::ELEC,
            'code' => 'PDL12345678901',
            'contractEnd' => '+180 days',
            'powerKva' => 36,
            'fta' => 'fta_business',
            'segment' => Segment::C4,
            'provider' => 'provider_edf',
            'consumption' => [
                'peak' => 12500.0,
                'hph' => 45000.0,
                'hch' => 22000.0,
                'hpe' => 38000.0,
                'hce' => 18000.0,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 135500.0,
            ],
        ],
        'energy_admin_1_gas' => [
            'customer' => 'customer_admin_1',
            'type' => EnergyType::GAZ,
            'code' => 'PCE9876543210987',
            'contractEnd' => '+150 days',
            'powerKva' => null,
            'fta' => null,
            'segment' => null,
            'provider' => 'provider_engie',
            'profile' => 'P014',
            'transportRate' => GasTransportRate::T3,
            'consumption' => [
                'peak' => null,
                'hph' => null,
                'hch' => null,
                'hpe' => null,
                'hce' => null,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 210000.0,
            ],
        ],

        // Énergies pour le deuxième client de l'admin
        'energy_admin_2_elec' => [
            'customer' => 'customer_admin_2',
            'type' => EnergyType::ELEC,
            'code' => 'PDL23456789012',
            'contractEnd' => '+90 days',
            'powerKva' => 120,
            'fta' => 'fta_premium',
            'segment' => Segment::C3,
            'provider' => 'provider_total_energies',
            'consumption' => [
                'peak' => 45000.0,
                'hph' => 120000.0,
                'hch' => 60000.0,
                'hpe' => 95000.0,
                'hce' => 40000.0,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 360000.0,
            ],
        ],

        // Énergies pour le premier client de l'utilisateur régulier
        'energy_regular_1_elec' => [
            'customer' => 'customer_regular_1',
            'type' => EnergyType::ELEC,
            'code' => 'PDL34567890123',
            'contractEnd' => '+45 days',
            'powerKva' => 12,
            'fta' => 'fta_standard',
            'segment' => Segment::C5,
            'provider' => 'provider_ekwateur',
            'consumption' => [
                'peak' => null,
                'hph' => null,
                'hch' => null,
                'hpe' => null,
                'hce' => null,
                'base' => null,
                'hp' => 15000.0,
                'hc' => 10000.0,
                'total' => 25000.0,
            ],
        ],

        // Énergies pour le client du manager
        'energy_manager_1_elec_1' => [
            'customer' => 'customer_manager_1',
            'type' => EnergyType::ELEC,
            'code' => 'PDL45678901234',
            'contractEnd' => '+270 days',
            'powerKva' => 60,
            'fta' => 'fta_business',
            'segment' => Segment::C4,
            'provider' => 'provider_edf',
            'consumption' => [
                'peak' => 20000.0,
                'hph' => 65000.0,
                'hch' => 32000.0,
                'hpe' => 55000.0,
                'hce' => 28000.0,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 200000.0,
            ],
        ],
        'energy_manager_1_elec_2' => [
            'customer' => 'customer_manager_1',
            'type' => EnergyType::ELEC,
            'code' => 'PDL56789012345',
            'contractEnd' => '+240 days',
            'powerKva' => 48,
            'fta' => 'fta_business',
            'segment' => Segment::C4,
            'provider' => 'provider_edf',
            'consumption' => [
                'peak' => 18000.0,
                'hph' => 55000.0,
                'hch' => 28000.0,
                'hpe' => 45000.0,
                'hce' => 24000.0,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 170000.0,
            ],
        ],
        'energy_manager_1_gas' => [
            'customer' => 'customer_manager_1',
            'type' => EnergyType::GAZ,
            'code' => 'PCE8765432109876',
            'contractEnd' => '+220 days',
            'powerKva' => null,
            'fta' => null,
            'segment' => null,
            'provider' => 'provider_grdf',
            'profile' => 'P016',
            'transportRate' => GasTransportRate::T4,
            'consumption' => [
                'peak' => null,
                'hph' => null,
                'hch' => null,
                'hpe' => null,
                'hce' => null,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 450000.0,
            ],
        ],

        // Énergies pour le premier client de l'utilisateur commercial
        'energy_sales_1_elec' => [
            'customer' => 'customer_sales_1',
            'type' => EnergyType::ELEC,
            'code' => 'PDL67890123456',
            'contractEnd' => '-30 days', // Contrat expiré, besoin de renouvellement
            'powerKva' => 24,
            'fta' => 'fta_standard',
            'segment' => Segment::C5,
            'provider' => 'provider_eni',
            'consumption' => [
                'peak' => null,
                'hph' => null,
                'hch' => null,
                'hpe' => null,
                'hce' => null,
                'base' => null,
                'hp' => 25000.0,
                'hc' => 15000.0,
                'total' => 40000.0,
            ],
        ],

        // Énergies pour le deuxième client de l'utilisateur commercial
        'energy_sales_2_elec' => [
            'customer' => 'customer_sales_2',
            'type' => EnergyType::ELEC,
            'code' => 'PDL78901234567',
            'contractEnd' => '-15 days', // Contrat expiré, en cours de renouvellement
            'powerKva' => 180,
            'fta' => 'fta_premium',
            'segment' => Segment::C3,
            'provider' => 'provider_vattenfall',
            'consumption' => [
                'peak' => 60000.0,
                'hph' => 180000.0,
                'hch' => 90000.0,
                'hpe' => 150000.0,
                'hce' => 70000.0,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 550000.0,
            ],
        ],
        'energy_sales_2_gas' => [
            'customer' => 'customer_sales_2',
            'type' => EnergyType::GAZ,
            'code' => 'PCE7654321098765',
            'contractEnd' => '-10 days', // Contrat expiré, en cours de renouvellement
            'powerKva' => null,
            'fta' => null,
            'segment' => null,
            'provider' => 'provider_iberdrola',
            'profile' => 'P017',
            'transportRate' => GasTransportRate::T4,
            'consumption' => [
                'peak' => null,
                'hph' => null,
                'hch' => null,
                'hpe' => null,
                'hce' => null,
                'base' => null,
                'hp' => null,
                'hc' => null,
                'total' => 680000.0,
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ENERGIES as $reference => $energyData) {
            $energy = new Energy();
            $energy->setCustomer($this->getReference($energyData['customer'], Customer::class));
            $energy->setType($energyData['type']);
            $energy->setCode($energyData['code']);

            if ($energyData['contractEnd']) {
                $energy->setContractEnd(new \DateTime($energyData['contractEnd']));
            }

            if ($energyData['powerKva']) {
                $energy->setPowerKva($energyData['powerKva']);
            }

            if ($energyData['fta']) {
                $energy->setFta($this->getReference($energyData['fta'], Fta::class));
            }

            if ($energyData['segment']) {
                $energy->setSegment($energyData['segment']);
            }

            if ($energyData['provider']) {
                $energy->setEnergyProvider($this->getReference($energyData['provider'], EnergyProvider::class));
            }

            if (isset($energyData['profile'])) {
                $energy->setProfile($energyData['profile']);
            }

            if (isset($energyData['transportRate'])) {
                $energy->setTransportRate($energyData['transportRate']);
            }

            // Consommation
            if (isset($energyData['consumption']['peak']) && null !== $energyData['consumption']['peak']) {
                $energy->setPeakConsumption($energyData['consumption']['peak']);
            }

            if (isset($energyData['consumption']['hph']) && null !== $energyData['consumption']['hph']) {
                $energy->setHphConsumption($energyData['consumption']['hph']);
            }

            if (isset($energyData['consumption']['hch']) && null !== $energyData['consumption']['hch']) {
                $energy->setHchConsumption($energyData['consumption']['hch']);
            }

            if (isset($energyData['consumption']['hpe']) && null !== $energyData['consumption']['hpe']) {
                $energy->setHpeConsumption($energyData['consumption']['hpe']);
            }

            if (isset($energyData['consumption']['hce']) && null !== $energyData['consumption']['hce']) {
                $energy->setHceConsumption($energyData['consumption']['hce']);
            }

            if (isset($energyData['consumption']['base']) && null !== $energyData['consumption']['base']) {
                $energy->setBaseConsumption($energyData['consumption']['base']);
            }

            if (isset($energyData['consumption']['hp']) && null !== $energyData['consumption']['hp']) {
                $energy->setHpConsumption($energyData['consumption']['hp']);
            }

            if (isset($energyData['consumption']['hc']) && null !== $energyData['consumption']['hc']) {
                $energy->setHcConsumption($energyData['consumption']['hc']);
            }

            if (isset($energyData['consumption']['total']) && null !== $energyData['consumption']['total']) {
                $energy->setTotalConsumption($energyData['consumption']['total']);
            }

            $manager->persist($energy);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $energy);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class,
            EnergyProviderFixtures::class,
            FtaFixtures::class,
        ];
    }
}
