<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign;

use Symfony\Component\Uid\Uuid;

readonly class YousignEvent
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        public Uuid $eventId,
        public string $eventName,
        public \DateTimeImmutable $eventTime,
        public array $data,
    ) {
    }
}
