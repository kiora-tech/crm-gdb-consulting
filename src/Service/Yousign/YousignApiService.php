<?php

namespace App\Service\Yousign;

use App\Entity\ClientDocument;
use App\Entity\ClientSigningDocument;
use App\Entity\ClientSigningDocumentSigner;
use App\Enum\YousignSignatureRequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class YousignApiService
{
    public function __construct(
        #[Autowire('%base_upload_directory%')]
        private string $projectDir,
        private YousignApiClient $yousignApiClient,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function askForSignature(ClientDocument $clientDocument): void
    {
        $signatureRequestId = $this->yousignApiClient->createSignatureRequest($clientDocument);

        $name = (string) $clientDocument->getName();
        $pdfPath = $this->projectDir.'/'.$clientDocument->getPdfPath();
        $pdfName = $clientDocument->getPdfName();
        $documentId = $this->yousignApiClient->addDocumentToSignatureRequest($signatureRequestId, $pdfPath, $pdfName);

        $clientSigningDocument = new ClientSigningDocument($signatureRequestId);
        $clientSigningDocument->setClientDocument($clientDocument);
        $this->em->persist($clientSigningDocument);
        $this->em->flush();

        foreach ($clientDocument->getClient() as $client) {
            $this->yousignApiClient->addSignerToSignatureRequest($signatureRequestId, $documentId, $clientDocument, $client);

            $signer = new ClientSigningDocumentSigner();
            $signer->setClient($client);
            $clientSigningDocument->addClientSigningDocumentSigner($signer);

            $this->em->flush();
        }

        $this->yousignApiClient->activateSignatureRequest($signatureRequestId);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function cancelSignature(ClientSigningDocument $clientSigningDocument): bool
    {
        if (!in_array($clientSigningDocument->getSignatureRequestStatus(), [
            YousignSignatureRequestStatus::DRAFT,
            YousignSignatureRequestStatus::ONGOING,
        ])) {
            return false;
        }

        $signatureRequestId = $clientSigningDocument->getId();

        if (null === $signatureRequestId) {
            throw new \RuntimeException('Invalid signature request id');
        }

        $this->yousignApiClient->cancelSignatureRequest($signatureRequestId);

        $clientSigningDocument->setSignatureRequestStatus(YousignSignatureRequestStatus::CANCELED);
        $this->em->flush();

        return true;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function downloadSignature(ClientSigningDocument $clientSigningDocument): void
    {
        if (YousignSignatureRequestStatus::DONE !== $clientSigningDocument->getSignatureRequestStatus()) {
            throw new \RuntimeException('Invalid signature request status');
        }

        if ($clientSigningDocument->isDownloaded()) {
            throw new \RuntimeException('Document already downloaded');
        }

        $signatureRequestId = $clientSigningDocument->getId();

        if (null === $signatureRequestId) {
            throw new \RuntimeException('Invalid signature request id');
        }

        $this->yousignApiClient->downloadSignatureRequest($this->projectDir, $signatureRequestId, $clientSigningDocument);

        $clientSigningDocument->setDownloaded(true);
        $this->em->flush();
    }
}
