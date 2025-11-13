<?php

declare(strict_types=1);

namespace App\Entity;

enum ImportErrorSeverity: string
{
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';

    /**
     * Get human-readable label for the severity level.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::WARNING => 'Avertissement',
            self::ERROR => 'Erreur',
            self::CRITICAL => 'Critique',
        };
    }
}
