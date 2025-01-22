<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\SignatureRequest;

use App\RemoteEvent\Yousign\AbstractYousignWebhookConsumer;

abstract class AbstractYousignSRWebhookConsumer extends AbstractYousignWebhookConsumer
{
    /**
     * @throws \Exception
     */
    protected function checkSRPayloadStatus(string $status): void
    {
        $srPayload = $this->getSRPayload();
        if (null === $srPayload || !array_key_exists('status', $srPayload) || $status !== $srPayload['status']) {
            throw new \Exception('Error checking signature request : invalid status');
        }
    }
}
