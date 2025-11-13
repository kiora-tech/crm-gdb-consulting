<?php

declare(strict_types=1);

namespace App\Entity;

enum ImportStatus: string
{
    case PENDING = 'pending';
    case ANALYZING = 'analyzing';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Check if this status is terminal (no further processing possible).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }

    /**
     * Check if import can be processed from this status.
     */
    public function canBeProcessed(): bool
    {
        return self::AWAITING_CONFIRMATION === $this;
    }

    /**
     * Check if import can be cancelled from this status.
     */
    public function canBeCancelled(): bool
    {
        return match ($this) {
            self::PENDING, self::ANALYZING, self::AWAITING_CONFIRMATION, self::PROCESSING => true,
            default => false,
        };
    }

    /**
     * Get human-readable label for the status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ANALYZING => 'Analyse en cours',
            self::AWAITING_CONFIRMATION => 'En attente de confirmation',
            self::PROCESSING => 'Traitement en cours',
            self::COMPLETED => 'Terminé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
        };
    }
}
