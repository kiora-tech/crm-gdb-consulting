<?php

declare(strict_types=1);

namespace App\Domain\Import\ValueObject;

/**
 * Value object representing the impact analysis of an import operation.
 *
 * This readonly class encapsulates the projected changes that would occur
 * if an import is executed, including creations, updates, skips, and errors.
 */
readonly class AnalysisImpact
{
    /**
     * @param array<string, int> $creations Array of entity types to number of new records to be created
     * @param array<string, int> $updates   Array of entity types to number of existing records to be updated
     * @param array<string, int> $skips     Array of entity types to number of records to be skipped
     * @param int                $totalRows Total number of rows in the import file
     * @param int                $errorRows Number of rows with errors
     */
    public function __construct(
        public array $creations,
        public array $updates,
        public array $skips,
        public int $totalRows,
        public int $errorRows,
    ) {
    }

    /**
     * Get the total number of records to be created across all entity types.
     */
    public function getTotalCreations(): int
    {
        return array_sum($this->creations);
    }

    /**
     * Get the total number of records to be updated across all entity types.
     */
    public function getTotalUpdates(): int
    {
        return array_sum($this->updates);
    }

    /**
     * Get the total number of records to be skipped across all entity types.
     */
    public function getTotalSkips(): int
    {
        return array_sum($this->skips);
    }

    /**
     * Check if the analysis contains any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errorRows > 0;
    }

    /**
     * Calculate the success rate of the import analysis.
     *
     * Returns the percentage of rows without errors (0.0 to 100.0).
     * Returns 100.0 if there are no rows to process.
     */
    public function getSuccessRate(): float
    {
        if (0 === $this->totalRows) {
            return 100.0;
        }

        $successRows = $this->totalRows - $this->errorRows;

        return round(($successRows / $this->totalRows) * 100, 2);
    }
}
