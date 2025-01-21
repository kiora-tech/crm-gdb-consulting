<?php

namespace App\MessageHandler;

use App\Message\AskSignatureMessage;
use App\Repository\DocumentRepository;
use App\Service\Yousign\YousignApiService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
final readonly class AskSignatureMessageHandler
{
    public function __construct(
        private YousignApiService $yousignApiService,
        private DocumentRepository $clientDocumentRepository,
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function __invoke(AskSignatureMessage $message): void
    {
        $clientDocument = $this->clientDocumentRepository->find($message->clientDocumentId);

        if (null === $clientDocument) {
            return;
        }

        $this->yousignApiService->askForSignature($clientDocument);
    }
}
