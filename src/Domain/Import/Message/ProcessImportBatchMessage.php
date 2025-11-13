<?php

declare(strict_types=1);

namespace App\Domain\Import\Message;

/**
 * Message to trigger processing of a batch of rows from an import.
 *
 * This message is dispatched asynchronously to process a chunk of rows
 * from the import file, allowing parallel processing of large imports.
 */
readonly class ProcessImportBatchMessage
{
    /**
     * @param int $importId The ID of the Import entity
     * @param int $startRow First row index of this batch (1-based, excluding header)
     * @param int $endRow   Last row index of this batch (1-based, inclusive)
     */
    public function __construct(
        private int $importId,
        private int $startRow,
        private int $endRow,
    ) {
    }

    public function getImportId(): int
    {
        return $this->importId;
    }

    public function getStartRow(): int
    {
        return $this->startRow;
    }

    public function getEndRow(): int
    {
        return $this->endRow;
    }
}
