<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CanalSignature: string implements TranslatableInterface
{
    case COURTIER = 'courtier';
    case FOURNISSEUR = 'fournisseur';
    case GDB = 'gdb';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->value, locale: $locale);
    }
}
