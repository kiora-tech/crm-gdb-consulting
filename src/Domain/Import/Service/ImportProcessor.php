<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use App\Domain\Import\Message\ProcessImportBatchMessage;
use App\Entity\Import;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestrates the import processing by dispatching batch messages.
 *
 * This service is responsible for coordinating the asynchronous processing
 * of imports by breaking them into batches and dispatching messages to
 * be handled by worker processes.
 */
readonly class ImportProcessor
{
    /**
     * Number of rows to process per batch.
     */
    private const int BATCH_SIZE = 100;

    public function __construct(
        private MessageBusInterface $messageBus,
        private ExcelReaderService $excelReaderService,
        private FileStorageService $fileStorageService,
    ) {
    }

    /**
     * Start asynchronous processing of an import.
     *
     * Calculates the total number of rows and dispatches ProcessImportBatchMessage
     * for each batch to be processed by workers.
     *
     * @param Import $import The import entity to process
     *
     * @throws \InvalidArgumentException If the import file cannot be read
     * @throws \RuntimeException         If there is an error dispatching messages
     */
    public function processAsync(Import $import): void
    {
        // Get the file path
        $filePath = $this->fileStorageService->getImportFilePath($import);

        // Calculate total rows
        $totalRows = $this->excelReaderService->getTotalRows($filePath);
        $import->setTotalRows($totalRows);

        // If no rows to process, mark as completed
        if (0 === $totalRows) {
            $import->markAsCompleted();

            return;
        }

        // Mark import as processing
        $import->markAsProcessing();

        // Dispatch batch messages
        // Rows are 1-based (row 1 is header, row 2+ is data)
        $importId = $import->getId();

        if (null === $importId) {
            throw new \RuntimeException('L\'import doit être persisté avant le traitement');
        }

        $currentRow = 2; // Start at row 2 (after header)
        $lastRow = $totalRows + 1; // Total rows + 1 for header

        while ($currentRow <= $lastRow) {
            $endRow = min($currentRow + self::BATCH_SIZE - 1, $lastRow);

            $message = new ProcessImportBatchMessage(
                importId: $importId,
                startRow: $currentRow,
                endRow: $endRow
            );

            $this->messageBus->dispatch($message);

            $currentRow = $endRow + 1;
        }
    }

    /**
     * Get the batch size used for processing.
     */
    public function getBatchSize(): int
    {
        return self::BATCH_SIZE;
    }
}
