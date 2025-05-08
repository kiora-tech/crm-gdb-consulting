<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ProspectOrigin: string implements TranslatableInterface
{
    case ACQUISITION = 'acquisition';
    case RENOUVELLEMENT = 'renouvellement';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, locale: $locale);
    }
}
