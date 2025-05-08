<?php

namespace App\DataFixtures;

use App\Entity\Fta;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FtaFixtures extends Fixture
{
    private const FTAS = [
        'fta_standard' => [
            'label' => 'Tarif Standard',
            'fixedCost' => 120.0,
            'powerReservationPeak' => 5.5,
            'powerReservationHPH' => 4.5,
            'powerReservationHCH' => 3.5,
            'powerReservationHPE' => 4.0,
            'powerReservationHCE' => 3.0,
            'consumptionPeak' => 0.18,
            'consumptionHPH' => 0.15,
            'consumptionHCH' => 0.10,
            'consumptionHPE' => 0.12,
            'consumptionHCE' => 0.08,
        ],
        'fta_business' => [
            'label' => 'Tarif Entreprise',
            'fixedCost' => 180.0,
            'powerReservationPeak' => 5.0,
            'powerReservationHPH' => 4.2,
            'powerReservationHCH' => 3.2,
            'powerReservationHPE' => 3.8,
            'powerReservationHCE' => 2.8,
            'consumptionPeak' => 0.17,
            'consumptionHPH' => 0.14,
            'consumptionHCH' => 0.09,
            'consumptionHPE' => 0.11,
            'consumptionHCE' => 0.07,
        ],
        'fta_eco' => [
            'label' => 'Tarif Éco',
            'fixedCost' => 100.0,
            'powerReservationPeak' => 6.0,
            'powerReservationHPH' => 5.0,
            'powerReservationHCH' => 4.0,
            'powerReservationHPE' => 4.5,
            'powerReservationHCE' => 3.5,
            'consumptionPeak' => 0.19,
            'consumptionHPH' => 0.16,
            'consumptionHCH' => 0.11,
            'consumptionHPE' => 0.13,
            'consumptionHCE' => 0.09,
        ],
        'fta_premium' => [
            'label' => 'Tarif Premium',
            'fixedCost' => 250.0,
            'powerReservationPeak' => 4.5,
            'powerReservationHPH' => 3.8,
            'powerReservationHCH' => 2.8,
            'powerReservationHPE' => 3.5,
            'powerReservationHCE' => 2.5,
            'consumptionPeak' => 0.16,
            'consumptionHPH' => 0.13,
            'consumptionHCH' => 0.08,
            'consumptionHPE' => 0.10,
            'consumptionHCE' => 0.06,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::FTAS as $reference => $ftaData) {
            $fta = new Fta();
            $fta->setLabel($ftaData['label']);
            $fta->setFixedCost($ftaData['fixedCost']);
            $fta->setPowerReservationPeak($ftaData['powerReservationPeak']);
            $fta->setPowerReservationHPH($ftaData['powerReservationHPH']);
            $fta->setPowerReservationHCH($ftaData['powerReservationHCH']);
            $fta->setPowerReservationHPE($ftaData['powerReservationHPE']);
            $fta->setPowerReservationHCE($ftaData['powerReservationHCE']);
            $fta->setConsumptionPeak($ftaData['consumptionPeak']);
            $fta->setConsumptionHPH($ftaData['consumptionHPH']);
            $fta->setConsumptionHCH($ftaData['consumptionHCH']);
            $fta->setConsumptionHPE($ftaData['consumptionHPE']);
            $fta->setConsumptionHCE($ftaData['consumptionHCE']);

            $manager->persist($fta);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $fta);
        }

        $manager->flush();
    }
}
