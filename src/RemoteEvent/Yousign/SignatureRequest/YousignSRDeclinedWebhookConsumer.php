<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\SignatureRequest;

use App\Enum\YousignSignatureRequestStatus;
use App\RemoteEvent\Yousign\YousignEvent;
use App\Repository\ClientSigningDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class YousignSRDeclinedWebhookConsumer extends AbstractYousignSRWebhookConsumer
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
        $this->checkSRPayloadStatus('declined');

        $clientSigningDocument = $this->repository->find($signatureRequestId);
        if (null === $clientSigningDocument) {
            return;
        }

        $clientSigningDocument->setSignatureRequestStatus(YousignSignatureRequestStatus::DECLINED);
        $this->em->flush();
    }
}
