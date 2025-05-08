<?php

namespace App\DataFixtures;

use App\Entity\Company;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CompanyFixtures extends Fixture
{
    private const COMPANIES = [
        'company_1' => 'Consulting GDB',
        'company_2' => 'Énergie Solutions',
        'company_3' => 'Green Power SAS',
        'company_4' => 'OptimÉnergie',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::COMPANIES as $reference => $name) {
            $company = new Company();
            $company->setName($name);

            $manager->persist($company);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $company);
        }

        $manager->flush();
    }
}
