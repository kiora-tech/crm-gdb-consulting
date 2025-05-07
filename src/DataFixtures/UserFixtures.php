<?php

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setName('Admin');
        $admin->setLastname('User');
        $admin->setEmail('admin@test.com');
        $admin->setPassword($this->passwordHasher->hashPassword(
            $admin,
            'password'
        ));
        $admin->setCompany($this->getReference('company_1', Company::class));
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $manager->persist($admin);
        
        // Create regular user
        $user = new User();
        $user->setName('Regular');
        $user->setLastname('User');
        $user->setEmail('user@test.com');
        $user->setPassword($this->passwordHasher->hashPassword(
            $user,
            'password'
        ));
        $user->setCompany($this->getReference('company_1', Company::class));
        $user->setRoles(['ROLE_USER']);
        $manager->persist($user);
        
        $manager->flush();

        $this->setReference('admin_user', $admin);
        $this->setReference('regular_user', $user);
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }
}
