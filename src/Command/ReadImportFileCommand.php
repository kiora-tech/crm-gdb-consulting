<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Import\Service\ExcelReaderService;
use App\Repository\ImportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:read-import-file',
    description: 'Read the content of an import file',
)]
class ReadImportFileCommand extends Command
{
    public function __construct(
        private readonly ImportRepository $importRepository,
        private readonly ExcelReaderService $excelReader,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('importId', InputArgument::REQUIRED, 'Import ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $importId = (int) $input->getArgument('importId');

        $import = $this->importRepository->find($importId);
        if (!$import) {
            $io->error("Import #$importId not found");

            return Command::FAILURE;
        }

        $filePath = $this->projectDir.'/var/import/'.$import->getStoredFilename();

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");

            return Command::FAILURE;
        }

        $io->title("Contenu du fichier: {$import->getOriginalFilename()}");

        try {
            $rowNumber = 1;
            foreach ($this->excelReader->readRowsInBatches($filePath, 100) as $batch) {
                foreach ($batch as $row) {
                    ++$rowNumber;
                    $io->section("Ligne $rowNumber");
                    foreach ($row as $key => $value) {
                        if (null !== $value && '' !== $value) {
                            $io->writeln("  $key: $value");
                        }
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error reading file: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
