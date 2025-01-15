<?php

declare(strict_types=1);

namespace App\Message;

final readonly class AskSignatureMessage
{
    public function __construct(
        public int $clientDocumentId,
    ) {
    }
}
