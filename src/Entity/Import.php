<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportRepository::class)]
#[ORM\Table(name: 'import')]
#[ORM\Index(columns: ['status'], name: 'idx_import_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_import_created_at')]
#[ORM\Index(columns: ['user_id'], name: 'idx_import_user_id')]
#[ORM\Index(columns: ['type'], name: 'idx_import_type')]
class Import
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $storedFilename;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: ImportStatus::class)]
    private ImportStatus $status;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: ImportType::class)]
    private ImportType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalRows = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $processedRows = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $successRows = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $errorRows = 0;

    /**
     * @var Collection<int, ImportError>
     */
    #[ORM\OneToMany(targetEntity: ImportError::class, mappedBy: 'import', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $errors;

    /**
     * @var Collection<int, ImportAnalysisResult>
     */
    #[ORM\OneToMany(targetEntity: ImportAnalysisResult::class, mappedBy: 'import', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $analysisResults;

    public function __construct()
    {
        $this->errors = new ArrayCollection();
        $this->analysisResults = new ArrayCollection();
        $this->status = ImportStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(string $storedFilename): self
    {
        $this->storedFilename = $storedFilename;

        return $this;
    }

    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    public function setStatus(ImportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): ImportType
    {
        return $this->type;
    }

    public function setType(ImportType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function setTotalRows(int $totalRows): self
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function setProcessedRows(int $processedRows): self
    {
        $this->processedRows = $processedRows;

        return $this;
    }

    public function getSuccessRows(): int
    {
        return $this->successRows;
    }

    public function setSuccessRows(int $successRows): self
    {
        $this->successRows = $successRows;

        return $this;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }

    public function setErrorRows(int $errorRows): self
    {
        $this->errorRows = $errorRows;

        return $this;
    }

    /**
     * @return Collection<int, ImportError>
     */
    public function getErrors(): Collection
    {
        return $this->errors;
    }

    public function addError(ImportError $error): self
    {
        if (!$this->errors->contains($error)) {
            $this->errors->add($error);
            $error->setImport($this);
        }

        return $this;
    }

    public function removeError(ImportError $error): self
    {
        if ($this->errors->removeElement($error)) {
            if ($error->getImport() === $this) {
                $error->setImport(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ImportAnalysisResult>
     */
    public function getAnalysisResults(): Collection
    {
        return $this->analysisResults;
    }

    public function addAnalysisResult(ImportAnalysisResult $analysisResult): self
    {
        if (!$this->analysisResults->contains($analysisResult)) {
            $this->analysisResults->add($analysisResult);
            $analysisResult->setImport($this);
        }

        return $this;
    }

    public function removeAnalysisResult(ImportAnalysisResult $analysisResult): self
    {
        if ($this->analysisResults->removeElement($analysisResult)) {
            if ($analysisResult->getImport() === $this) {
                $analysisResult->setImport(null);
            }
        }

        return $this;
    }

    // Status transition methods

    public function markAsAnalyzing(): self
    {
        $this->status = ImportStatus::ANALYZING;
        if (null === $this->startedAt) {
            $this->startedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function markAsAwaitingConfirmation(): self
    {
        $this->status = ImportStatus::AWAITING_CONFIRMATION;

        return $this;
    }

    public function markAsProcessing(): self
    {
        $this->status = ImportStatus::PROCESSING;
        if (null === $this->startedAt) {
            $this->startedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function markAsCompleted(): self
    {
        $this->status = ImportStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        return $this;
    }

    public function markAsFailed(): self
    {
        $this->status = ImportStatus::FAILED;
        $this->completedAt = new \DateTimeImmutable();

        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->status = ImportStatus::CANCELLED;
        $this->completedAt = new \DateTimeImmutable();

        return $this;
    }

    // Counter increment methods

    public function incrementProcessedRows(): self
    {
        ++$this->processedRows;

        return $this;
    }

    public function incrementSuccessRows(): self
    {
        ++$this->successRows;

        return $this;
    }

    public function incrementErrorRows(): self
    {
        ++$this->errorRows;

        return $this;
    }

    // Computed properties

    /**
     * Get the progress percentage (0-100).
     */
    public function getProgressPercentage(): float
    {
        if (0 === $this->totalRows) {
            return 0.0;
        }

        return round(($this->processedRows / $this->totalRows) * 100, 2);
    }

    /**
     * Get the duration of the import in seconds.
     */
    public function getDuration(): ?int
    {
        if (null === $this->startedAt) {
            return null;
        }

        $endTime = $this->completedAt ?? new \DateTimeImmutable();

        return $endTime->getTimestamp() - $this->startedAt->getTimestamp();
    }
}
