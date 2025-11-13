<?php

declare(strict_types=1);

namespace App\Domain\Import\ValueObject;

/**
 * Value object representing information about an uploaded import file.
 *
 * This immutable object encapsulates all relevant details about an uploaded file
 * that will be used for data import operations.
 */
readonly class ImportFileInfo
{
    /**
     * @param string $originalName   The original filename as uploaded by the user
     * @param string $storedPath     The absolute path where the file is stored on disk
     * @param string $storedFilename The unique filename generated for storage
     * @param int    $fileSize       The size of the file in bytes
     * @param string $mimeType       The MIME type of the file
     */
    public function __construct(
        public string $originalName,
        public string $storedPath,
        public string $storedFilename,
        public int $fileSize,
        public string $mimeType,
    ) {
    }

    /**
     * Get the file extension from the stored filename.
     */
    public function getExtension(): string
    {
        return pathinfo($this->storedFilename, PATHINFO_EXTENSION);
    }

    /**
     * Get the base filename without path.
     */
    public function getBasename(): string
    {
        return basename($this->storedPath);
    }

    /**
     * Check if the file is an Excel file.
     */
    public function isExcelFile(): bool
    {
        $excelMimeTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.spreadsheet',
        ];

        return in_array($this->mimeType, $excelMimeTypes, true);
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->fileSize;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            ++$unit;
        }

        return round($size, 2).' '.$units[$unit];
    }
}
