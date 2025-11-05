<?php

declare(strict_types=1);

namespace App\Message;

class RefreshMicrosoftEventsMessage
{
    public function __construct(
        private readonly int $userId,
        private readonly string $calendarId,
        private readonly \DateTimeInterface $startDate,
        private readonly \DateTimeInterface $endDate,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }
}
