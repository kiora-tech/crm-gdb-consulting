<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Import\Service\ImportOrchestrator;
use App\Domain\Import\ValueObject\ImportFileInfo;
use App\Entity\ImportType;
use App\Repository\ImportRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;

#[AsCommand(
    name: 'app:test-import',
    description: 'Test import with a file',
)]
class TestImportCommand extends Command
{
    public function __construct(
        private readonly ImportOrchestrator $orchestrator,
        private readonly UserRepository $userRepository,
        private readonly ImportRepository $importRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the import file')
            ->addArgument('type', InputArgument::OPTIONAL, 'Import type (customer|energy|contact|full)', 'full')
            ->addArgument('userId', InputArgument::OPTIONAL, 'User ID', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = $input->getArgument('file');
        $typeValue = $input->getArgument('type');
        $userId = (int) $input->getArgument('userId');

        // Get user
        $user = $this->userRepository->find($userId);
        if (!$user) {
            $io->error("User with ID $userId not found");

            return Command::FAILURE;
        }

        // Validate file
        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");

            return Command::FAILURE;
        }

        // Parse import type
        $type = match ($typeValue) {
            'customer' => ImportType::CUSTOMER,
            'energy' => ImportType::ENERGY,
            'contact' => ImportType::CONTACT,
            'full' => ImportType::FULL,
            default => null,
        };

        if (!$type) {
            $io->error("Invalid import type: $typeValue");

            return Command::FAILURE;
        }

        $io->info("Starting import test...");
        $io->info("File: $filePath");
        $io->info("Type: {$type->value}");
        $io->info("User: {$user->getEmail()}");

        try {
            // Create ImportFileInfo object
            $file = new File($filePath);
            $filename = $file->getFilename();
            $fileInfo = new ImportFileInfo(
                originalName: $filename,
                storedPath: $filePath,
                storedFilename: $filename,
                fileSize: $file->getSize(),
                mimeType: $file->getMimeType() ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

            // Initialize import
            $io->section('1. Initializing import...');
            $import = $this->orchestrator->initializeImport($fileInfo, $type, $user);
            $io->success("Import #{$import->getId()} created");

            // Start analysis
            $io->section('2. Starting analysis...');
            $this->orchestrator->startAnalysis($import);
            $io->success('Analysis dispatched');

            // Wait a bit for analysis to complete
            $io->info('Waiting for analysis to complete (5 seconds)...');
            sleep(5);

            // Refresh import to get latest status
            $this->entityManager->refresh($import);
            $io->info("Import status: {$import->getStatus()->value}");
            $io->info("Total rows: {$import->getTotalRows()}");

            if ($import->getStatus()->canBeProcessed()) {
                // Confirm and process
                $io->section('3. Confirming and processing...');
                $this->orchestrator->confirmAndProcess($import);
                $io->success('Processing dispatched');

                // Wait for processing to complete
                $io->info('Waiting for processing to complete (10 seconds)...');
                sleep(10);

                // Refresh again
                $this->entityManager->refresh($import);
                $io->info("Final status: {$import->getStatus()->value}");
                $io->info("Success rows: {$import->getSuccessRows()}");
                $io->info("Error rows: {$import->getErrorRows()}");
            } else {
                $io->warning("Import cannot be processed (status: {$import->getStatus()->value})");
            }

            $io->success("Import test completed! Import ID: {$import->getId()}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Import test failed: {$e->getMessage()}");
            $io->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
