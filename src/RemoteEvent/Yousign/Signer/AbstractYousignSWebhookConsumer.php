<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\Signer;

use App\RemoteEvent\Yousign\AbstractYousignWebhookConsumer;

abstract class AbstractYousignSWebhookConsumer extends AbstractYousignWebhookConsumer
{
    /**
     * @throws \Exception
     */
    protected function checkSPayloadStatus(string $status): void
    {
        $sPayload = $this->getSPayload();
        if (null === $sPayload || !array_key_exists('status', $sPayload) || $status !== $sPayload['status']) {
            throw new \Exception('Error checking signature : invalid status');
        }
    }

    /**
     * @throws \Exception
     */
    protected function getSignerEmail(): string
    {
        $sPayload = $this->getSPayload();
        if (null === $sPayload) {
            throw new \Exception('Error getting signer : invalid signer');
        }

        return $this->getEmailFromSPayload($sPayload);
    }

    /**
     * @param mixed[] $sPayload
     *
     * @throws \Exception
     */
    private function getEmailFromSPayload(array $sPayload): string
    {
        if (!array_key_exists('info', $sPayload) || !is_array($sPayload['info'])) {
            throw new \Exception('Error getting signer : invalid signer info');
        }

        $signerInfo = $sPayload['info'];
        if (!array_key_exists('email', $signerInfo) || !is_string($signerInfo['email']) || empty($signerInfo['email'])) {
            throw new \Exception('Error getting signer : invalid signer email');
        }

        return $signerInfo['email'];
    }
}
