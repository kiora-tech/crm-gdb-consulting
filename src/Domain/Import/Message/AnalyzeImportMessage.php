<?php

declare(strict_types=1);

namespace App\Domain\Import\Message;

/**
 * Message to trigger asynchronous analysis of an uploaded import file.
 *
 * This message is dispatched when an import file has been uploaded and needs
 * to be analyzed to determine its impact before processing. The analysis
 * examines the file structure, validates data, and estimates the changes
 * that would be made during processing.
 */
readonly class AnalyzeImportMessage
{
    /**
     * @param int $importId The ID of the Import entity to analyze
     */
    public function __construct(
        public int $importId,
    ) {
    }
}
