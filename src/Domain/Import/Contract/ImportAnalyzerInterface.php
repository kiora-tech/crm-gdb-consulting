<?php

declare(strict_types=1);

namespace App\Domain\Import\Contract;

use App\Domain\Import\ValueObject\AnalysisImpact;
use App\Entity\ImportType;

/**
 * Interface for import file analyzers.
 *
 * Analyzers examine uploaded files to determine what changes will be made
 * during import processing, providing users with preview information before
 * confirming the import operation.
 */
interface ImportAnalyzerInterface
{
    /**
     * Check if this analyzer supports the given import type.
     *
     * @param ImportType $type The import type to check
     *
     * @return bool True if this analyzer can handle the import type
     */
    public function supports(ImportType $type): bool;

    /**
     * Analyze the import file and determine the impact of processing it.
     *
     * This method should:
     * - Read and validate the file structure
     * - Count rows and estimate processing impact
     * - Identify potential issues or conflicts
     * - Return detailed analysis results
     *
     * @param string $filePath Absolute path to the import file
     * @param object $import   The Import entity being analyzed
     *
     * @return AnalysisImpact Analysis results describing the import impact
     *
     * @throws \RuntimeException If analysis fails
     */
    public function analyze(string $filePath, object $import): AnalysisImpact;
}
