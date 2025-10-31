<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HtmlExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('html_entity_decode', [$this, 'htmlEntityDecode']),
        ];
    }

    public function htmlEntityDecode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
