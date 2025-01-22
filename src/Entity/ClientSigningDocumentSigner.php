<?php

namespace App\Entity;

use App\Enum\YousignSignerStatus;
use App\Repository\ClientSigningDocumentSignerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientSigningDocumentSignerRepository::class)]
class ClientSigningDocumentSigner
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'clientSigningDocumentSigners')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $client = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'clientSigningDocumentSigners')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ClientSigningDocument $clientSigningDocument = null;

    #[ORM\Column(type: Types::STRING, length: 255, enumType: YousignSignerStatus::class)]
    private YousignSignerStatus $status = YousignSignerStatus::INITIATED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $declineReason = null;

    public function getId(): ?string
    {
        return $this->client?->getId().$this->clientSigningDocument?->getId();
    }

    public function getClient(): ?Contact
    {
        return $this->client;
    }

    public function setClient(?Contact $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getClientSigningDocument(): ?ClientSigningDocument
    {
        return $this->clientSigningDocument;
    }

    public function setClientSigningDocument(?ClientSigningDocument $clientSigningDocument): static
    {
        $this->clientSigningDocument = $clientSigningDocument;

        return $this;
    }

    public function getStatus(): ?YousignSignerStatus
    {
        return $this->status;
    }

    public function setStatus(YousignSignerStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    public function setDeclineReason(?string $declineReason): static
    {
        $this->declineReason = $declineReason;

        return $this;
    }
}
