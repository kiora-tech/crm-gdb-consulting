<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager): void
    {
        $customers = ['kiora', 'digdeo'];

        foreach ($customers as $customerName) {
            $customer = new Customer();
            $customer->setName($customerName);
            $customer->setUser($this->getReference('user_1', User::class));
            $manager->persist($customer);
        }
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}