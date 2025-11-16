<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportErrorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportErrorRepository::class)]
#[ORM\Table(name: 'import_error')]
#[ORM\Index(columns: ['import_id'], name: 'idx_import_error_import_id')]
#[ORM\Index(columns: ['severity'], name: 'idx_import_error_severity')]
class ImportError
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Import::class, inversedBy: 'errors')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Import $import = null;

    #[ORM\Column(name: '`row_number`', type: Types::INTEGER)]
    private int $rowNumber;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: ImportErrorSeverity::class)]
    private ImportErrorSeverity $severity;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $fieldName = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rowData = null;

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

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(int $rowNumber): self
    {
        $this->rowNumber = $rowNumber;

        return $this;
    }

    public function getSeverity(): ImportErrorSeverity
    {
        return $this->severity;
    }

    public function setSeverity(ImportErrorSeverity $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(?string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRowData(): ?array
    {
        return $this->rowData;
    }

    /**
     * @param array<string, mixed>|null $rowData
     */
    public function setRowData(?array $rowData): self
    {
        $this->rowData = $rowData;

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
