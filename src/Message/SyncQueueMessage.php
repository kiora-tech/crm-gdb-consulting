<?php

namespace App\Message;

/**
 * SyncQueueMessage represents a synchronization operation to be processed asynchronously.
 */
class SyncQueueMessage
{
    public function __construct(
        private array $syncData,
        private int $priority = 0,
        private int $attemptCount = 0,
        private ?\DateTime $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?? new \DateTime();
    }

    public function getSyncData(): array
    {
        return $this->syncData;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function incrementAttempt(): void
    {
        ++$this->attemptCount;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getEntityType(): ?string
    {
        return $this->syncData['entityType'] ?? null;
    }

    public function getOperation(): ?string
    {
        return $this->syncData['operation'] ?? null;
    }

    public function isHighPriority(): bool
    {
        return $this->priority > 5;
    }

    public function shouldRetry(): bool
    {
        // Max 3 attempts
        return $this->attemptCount < 3;
    }

    public function getRetryDelay(): int
    {
        // Progressive delay: 5s, 30s, 60s
        return match ($this->attemptCount) {
            0 => 5,
            1 => 30,
            2 => 60,
            default => 120,
        };
    }
}

