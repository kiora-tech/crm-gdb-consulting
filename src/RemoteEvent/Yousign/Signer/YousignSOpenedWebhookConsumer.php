<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\Signer;

use App\Enum\YousignSignerStatus;
use App\RemoteEvent\Yousign\YousignEvent;
use App\Repository\ClientSigningDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class YousignSOpenedWebhookConsumer extends AbstractYousignSWebhookConsumer
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
        $this->checkSPayloadStatus('notified');

        $signerEmail = $this->getSignerEmail();

        $clientSigningDocument = $this->repository->find($signatureRequestId);
        if (null === $clientSigningDocument) {
            return;
        }

        $clientSigningDocument->setSignerStatus($signerEmail, YousignSignerStatus::PROCESSING);
        $this->em->flush();
    }
}
