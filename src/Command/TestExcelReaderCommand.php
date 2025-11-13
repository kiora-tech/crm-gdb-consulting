<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Import\Service\ExcelReaderService;
use App\Domain\Import\Service\FileStorageService;
use App\Repository\ImportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:test-excel',
    description: 'Test Excel reading',
)]
class TestExcelReaderCommand extends Command
{
    public function __construct(
        private readonly ImportRepository $importRepository,
        private readonly FileStorageService $fileStorage,
        private readonly ExcelReaderService $excelReader,
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

        try {
            $filePath = $this->fileStorage->getImportFilePath($import);
            $io->info(sprintf('File path: %s', $filePath));
            $io->info(sprintf('File exists: %s', file_exists($filePath) ? 'YES' : 'NO'));

            if (file_exists($filePath)) {
                $io->info(sprintf('File size: %d bytes', filesize($filePath)));
            }

            $io->section('Reading total rows...');
            $totalRows = $this->excelReader->getTotalRows($filePath);
            $io->success(sprintf('Total rows: %d', $totalRows));

            $io->section('Reading headers...');
            $headers = $this->excelReader->getHeaders($filePath);
            $io->success(sprintf('Headers: %s', implode(', ', $headers)));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Exception: '.$e->getMessage());
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
