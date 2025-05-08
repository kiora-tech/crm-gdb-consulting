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
    private const USERS = [
        'admin_user' => [
            'name' => 'Admin',
            'lastname' => 'User',
            'email' => 'admin@test.com',
            'password' => 'password',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            'company' => 'company_1',
        ],
        'regular_user' => [
            'name' => 'Regular',
            'lastname' => 'User',
            'email' => 'user@test.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'company' => 'company_1',
        ],
        'manager_user' => [
            'name' => 'Jean',
            'lastname' => 'Dupont',
            'email' => 'jean.dupont@test.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'company' => 'company_2',
        ],
        'sales_user' => [
            'name' => 'Marie',
            'lastname' => 'Martin',
            'email' => 'marie.martin@test.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'company' => 'company_2',
        ],
        'advisor_user' => [
            'name' => 'Pierre',
            'lastname' => 'Leclerc',
            'email' => 'pierre.leclerc@test.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'company' => 'company_3',
        ],
        'support_user' => [
            'name' => 'Sophie',
            'lastname' => 'Bernard',
            'email' => 'sophie.bernard@test.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'company' => 'company_4',
        ],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $reference => $userData) {
            $user = new User();
            $user->setName($userData['name']);
            $user->setLastName($userData['lastname']);
            $user->setEmail($userData['email']);
            $user->setPassword($this->passwordHasher->hashPassword(
                $user,
                $userData['password']
            ));
            $user->setCompany($this->getReference($userData['company'], Company::class));
            $user->setRoles($userData['roles']);

            $manager->persist($user);

            // Ajouter une référence pour utilisation ultérieure
            $this->addReference($reference, $user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyFixtures::class,
        ];
    }
}
