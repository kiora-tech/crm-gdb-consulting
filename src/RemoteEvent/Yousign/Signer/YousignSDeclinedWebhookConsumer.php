<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\Signer;

use App\Enum\YousignSignerStatus;
use App\RemoteEvent\Yousign\YousignEvent;
use App\Repository\ClientSigningDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class YousignSDeclinedWebhookConsumer extends AbstractYousignSWebhookConsumer
{
    public function __construct(
        private readonly ClientSigningDocumentRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws \Exception
     */
    protected function executeAction(YousignEvent $payload, Uuid $signatureRequestId): void
    {
        $this->checkSPayloadStatus('declined');

        $signerEmail = $this->getSignerEmail();

        $clientSigningDocument = $this->repository->find($signatureRequestId);
        if (null === $clientSigningDocument) {
            return;
        }

        $reason = $this->getReason($payload);

        $clientSigningDocument->setSignerStatus($signerEmail, YousignSignerStatus::DECLINED, $reason);
        $this->em->flush();
    }

    /**
     * @throws \Exception
     */
    private function getReason(YousignEvent $payload): string
    {
        if (!array_key_exists('reason', $payload->data) || !is_string($payload->data['reason'])) {
            throw new \Exception('Error checking reason : invalid reason');
        }

        return $payload->data['reason'];
    }
}
