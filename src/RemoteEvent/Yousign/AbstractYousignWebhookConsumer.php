<?php

namespace App\RemoteEvent\Yousign;

use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Uid\Uuid;

abstract class AbstractYousignWebhookConsumer implements ConsumerInterface
{
    /**
     * @var mixed[]|null
     */
    private ?array $srPayload = null;
    /**
     * @var mixed[]|null
     */
    private ?array $sPayload = null;

    /**
     * @throws \Exception
     */
    public function consume(RemoteEvent $event): void
    {
        $payload = $event->getPayload();
        if (1 !== sizeof($payload)) {
            return;
        }

        $payload = $payload[0];
        if (!$payload instanceof YousignEvent) {
            return;
        }

        $signatureRequestId = $this->extractSRIdFromPayload($payload);
        $this->extractSFromPayload($payload);

        $this->executeAction($payload, $signatureRequestId);
    }

    /**
     * @throws \Exception
     */
    private function extractSRIdFromPayload(YousignEvent $payload): Uuid
    {
        if (!array_key_exists('signature_request', $payload->data)) {
            throw new \Exception('Error getting signature request : invalid signature_request');
        }

        $this->srPayload = $payload->data['signature_request'];
        if (!array_key_exists('id', $this->srPayload)) {
            throw new \Exception('Error getting signature request : invalid id');
        }
        $id = $this->srPayload['id'];

        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new \Exception('Error getting signature request : invalid id');
        }

        return Uuid::fromString($id);
    }

    /**
     * @throws \Exception
     */
    private function extractSFromPayload(YousignEvent $payload): void
    {
        if (!array_key_exists('signer', $payload->data)) {
            return;
        }

        $this->sPayload = $payload->data['signer'];
    }

    /**
     * @return mixed[]|null
     */
    protected function getSRPayload(): ?array
    {
        return $this->srPayload;
    }

    /**
     * @return mixed[]|null
     */
    protected function getSPayload(): ?array
    {
        return $this->sPayload;
    }

    abstract protected function executeAction(YousignEvent $payload, Uuid $signatureRequestId): void;
}
