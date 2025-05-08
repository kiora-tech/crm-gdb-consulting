<?php

namespace App\Message;

readonly class StartImportMessage
{
    public function __construct(
        private string $filePath,
        private int $userId,
        private string $originalFilename,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }
}
