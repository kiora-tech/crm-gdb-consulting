<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum EnergyType: string implements TranslatableInterface
{
    case ELEC = 'elec';
    case GAZ = 'gaz';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, locale:$locale);
    }
}
