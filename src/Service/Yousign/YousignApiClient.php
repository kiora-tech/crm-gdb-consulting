<?php

namespace App\Service\Yousign;

use App\Entity\Client;
use App\Entity\Contact;
use App\Entity\Document;
use App\Entity\ClientSigningDocument;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class YousignApiClient
{
    public function __construct(
        private HttpClientInterface $yousignClient,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     * @throws DecodingExceptionInterface
     */
    public function createSignatureRequest(Document $clientDocument): Uuid
    {
        $externalId = $clientDocument->getId();
        $name = $clientDocument->getPath();

        $response = $this->yousignClient->request(
            'POST',
            'signature_requests',
            [
                'body' => <<<JSON
                      {
                        "name": "{$name}",
                        "delivery_mode": "none",
                        "timezone": "Europe/Paris",
                        "external_id": "{$externalId}",
                        "signers_allowed_to_decline": true
                      }
                      JSON,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        if (201 !== $response->getStatusCode()) {
            throw new \Exception('Error creating signature request');
        }

        $data = $response->toArray();

        if (!array_key_exists('status', $data) || 'draft' !== $data['status']) {
            throw new \Exception('Error creating signature request : invalid status');
        }

        if (!array_key_exists('id', $data)) {
            throw new \Exception('Error creating signature request : invalid id');
        }
        $id = $data['id'];
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new \Exception('Error creating signature request : invalid id');
        }

        return Uuid::fromString($id);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws \Exception
     */
    public function addDocumentToSignatureRequest(Uuid $signatureRequestId, string $filePath, string $fileName): Uuid
    {
        $formFields = [
            'nature' => 'signable_document',
            'file' => DataPart::fromPath($filePath, $fileName, 'application/pdf'),
            'parse_anchors' => 'true',
        ];

        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();

        $response = $this->yousignClient->request(
            'POST',
            sprintf('signature_requests/%s/documents', $signatureRequestId),
            [
                'headers' => $headers,
                'body' => $formData->bodyToString(),
            ]
        );

        if (201 !== $response->getStatusCode()) {
            throw new \Exception('Error adding document to signature request');
        }

        $data = $response->toArray();

        if (!array_key_exists('id', $data)) {
            throw new \Exception('Error creating signature request : invalid id');
        }
        $id = $data['id'];
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new \Exception('Error creating signature request : invalid id');
        }

        return Uuid::fromString($id);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws \Exception
     */
    public function addSignerToSignatureRequest(Uuid $signatureRequestId, Uuid $documentId, Document $clientDocument, Contact $client): void
    {
        $response = $this->yousignClient->request(
            'POST',
            sprintf('signature_requests/%s/signers', $signatureRequestId),
            [
                'body' => <<<JSON
                       {
                           "info":{
                              "first_name": "{$client->getFirstName()}",
                              "last_name": "{$client->getLastName()}",
                              "email": "{$client->getEmail()}",
                              "locale":"fr"
                           },
                           "signature_level":"electronic_signature",
                           "signature_authentication_mode":"no_otp"
                       }
                       JSON,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

        if (201 !== $response->getStatusCode()) {
            throw new \Exception('Error adding signer to signature request');
        }

        $data = $response->toArray();

        if (!array_key_exists('id', $data)) {
            throw new \Exception('Error creating signature request : invalid id');
        }
        $id = $data['id'];
        if (!is_string($id) || !Uuid::isValid($id)) {
            throw new \Exception('Error creating signature request : invalid id');
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     * @throws DecodingExceptionInterface
     */
    public function activateSignatureRequest(Uuid $signatureRequestId): void
    {
        $response = $this->yousignClient->request(
            'POST',
            sprintf('signature_requests/%s/activate', $signatureRequestId)
        );

        if (201 !== $response->getStatusCode()) {
            throw new \Exception('Error activating signature request');
        }

        $data = $response->toArray();

        if (!array_key_exists('status', $data) || 'ongoing' !== $data['status']) {
            throw new \Exception('Error activating signature request : invalid status');
        }

        if (!array_key_exists('id', $data)) {
            throw new \Exception('Error creating signature request : invalid id');
        }
        $id = $data['id'];

        if (!is_string($id) || !Uuid::isValid($id) || $id !== (string) $signatureRequestId) {
            throw new \Exception('Error creating signature request : invalid id');
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     * @throws DecodingExceptionInterface
     */
    public function getSignerLink(Uuid $signatureRequestId, Uuid $signerId): string
    {
        $response = $this->yousignClient->request(
            'GET',
            sprintf('signature_requests/%s/signers/%s', $signatureRequestId, $signerId)
        );

        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Error getting signer link');
        }

        $data = $response->toArray();

        if (!array_key_exists('signature_link', $data) || !is_string($data['signature_link']) || empty($data['signature_link'])) {
            throw new \Exception('Error getting signer link : invalid signature link');
        }

        return $data['signature_link'];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \Exception
     * @throws DecodingExceptionInterface
     */
    public function cancelSignatureRequest(Uuid $signatureRequestId): void
    {
        $response = $this->yousignClient->request(
            'POST',
            sprintf('signature_requests/%s/cancel', $signatureRequestId),
            [
                'body' => <<<JSON
                       {
                           "reason": "contractualization_aborted"
                       }
                       JSON,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

        if (201 !== $response->getStatusCode()) {
            throw new \Exception('Error canceling signature request');
        }

        $data = $response->toArray();

        if (!array_key_exists('status', $data) || 'canceled' !== $data['status']) {
            throw new \Exception('Error canceling signature request : invalid status');
        }

        if (!array_key_exists('id', $data)) {
            throw new \Exception('Error canceling signature request : invalid id');
        }
        $id = $data['id'];

        if (!is_string($id) || !Uuid::isValid($id) || $id !== (string) $signatureRequestId) {
            throw new \Exception('Error canceling signature request : invalid id');
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \Exception
     */
    public function downloadSignatureRequest(string $projectDir, Uuid $signatureRequestId, ClientSigningDocument $signingDocument): void
    {
        $response = $this->yousignClient->request(
            'GET',
            sprintf('signature_requests/%s/documents/download?version=completed', $signatureRequestId),
            [
                'headers' => [
                    'Content-Type' => 'application/pdf',
                ],
            ]);

        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Error canceling signature request');
        }

        $stream = $this->yousignClient->stream($response);

        $pdfPath = $signingDocument->getDocument()?->getPath();
        if (null === $pdfPath) {
            throw new \Exception('Error downloading signature request : invalid pdf path');
        }

        $fileHandler = fopen($projectDir.'/'.$pdfPath, 'w');
        if (false === $fileHandler) {
            throw new \Exception('Error downloading signature request : invalid file handler');
        }

        foreach ($stream as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }

        fclose($fileHandler);
    }
}
