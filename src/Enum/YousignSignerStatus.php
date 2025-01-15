<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

enum YousignSignerStatus: string implements EnumLabelInterface, TranslatableInterface
{
    case INITIATED = 'initiated'; // The Signer has been added to a Signature Request and is waiting to be notified to sign.
    case NOTIFIED = 'notified'; // The Signer has been notified to sign. A Magic Link has been sent or can be retrieved.
    case PROCESSING = 'processing'; // The Signer is able to sign the document thanks to the slider and Yousign is processing its signature to the document.
    case DECLINED = 'declined'; // The signer declined to sign the Signature Request.
    case SIGNED = 'signed'; // Yousign internal process is successful and the signature has been created on the document.
    case ABORTED = 'aborted'; // The Signature Request has been cancelled due to a signature error.
    case ERROR = 'error'; // An internal error occurred during signature creation on the document.

    public function getLabel(): string
    {
        return match ($this) {
            self::INITIATED => (string) t('yousign.signer_status.initiated'),
            self::NOTIFIED => (string) t('yousign.signer_status.notified'),
            self::PROCESSING => (string) t('yousign.signer_status.processing'),
            self::DECLINED => (string) t('yousign.signer_status.declined'),
            self::SIGNED => (string) t('yousign.signer_status.signed'),
            self::ABORTED => (string) t('yousign.signer_status.aborted'),
            self::ERROR => (string) t('yousign.signer_status.error'),
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::INITIATED => $translator->trans('yousign.signer_status.initiated', locale: $locale),
            self::NOTIFIED => $translator->trans('yousign.signer_status.notified', locale: $locale),
            self::PROCESSING => $translator->trans('yousign.signer_status.processing', locale: $locale),
            self::DECLINED => $translator->trans('yousign.signer_status.declined', locale: $locale),
            self::SIGNED => $translator->trans('yousign.signer_status.signed', locale: $locale),
            self::ABORTED => $translator->trans('yousign.signer_status.aborted', locale: $locale),
            self::ERROR => $translator->trans('yousign.signer_status.error', locale: $locale),
        };
    }
}
