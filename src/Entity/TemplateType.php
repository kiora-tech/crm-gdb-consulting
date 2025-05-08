<?php

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum TemplateType: string implements TranslatableInterface
{
    /**
     * @var array<string, self>
     */
    public const array MIME_TYPE_MAPPING = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::EXCEL,
        'application/vnd.ms-excel' => self::EXCEL,
        'application/msword' => self::DOCUMENT,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::DOCUMENT,
        'application/rtf' => self::DOCUMENT,
    ];

    case EXCEL = 'excel';
    case DOCUMENT = 'document';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('template.type.'.$this->value, locale: $locale);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromMimeType(string $mimeType): self
    {
        if (!isset(self::MIME_TYPE_MAPPING[$mimeType])) {
            throw new \InvalidArgumentException('Unsupported file type: '.$mimeType);
        }

        return self::MIME_TYPE_MAPPING[$mimeType];
    }
}
