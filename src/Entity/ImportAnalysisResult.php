<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportAnalysisResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportAnalysisResultRepository::class)]
#[ORM\Table(name: 'import_analysis_result')]
#[ORM\Index(columns: ['import_id'], name: 'idx_import_analysis_result_import_id')]
#[ORM\Index(columns: ['operation_type'], name: 'idx_import_analysis_result_operation_type')]
class ImportAnalysisResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Import::class, inversedBy: 'analysisResults')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Import $import = null;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: ImportOperationType::class)]
    private ImportOperationType $operationType;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::INTEGER)]
    private int $count = 0;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImport(): ?Import
    {
        return $this->import;
    }

    public function setImport(?Import $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getOperationType(): ImportOperationType
    {
        return $this->operationType;
    }

    public function setOperationType(ImportOperationType $operationType): self
    {
        $this->operationType = $operationType;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function setDetails(?array $details): self
    {
        $this->details = $details;

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
}
