<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint to validate that an Energy code/type/contractEnd combination is unique.
 * If a duplicate is found, the error message will include a link to the existing customer.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueEnergyCode extends Constraint
{
    public string $message = 'Ce code est déjà utilisé pour ce type d\'énergie avec cette date de fin de contrat. <a href="{{ customerUrl }}" class="text-blue-600 underline hover:text-blue-800" target="_blank">Voir le client {{ customerName }}</a>';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
