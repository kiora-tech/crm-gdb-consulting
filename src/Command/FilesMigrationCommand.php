<?php

namespace App\Command;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:migrate-files',
    description: 'Migrate files from local filesystem to MinIO storage',
)]
class FilesMigrationCommand extends Command
{
    public function __construct(
        private readonly FilesystemOperator $documentsStorage,
        private readonly FilesystemOperator $templatesStorage,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without actual file migration')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'File type to migrate (documents, templates, all)', 'all')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $type = $input->getOption('type');

        $io->title('Starting file migration from local filesystem to MinIO storage');

        if ($dryRun) {
            $io->warning('Running in dry-run mode. No files will be migrated.');
        }

        if ('all' === $type || 'documents' === $type) {
            $this->migrateFiles(
                $io,
                $this->documentsStorage,
                $this->projectDir.'/public/uploads/documents',
                'documents',
                $dryRun
            );
        }

        if ('all' === $type || 'templates' === $type) {
            $this->migrateFiles(
                $io,
                $this->templatesStorage,
                $this->projectDir.'/public/uploads/templates',
                'templates',
                $dryRun
            );
        }

        $io->success('File migration completed successfully!');

        return Command::SUCCESS;
    }

    private function migrateFiles(
        SymfonyStyle $io,
        FilesystemOperator $storage,
        string $sourcePath,
        string $type,
        bool $dryRun,
    ): void {
        if (!is_dir($sourcePath)) {
            $io->warning(sprintf('Source directory %s does not exist. Skipping.', $sourcePath));

            return;
        }

        $io->section(sprintf('Migrating %s files', $type));

        $finder = new Finder();
        $finder->files()->in($sourcePath);

        $totalFiles = count($finder);
        $io->progressStart($totalFiles);
        $migratedCount = 0;

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $content = file_get_contents($file->getRealPath());

            if (!$dryRun) {
                $storage->write($relativePath, $content);
            }

            ++$migratedCount;
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->info(sprintf('%s %s files %s',
            $dryRun ? 'Would migrate' : 'Migrated',
            $migratedCount,
            $dryRun ? 'if not in dry-run mode' : 'successfully'
        ));
    }
}
