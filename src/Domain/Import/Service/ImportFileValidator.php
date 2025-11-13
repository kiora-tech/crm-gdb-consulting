<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service for validating uploaded import files.
 *
 * Performs comprehensive validation including MIME type checking,
 * file size verification, and file integrity validation to ensure
 * only valid Excel files are accepted for import.
 */
readonly class ImportFileValidator
{
    /**
     * Maximum allowed file size (50 MB in bytes).
     */
    private const int MAX_FILE_SIZE = 52428800; // 50 * 1024 * 1024

    /**
     * Allowed MIME types for Excel files.
     *
     * @var array<string>
     */
    private const array ALLOWED_MIME_TYPES = [
        'application/vnd.ms-excel',                                                         // .xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',              // .xlsx
        'application/vnd.oasis.opendocument.spreadsheet',                                 // .ods
        'application/octet-stream',                                                        // Generic binary (fallback)
    ];

    /**
     * Allowed file extensions.
     *
     * @var array<string>
     */
    private const array ALLOWED_EXTENSIONS = ['xls', 'xlsx', 'ods'];

    /**
     * Validate an uploaded file for import.
     *
     * Performs multiple validation checks:
     * - MIME type verification
     * - File size verification
     * - File integrity check (can be read as Excel)
     *
     * @param UploadedFile $file The uploaded file to validate
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(UploadedFile $file): void
    {
        $this->validateMimeType($file);
        $this->validateFileSize($file);
        $this->validateFileIntegrity($file);
    }

    /**
     * Validate that the file has an allowed MIME type.
     *
     * @param UploadedFile $file The uploaded file
     *
     * @throws \InvalidArgumentException If MIME type is not allowed
     */
    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        // Check MIME type
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Le type de fichier "%s" n\'est pas autorisé. Formats acceptés : %s', $mimeType ?? 'inconnu', implode(', ', ['.xls', '.xlsx', '.ods'])));
        }

        // Double-check extension as MIME types can be spoofed
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(sprintf('L\'extension de fichier ".%s" n\'est pas autorisée. Extensions acceptées : %s', $extension, implode(', ', array_map(fn ($ext) => '.'.$ext, self::ALLOWED_EXTENSIONS))));
        }
    }

    /**
     * Validate that the file size does not exceed the maximum allowed size.
     *
     * @param UploadedFile $file The uploaded file
     *
     * @throws \InvalidArgumentException If file size exceeds the limit
     */
    private function validateFileSize(UploadedFile $file): void
    {
        $fileSize = $file->getSize();

        if (false === $fileSize || $fileSize > self::MAX_FILE_SIZE) {
            $maxSizeMB = self::MAX_FILE_SIZE / 1024 / 1024;
            $fileSizeMB = false !== $fileSize ? round($fileSize / 1024 / 1024, 2) : 'inconnue';

            throw new \InvalidArgumentException(sprintf('La taille du fichier (%s MB) dépasse la limite autorisée de %d MB', $fileSizeMB, (int) $maxSizeMB));
        }
    }

    /**
     * Validate that the file can be read as a valid Excel file.
     *
     * Attempts to open the file with PhpSpreadsheet to verify its integrity.
     *
     * @param UploadedFile $file The uploaded file
     *
     * @throws \InvalidArgumentException If file cannot be read as Excel
     */
    private function validateFileIntegrity(UploadedFile $file): void
    {
        $filePath = $file->getPathname();

        if (empty($filePath) || !file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException('Impossible d\'accéder au fichier téléchargé');
        }

        try {
            // Try to create a reader for the file
            $reader = IOFactory::createReaderForFile($filePath);

            // Attempt to load the file (without reading all data)
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);

            // Verify it has at least one worksheet
            if (0 === $spreadsheet->getSheetCount()) {
                throw new \InvalidArgumentException('Le fichier Excel ne contient aucune feuille de calcul');
            }

            // Check that the first sheet has data
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestDataRow();

            if ($highestRow < 2) {
                throw new \InvalidArgumentException('Le fichier Excel doit contenir au moins une ligne d\'en-têtes et une ligne de données');
            }

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (\InvalidArgumentException $e) {
            // Re-throw our own validation exceptions
            throw $e;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Le fichier n\'est pas un fichier Excel valide ou est corrompu : %s', $e->getMessage()), 0, $e);
        }
    }
}
