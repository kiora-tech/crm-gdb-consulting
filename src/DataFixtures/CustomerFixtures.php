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
        // Customers for admin user
        $adminCustomers = ['Admin Client 1', 'Admin Client 2'];
        foreach ($adminCustomers as $customerName) {
            $customer = new Customer();
            $customer->setName($customerName);
            $customer->setOrigin(\App\Entity\ProspectOrigin::ACQUISITION);
            $customer->setUser($this->getReference('admin_user', User::class));
            $manager->persist($customer);
        }

        // Customers for regular user
        $userCustomers = ['User Client 1', 'User Client 2'];
        foreach ($userCustomers as $customerName) {
            $customer = new Customer();
            $customer->setName($customerName);
            $customer->setOrigin(\App\Entity\ProspectOrigin::ACQUISITION);
            $customer->setUser($this->getReference('regular_user', User::class));
            $manager->persist($customer);
        }

        // Unassigned customer
        $unassignedCustomer = new Customer();
        $unassignedCustomer->setName('Unassigned Client');
        $unassignedCustomer->setOrigin(\App\Entity\ProspectOrigin::ACQUISITION);
        $unassignedCustomer->setUser(null);
        $manager->persist($unassignedCustomer);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}