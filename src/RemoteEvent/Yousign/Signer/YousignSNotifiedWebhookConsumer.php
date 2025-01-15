<?php

declare(strict_types=1);

namespace App\RemoteEvent\Yousign\Signer;

use App\Enum\YousignSignerStatus;
use App\Notifications\Yousign\RequestSignerNotification;
use App\RemoteEvent\Yousign\YousignEvent;
use App\Repository\ClientSigningDocumentRepository;
use App\Service\Yousign\YousignApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

final class YousignSNotifiedWebhookConsumer extends AbstractYousignSWebhookConsumer
{
    public function __construct(
        private readonly ClientSigningDocumentRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly NotifierInterface $notifier,
        private readonly YousignApiClient $yousignApiClient,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
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

        $signerLink = $this->getSignerLink($signatureRequestId);

        $documentName = $clientSigningDocument->getClientDocument()?->getPdfName();
        if (null === $documentName) {
            return;
        }

        $this->notifier->send(
            new RequestSignerNotification($documentName, $signerLink),
            new Recipient($signerEmail)
        );

        $clientSigningDocument->setSignerStatus($signerEmail, YousignSignerStatus::NOTIFIED);
        $this->em->flush();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     */
    private function getSignerLink(Uuid $signatureRequestId): string
    {
        $sPayload = $this->getSPayload();
        if (null === $sPayload || !array_key_exists('id', $sPayload) || !is_string($sPayload['id']) || !Uuid::isValid($sPayload['id'])) {
            throw new \Exception('Error getting signer : invalid signer link');
        }

        $id = Uuid::fromString($sPayload['id']);

        return $this->yousignApiClient->getSignerLink($signatureRequestId, $id);
    }
}
