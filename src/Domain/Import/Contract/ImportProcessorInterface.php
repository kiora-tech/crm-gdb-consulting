<?php

declare(strict_types=1);

namespace App\Domain\Import\Contract;

use App\Entity\ImportType;

/**
 * Interface for import batch processors.
 *
 * Processors handle the actual data import operation, reading batches of rows
 * from files and persisting them to the database. Each processor is responsible
 * for a specific import type (e.g., customers, contacts, etc.).
 */
interface ImportProcessorInterface
{
    /**
     * Check if this processor supports the given import type.
     *
     * @param ImportType $type The import type to check
     *
     * @return bool True if this processor can handle the import type
     */
    public function supports(ImportType $type): bool;

    /**
     * Process a batch of rows from the import file.
     *
     * This method should:
     * - Validate each row's data
     * - Transform data to appropriate entity format
     * - Create/update entities in the database
     * - Track errors for invalid rows
     * - Update import progress counters
     *
     * All database operations should be performed within a transaction
     * managed by the caller (MessageHandler).
     *
     * Note: Despite the type hint being 'object', implementations should expect
     * an App\Entity\Import instance. The generic type hint allows for interface
     * flexibility while maintaining type safety through runtime assertions.
     *
     * @param array<int, array<string, mixed>> $rows   Batch of rows to process
     * @param \App\Entity\Import               $import The Import entity being processed
     *
     * @throws \RuntimeException If processing fails critically
     */
    public function processBatch(array $rows, \App\Entity\Import $import): void;
}
