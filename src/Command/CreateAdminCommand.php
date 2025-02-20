<?php

namespace App\Command;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create:admin',
    description: 'Create the first admin user.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // check if a user already exists
        if ($this->em->getRepository(User::class)->findOneBy([])) {
            $io->error('An admin user already exists.');

            return Command::FAILURE;
        }

        $company = new Company();
        $companyName = $io->ask('Company name', 'Kiora');
        if (!is_string($companyName)) {
            throw new \RuntimeException('Invalid company name');
        }

        $company->setName($companyName);

        $user = new User();
        $email = $io->ask('Email', validator: function ($value) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid email');
            }

            return $value;
        });

        if (!is_string($email)) {
            throw new \RuntimeException('Invalid email');
        }
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $password = $io->askHidden('Password');
        if (!is_string($password)) {
            throw new \RuntimeException('Invalid password');
        }
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setCompany($company);
        $name = $io->ask('First name');
        if (!is_string($name)) {
            throw new \RuntimeException('Invalid first name');
        }

        $user->setName($name);

        $lastName = $io->ask('Last name');
        if (!is_string($lastName)) {
            throw new \RuntimeException('Invalid last name');
        }
        $user->setLastName($lastName);

        $this->em->persist($company);
        $this->em->persist($user);
        $this->em->flush();

        return Command::SUCCESS;
    }
}
