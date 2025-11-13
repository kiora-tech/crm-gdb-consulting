<?php

declare(strict_types=1);

namespace App\Entity;

enum ImportType: string
{
    case CUSTOMER = 'customer';
    case ENERGY = 'energy';
    case CONTACT = 'contact';
    case FULL = 'full';

    /**
     * Get human-readable label for the import type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Clients',
            self::ENERGY => 'Énergies',
            self::CONTACT => 'Contacts',
            self::FULL => 'Import complet',
        };
    }

    /**
     * Get the entity class name associated with this import type.
     *
     * @return class-string|null
     */
    public function getEntityClass(): ?string
    {
        return match ($this) {
            self::CUSTOMER => Customer::class,
            self::ENERGY => Energy::class,
            self::CONTACT => Contact::class,
            self::FULL => null, // Multiple entities
        };
    }
}
