<?php

declare(strict_types=1);

namespace App\Entity;

enum ImportOperationType: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case SKIP = 'skip';

    /**
     * Get human-readable label for the operation type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CREATE => 'Création',
            self::UPDATE => 'Mise à jour',
            self::SKIP => 'Ignoré',
        };
    }
}
