<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\SignatureRequest;

use App\Enum\YousignSignatureRequestStatus;
use App\RemoteEvent\Yousign\YousignEvent;
use App\Repository\ClientSigningDocumentRepository;
use App\Service\Yousign\YousignApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class YousignSRDoneWebhookConsumer extends AbstractYousignSRWebhookConsumer
{
    public function __construct(
        private readonly ClientSigningDocumentRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly YousignApiService $yousignApiService,
    ) {
    }

    /**
     * @throws \Exception
     * @throws TransportExceptionInterface
     */
    protected function executeAction(YousignEvent $payload, Uuid $signatureRequestId): void
    {
        $this->checkSRPayloadStatus('done');

        $clientSigningDocument = $this->repository->find($signatureRequestId);
        if (null === $clientSigningDocument) {
            return;
        }

        $clientSigningDocument->setSignatureRequestStatus(YousignSignatureRequestStatus::DONE);
        $this->em->flush();

        $this->yousignApiService->downloadSignature($clientSigningDocument);
    }
}
