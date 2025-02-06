<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ProspectStatus: string implements TranslatableInterface
{
    case WON = 'won';
    case LOST = 'lost';
    case IN_PROGRESS = 'in_progress';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, locale:$locale);
    }
}