<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Import\Service\Processor\CustomerImportProcessor;
use App\Entity\Import;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-nested-transaction-fix',
    description: 'Test the nested transaction fix for customer import',
)]
class TestNestedTransactionFixCommand extends Command
{
    public function __construct(
        private readonly CustomerImportProcessor $processor,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get user
        $user = $this->userRepository->find(29);
        if (!$user) {
            $io->error('User with ID 29 not found');

            return Command::FAILURE;
        }

        $io->title('Test du correctif des transactions imbriquées');

        // Create a test import
        $import = new Import();
        $import->setUser($user);
        $import->setType(ImportType::FULL);
        $import->setOriginalFilename('test_nested_transaction.xlsx');
        $import->setStoredFilename('test_nested_transaction.xlsx');
        $import->setTotalRows(3);
        $import->setStatus(ImportStatus::PROCESSING);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        $io->success("Import #{$import->getId()} créé");

        // Test data (from the actual file)
        $testRows = [
            [
                'name' => 'TWO-PARIS',
                'siret' => '82048024200016',
                'lead_origin' => 'Apport partenaire',
            ],
            [
                'name' => 'SAS SALLES Dominique',
                'siret' => '42896071000021',
                'lead_origin' => 'Apport partenaire',
            ],
            [
                'name' => 'EARL TIRE-PIED',
                'siret' => '41892325600014',
                'lead_origin' => 'Apport partenaire',
            ],
        ];

        $io->section('Traitement des données avec le correctif appliqué...');

        try {
            // Process batch with our fixed processor
            $this->processor->processBatch($testRows, $import);

            $io->success('Traitement terminé sans erreur !');
            $io->newLine();

            // Check the database for created customers
            $io->section('Vérification des clients créés en base de données :');

            $sirets = array_column($testRows, 'siret');
            $connection = $this->entityManager->getConnection();

            $placeholders = implode(', ', array_fill(0, count($sirets), '?'));
            $sql = "SELECT id, name, siret FROM customer WHERE siret IN ($placeholders)";
            $customers = $connection->fetchAllAssociative($sql, $sirets);

            if (count($customers) === 3) {
                $io->success('✅ SUCCÈS ! Les 3 clients ont été créés en base de données :');
                foreach ($customers as $customer) {
                    $io->writeln("  - [{$customer['siret']}] {$customer['name']}");
                }
                $io->newLine();
                $io->writeln('<fg=green;options=bold>🎉 Le correctif fonctionne ! Les données sont bien persistées.</>');
            } else {
                $io->error('❌ ÉCHEC ! Seulement '.count($customers).' client(s) créé(s) au lieu de 3');
                foreach ($customers as $customer) {
                    $io->writeln("  - [{$customer['siret']}] {$customer['name']}");
                }
                $io->newLine();
                $io->writeln('<fg=red;options=bold>⚠️  Le bug persiste. Les données ne sont pas persistées correctement.</>');
            }

            // Update import status
            $import->setSuccessRows(count($customers));
            $import->setErrorRows(3 - count($customers));
            $import->setStatus(count($customers) === 3 ? ImportStatus::COMPLETED : ImportStatus::FAILED);
            $this->entityManager->flush();

            return count($customers) === 3 ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Erreur lors du traitement : '.$e->getMessage());
            $io->error($e->getTraceAsString());

            $import->setStatus(ImportStatus::FAILED);
            $this->entityManager->flush();

            return Command::FAILURE;
        }
    }
}
