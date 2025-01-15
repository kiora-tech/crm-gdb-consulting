<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

enum YousignSignatureRequestStatus: string implements EnumLabelInterface, TranslatableInterface
{
    case DRAFT = 'draft'; // The signature has not been activated yet.
    case ONGOING = 'ongoing'; // The Signature Request has been activated.
    case DECLINED = 'declined'; // A signer has declined the Signature Request.
    case EXPIRED = 'expired'; // The Signature Request expiration date has passed. An expired Signature Request can be reactivated. The default validity period is 6 months from the activation.
    case DONE = 'done'; // The Signature Request is done. All Signers signed the document.
    case DELETED = 'deleted'; // The Signature Request has been deleted. All Signature Requests can be deleted except those with an “Approval” and “Ongoing” status. A deleted Signature Request can be reactivated from the application.
    case CANCELED = 'canceled'; // The Signature Request has been canceled. A canceled Signature Request can't be reactivated. A Signature Request needs to have the "Approval" or "Ongoing" status to be canceled.

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => (string) t('yousign.signature_request_status.draft'),
            self::ONGOING => (string) t('yousign.signature_request_status.ongoing'),
            self::DECLINED => (string) t('yousign.signature_request_status.declined'),
            self::EXPIRED => (string) t('yousign.signature_request_status.expired'),
            self::DONE => (string) t('yousign.signature_request_status.done'),
            self::DELETED => (string) t('yousign.signature_request_status.deleted'),
            self::CANCELED => (string) t('yousign.signature_request_status.canceled'),
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DRAFT => $translator->trans('yousign.signature_request_status.draft', locale: $locale),
            self::ONGOING => $translator->trans('yousign.signature_request_status.ongoing', locale: $locale),
            self::DECLINED => $translator->trans('yousign.signature_request_status.declined', locale: $locale),
            self::EXPIRED => $translator->trans('yousign.signature_request_status.expired', locale: $locale),
            self::DONE => $translator->trans('yousign.signature_request_status.done', locale: $locale),
            self::DELETED => $translator->trans('yousign.signature_request_status.deleted', locale: $locale),
            self::CANCELED => $translator->trans('yousign.signature_request_status.canceled', locale: $locale),
        };
    }
}
