<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Import\Service\ImportProcessor;
use App\Repository\ImportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:test-process',
    description: 'Test import processing (DEBUG)',
)]
class TestProcessImportCommand extends Command
{
    public function __construct(
        private readonly ImportRepository $importRepository,
        private readonly ImportProcessor $processor,
        private readonly \Doctrine\ORM\EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('import-id', InputArgument::REQUIRED, 'Import ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $importId = (int) $input->getArgument('import-id');

        $import = $this->importRepository->find($importId);
        if (null === $import) {
            $io->error(sprintf('Import with ID %d not found', $importId));

            return Command::FAILURE;
        }

        $io->info(sprintf('Testing process for import #%d', $import->getId()));
        $io->info(sprintf('Status: %s', $import->getStatus()->value));

        try {
            $io->section('Calling processAsync()...');
            $this->processor->processAsync($import);

            // CRITICAL: Flush to persist dispatched messages
            $this->entityManager->flush();

            $io->success('processAsync() completed!');
            $io->info('EntityManager flushed to persist messages');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Exception: '.$e->getMessage());
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
