<?php

declare(strict_types=1);

namespace App\Domain\Import\Service\Processor;

use App\Entity\Import;

/**
 * Interface for import processors using the Strategy pattern.
 *
 * Each processor is responsible for handling a specific type of import
 * and processes batches of rows from the Excel file.
 */
interface ImportProcessorInterface
{
    /**
     * Process a batch of rows from the import file.
     *
     * This method processes multiple rows at once, handling customer creation/update,
     * entity relationships, and error tracking. Each processor is responsible for
     * updating the Import entity's metrics (processedRows, successRows, errorRows).
     *
     * @param Import                           $import The import entity being processed
     * @param array<int, array<string, mixed>> $rows   Array of rows to process (each row is an associative array)
     *
     * @throws \Exception If a critical error occurs during batch processing
     */
    public function processBatch(Import $import, array $rows): void;

    /**
     * Check if this processor supports the given import type.
     *
     * @param Import $import The import entity to check
     *
     * @return bool True if this processor can handle the import, false otherwise
     */
    public function supports(Import $import): bool;
}
