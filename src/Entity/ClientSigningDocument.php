<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\YousignSignatureRequestStatus;
use App\Enum\YousignSignerStatus;
use App\Repository\ClientSigningDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ClientSigningDocumentRepository::class)]
class ClientSigningDocument
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $id;

    #[ORM\OneToOne(inversedBy: 'clientSigningDocument', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Document $clientDocument = null;

    #[ORM\Column(type: Types::STRING, length: 255, enumType: YousignSignatureRequestStatus::class)]
    private YousignSignatureRequestStatus $signatureRequestStatus = YousignSignatureRequestStatus::DRAFT;

    /**
     * @var ArrayCollection<int, ClientSigningDocumentSigner>
     */
    #[ORM\OneToMany(targetEntity: ClientSigningDocumentSigner::class, mappedBy: 'clientSigningDocument', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $clientSigningDocumentSigners;

    #[ORM\Column]
    private bool $downloaded = false;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id;
        $this->clientSigningDocumentSigners = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->clientDocument;
    }

    public function setDocument(Document $clientDocument): static
    {
        $this->clientDocument = $clientDocument;

        return $this;
    }

    public function getSignatureRequestStatus(): YousignSignatureRequestStatus
    {
        return $this->signatureRequestStatus;
    }

    public function setSignatureRequestStatus(YousignSignatureRequestStatus $signatureRequestStatus): static
    {
        $this->signatureRequestStatus = $signatureRequestStatus;

        return $this;
    }

    /**
     * @return Collection<int, ClientSigningDocumentSigner>
     */
    public function getClientSigningDocumentSigners(): Collection
    {
        return $this->clientSigningDocumentSigners;
    }

    public function addClientSigningDocumentSigner(ClientSigningDocumentSigner $clientSigningDocumentSigner): static
    {
        if (!$this->clientSigningDocumentSigners->contains($clientSigningDocumentSigner)) {
            $this->clientSigningDocumentSigners->add($clientSigningDocumentSigner);
            $clientSigningDocumentSigner->setClientSigningDocument($this);
        }

        return $this;
    }

    public function removeClientSigningDocumentSigner(ClientSigningDocumentSigner $clientSigningDocumentSigner): static
    {
        if ($this->clientSigningDocumentSigners->removeElement($clientSigningDocumentSigner)) {
            // set the owning side to null (unless already changed)
            if ($clientSigningDocumentSigner->getClientSigningDocument() === $this) {
                $clientSigningDocumentSigner->setClientSigningDocument(null);
            }
        }

        return $this;
    }

    public function setSignerStatus(string $signerEmail, YousignSignerStatus $status, ?string $reason = null): static
    {
        $element = $this->clientSigningDocumentSigners->findFirst(
            fn (int $key, ClientSigningDocumentSigner $signer) => $signer->getClient()?->getEmail() === $signerEmail
        );

        if ($element instanceof ClientSigningDocumentSigner) {
            $element->setStatus($status);
            $element->setDeclineReason($reason);
        }

        return $this;
    }

    public function isDownloaded(): bool
    {
        return $this->downloaded;
    }

    public function setDownloaded(bool $downloaded): static
    {
        $this->downloaded = $downloaded;

        return $this;
    }
}
