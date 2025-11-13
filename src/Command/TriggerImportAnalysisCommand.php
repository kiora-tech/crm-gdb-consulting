<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Import\Service\ImportOrchestrator;
use App\Repository\ImportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:trigger-analysis',
    description: 'Trigger analysis for a pending import',
)]
class TriggerImportAnalysisCommand extends Command
{
    public function __construct(
        private readonly ImportRepository $importRepository,
        private readonly ImportOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('import-id', InputArgument::REQUIRED, 'Import ID to analyze');
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

        $io->info(sprintf('Triggering analysis for import #%d (%s)', $import->getId(), $import->getOriginalFilename()));

        try {
            $this->orchestrator->startAnalysis($import);
            $io->success('Analysis message dispatched successfully!');
            $io->note('Run the messenger worker to process the analysis: bin/console messenger:consume import_analysis -vv');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to trigger analysis: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
