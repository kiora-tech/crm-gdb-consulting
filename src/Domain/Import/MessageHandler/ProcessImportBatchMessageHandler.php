<?php

declare(strict_types=1);

namespace App\Domain\Import\MessageHandler;

use App\Domain\Import\Contract\ImportProcessorInterface;
use App\Domain\Import\Message\ProcessImportBatchMessage;
use App\Domain\Import\Service\ExcelReaderService;
use App\Domain\Import\Service\FileStorageService;
use App\Domain\Import\Service\ImportNotifier;
use App\Entity\ImportStatus;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for processing batches of import rows.
 *
 * This handler processes ProcessImportBatchMessage messages to import batches
 * of data from uploaded files. Each batch is processed in a transaction,
 * with progress tracking and error handling.
 */
#[AsMessageHandler]
readonly class ProcessImportBatchMessageHandler
{
    /**
     * @param ImportRepository                   $importRepository Repository for Import entities
     * @param FileStorageService                 $fileStorage      Service for file operations
     * @param ExcelReaderService                 $excelReader      Service for reading Excel files
     * @param iterable<ImportProcessorInterface> $processors       Tagged collection of processor implementations
     * @param ImportNotifier                     $notifier         Service for sending notifications
     * @param EntityManagerInterface             $entityManager    Doctrine entity manager
     * @param LoggerInterface                    $logger           Logger for tracking operations
     */
    public function __construct(
        private ImportRepository $importRepository,
        private FileStorageService $fileStorage,
        private ExcelReaderService $excelReader,
        private iterable $processors,
        private ImportNotifier $notifier,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handle the processing of an import batch.
     *
     * @param ProcessImportBatchMessage $message The message containing batch information
     */
    public function __invoke(ProcessImportBatchMessage $message): void
    {
        $import = $this->importRepository->find($message->getImportId());

        if (null === $import) {
            $this->logger->error('Import non trouvé pour le traitement du lot', [
                'import_id' => $message->getImportId(),
            ]);

            return;
        }

        // Verify status is PROCESSING
        if (ImportStatus::PROCESSING !== $import->getStatus()) {
            $this->logger->warning('L\'import n\'est pas dans le statut PROCESSING', [
                'import_id' => $import->getId(),
                'status' => $import->getStatus()->value,
            ]);

            return;
        }

        $this->logger->info('Démarrage du traitement du lot', [
            'import_id' => $import->getId(),
            'start_row' => $message->getStartRow(),
            'end_row' => $message->getEndRow(),
        ]);

        try {
            // Get file path
            $filePath = $this->fileStorage->getImportFilePath($import);

            // Find appropriate processor
            $processor = $this->findProcessor($import->getType());

            if (null === $processor) {
                throw new \RuntimeException(sprintf('Aucun processeur trouvé pour le type d\'import "%s"', $import->getType()->value));
            }

            // Start transaction
            $this->entityManager->beginTransaction();

            try {
                // Read batch rows
                $batchRows = $this->readBatchRows($filePath, $message->getStartRow(), $message->getEndRow());

                // Process the batch
                $processor->processBatch($batchRows, $import);

                // Commit transaction
                $this->entityManager->commit();

                $this->logger->info('Lot traité avec succès', [
                    'import_id' => $import->getId(),
                    'start_row' => $message->getStartRow(),
                    'end_row' => $message->getEndRow(),
                    'rows_count' => count($batchRows),
                    'processed_rows' => $import->getProcessedRows(),
                    'total_rows' => $import->getTotalRows(),
                ]);

                // Check if import is complete
                if ($import->getProcessedRows() >= $import->getTotalRows()) {
                    $import->markAsCompleted();
                    $this->entityManager->flush();

                    // Send completion notification
                    try {
                        $this->notifier->notifyProcessingComplete($import);
                    } catch (\Throwable $notificationError) {
                        $this->logger->error('Échec de l\'envoi de la notification de fin', [
                            'import_id' => $import->getId(),
                            'error' => $notificationError->getMessage(),
                        ]);
                    }

                    $this->logger->info('Import terminé avec succès', [
                        'import_id' => $import->getId(),
                        'total_rows' => $import->getTotalRows(),
                        'success_rows' => $import->getSuccessRows(),
                        'error_rows' => $import->getErrorRows(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Rollback transaction on error
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                }

                throw $e;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Échec du traitement du lot', [
                'import_id' => $import->getId(),
                'start_row' => $message->getStartRow(),
                'end_row' => $message->getEndRow(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Log the error but don't fail the entire import
            // Individual row errors should be tracked by the processor
            // Only re-throw if it's a critical failure
            if ($e instanceof \RuntimeException) {
                // Mark import as failed for critical errors
                $import->markAsFailed();
                $this->entityManager->flush();

                // Send failure notification
                try {
                    $this->notifier->notifyFailure($import);
                } catch (\Throwable $notificationError) {
                    $this->logger->error('Échec de l\'envoi de la notification d\'échec', [
                        'import_id' => $import->getId(),
                        'error' => $notificationError->getMessage(),
                    ]);
                }

                throw $e;
            }
        }
    }

    /**
     * Find the appropriate processor for the given import type.
     *
     * @param \App\Entity\ImportType $type The import type
     *
     * @return ImportProcessorInterface|null The processor that supports the type, or null if none found
     */
    private function findProcessor(\App\Entity\ImportType $type): ?ImportProcessorInterface
    {
        foreach ($this->processors as $processor) {
            if ($processor->supports($type)) {
                return $processor;
            }
        }

        return null;
    }

    /**
     * Read a specific batch of rows from the Excel file.
     *
     * @param string $filePath The absolute path to the Excel file
     * @param int    $startRow Starting row number (1-based, inclusive)
     * @param int    $endRow   Ending row number (1-based, inclusive)
     *
     * @return array<int, array<string, mixed>> Array of rows in the batch
     */
    private function readBatchRows(string $filePath, int $startRow, int $endRow): array
    {
        $batchRows = [];
        $currentRow = 1; // Start at 1 (data rows start after header)

        // Use the generator to read rows efficiently
        foreach ($this->excelReader->readRowsInBatches($filePath, 100) as $batch) {
            foreach ($batch as $row) {
                ++$currentRow;

                // Skip rows before our start
                if ($currentRow < $startRow) {
                    continue;
                }

                // Add row if it's in our range
                if ($currentRow >= $startRow && $currentRow <= $endRow) {
                    $batchRows[] = $row;
                }

                // Stop if we've reached the end
                if ($currentRow >= $endRow) {
                    break 2; // Break out of both foreach loops
                }
            }
        }

        return $batchRows;
    }
}
