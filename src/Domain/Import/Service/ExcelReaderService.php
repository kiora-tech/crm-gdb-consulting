<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/**
 * Service for reading Excel files in a memory-efficient manner.
 *
 * Uses PhpSpreadsheet with streaming techniques to handle large files
 * without consuming excessive memory. Provides batch reading capabilities
 * for processing rows in chunks.
 */
readonly class ExcelReaderService
{
    /**
     * Default batch size for reading rows.
     */
    private const int BATCH_SIZE = 100;

    /**
     * Read rows from an Excel file in batches.
     *
     * This method uses a generator to yield batches of rows, allowing
     * memory-efficient processing of large files. Each batch contains
     * an array of associative arrays where keys are column headers.
     *
     * @param string $filePath  Absolute path to the Excel file
     * @param int    $batchSize Number of rows per batch (default: 100)
     *
     * @return \Generator<int, array<int, array<string, mixed>>> Generator yielding batches of rows
     *
     * @throws \InvalidArgumentException If file does not exist or is not readable
     * @throws ReaderException           If file cannot be read as Excel
     */
    public function readRowsInBatches(string $filePath, int $batchSize = self::BATCH_SIZE): \Generator
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier "%s" n\'existe pas', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier "%s" n\'est pas lisible', $filePath));
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Exception $e) {
            throw new ReaderException(sprintf('Impossible de lire le fichier Excel "%s": %s', $filePath, $e->getMessage()), 0, $e);
        }

        try {
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            // Read headers from first row
            $headers = [];
            $headerRow = $worksheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0];
            foreach ($headerRow as $index => $header) {
                $headers[$index] = null !== $header ? (string) $header : 'column_'.$index;
            }

            // Process rows in batches (skip header row)
            $batch = [];
            $batchCount = 0;

            for ($row = 2; $row <= $highestRow; ++$row) {
                $rowData = $worksheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, false)[0];

                // Convert to associative array using headers
                $associativeRow = [];
                foreach ($headers as $index => $header) {
                    $associativeRow[$header] = $rowData[$index] ?? null;
                }

                $batch[] = $associativeRow;
                ++$batchCount;

                // Yield batch when it reaches the batch size
                if ($batchCount >= $batchSize) {
                    yield $batch;
                    $batch = [];
                    $batchCount = 0;
                }
            }

            // Yield remaining rows
            if (!empty($batch)) {
                yield $batch;
            }
        } finally {
            // Free memory by disconnecting worksheets
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * Get the total number of data rows in an Excel file (excluding header).
     *
     * @param string $filePath Absolute path to the Excel file
     *
     * @return int Total number of data rows
     *
     * @throws \InvalidArgumentException If file does not exist or is not readable
     * @throws ReaderException           If file cannot be read as Excel
     */
    public function getTotalRows(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier "%s" n\'existe pas', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier "%s" n\'est pas lisible', $filePath));
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Exception $e) {
            throw new ReaderException(sprintf('Impossible de lire le fichier Excel "%s": %s', $filePath, $e->getMessage()), 0, $e);
        }

        try {
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            // Count only non-empty rows (excluding header)
            $actualRowCount = 0;
            for ($row = 2; $row <= $highestRow; ++$row) {
                $rowData = $worksheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, false)[0];

                // Check if row has any non-empty cell
                $hasData = false;
                foreach ($rowData as $cellValue) {
                    if (null !== $cellValue && '' !== trim((string) $cellValue)) {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData) {
                    ++$actualRowCount;
                }
            }

            return $actualRowCount;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * Get the header row from an Excel file.
     *
     * @param string $filePath Absolute path to the Excel file
     *
     * @return array<int, string> Array of column headers
     *
     * @throws \InvalidArgumentException If file does not exist or is not readable
     * @throws ReaderException           If file cannot be read as Excel
     */
    public function getHeaders(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier "%s" n\'existe pas', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException(sprintf('Le fichier "%s" n\'est pas lisible', $filePath));
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Exception $e) {
            throw new ReaderException(sprintf('Impossible de lire le fichier Excel "%s": %s', $filePath, $e->getMessage()), 0, $e);
        }

        try {
            $worksheet = $spreadsheet->getActiveSheet();
            $highestColumn = $worksheet->getHighestDataColumn();

            // Read headers from first row
            $headerRow = $worksheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0];
            $headers = [];

            foreach ($headerRow as $index => $header) {
                $headers[] = null !== $header ? (string) $header : 'column_'.$index;
            }

            return $headers;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }
}
