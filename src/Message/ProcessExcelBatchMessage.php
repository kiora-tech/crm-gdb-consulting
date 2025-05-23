<?php

namespace App\Message;

readonly class ProcessExcelBatchMessage
{
    /**
     * @param string                   $filePath         Chemin vers le fichier Excel
     * @param int                      $startRow         Ligne de début pour ce lot
     * @param int                      $endRow           Ligne de fin pour ce lot
     * @param array<int|string, mixed> $headerRow        En-tête du fichier Excel
     * @param string|null              $originalFilename Nom du fichier original (pour le rapport d'erreurs)
     */
    public function __construct(
        private string $filePath,
        private int $startRow,
        private int $endRow,
        private int $userId,
        private array $headerRow = [],
        private ?string $originalFilename = null,
    ) {
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getStartRow(): int
    {
        return $this->startRow;
    }

    public function getEndRow(): int
    {
        return $this->endRow;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getHeaderRow(): array
    {
        return $this->headerRow;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }
}
