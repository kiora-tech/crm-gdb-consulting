<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Company;
use App\Entity\Import;
use App\Entity\ImportAnalysisResult;
use App\Entity\ImportError;
use App\Entity\ImportErrorSeverity;
use App\Entity\ImportOperationType;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ImportTest extends TestCase
{
    private Import $import;

    protected function setUp(): void
    {
        parent::setUp();
        $this->import = new Import();
    }

    public function testConstructorInitializesDefaultValues(): void
    {
        // Arrange & Act
        $import = new Import();

        // Assert
        $this->assertSame(ImportStatus::PENDING, $import->getStatus());
        // Verify created date is recent (within last minute) - type enforced by return type
        $this->assertLessThan(60, (new \DateTimeImmutable())->getTimestamp() - $import->getCreatedAt()->getTimestamp());
        $this->assertCount(0, $import->getErrors());
        $this->assertCount(0, $import->getAnalysisResults());
        $this->assertSame(0, $import->getTotalRows());
        $this->assertSame(0, $import->getProcessedRows());
        $this->assertSame(0, $import->getSuccessRows());
        $this->assertSame(0, $import->getErrorRows());
    }

    public function testMarkAsAnalyzingChangesStatusAndSetsStartedAt(): void
    {
        // Arrange
        $this->assertNull($this->import->getStartedAt());

        // Act
        $result = $this->import->markAsAnalyzing();

        // Assert
        $this->assertSame($this->import, $result); // Fluent interface
        $this->assertSame(ImportStatus::ANALYZING, $this->import->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->import->getStartedAt());
    }

    public function testMarkAsAnalyzingDoesNotOverwriteExistingStartedAt(): void
    {
        // Arrange
        $initialStartedAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $this->import->setStartedAt($initialStartedAt);

        // Act
        $this->import->markAsAnalyzing();

        // Assert
        $this->assertSame($initialStartedAt, $this->import->getStartedAt());
    }

    public function testMarkAsAwaitingConfirmationChangesStatus(): void
    {
        // Act
        $result = $this->import->markAsAwaitingConfirmation();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(ImportStatus::AWAITING_CONFIRMATION, $this->import->getStatus());
    }

    public function testMarkAsProcessingChangesStatusAndSetsStartedAt(): void
    {
        // Arrange
        $this->assertNull($this->import->getStartedAt());

        // Act
        $result = $this->import->markAsProcessing();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(ImportStatus::PROCESSING, $this->import->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->import->getStartedAt());
    }

    public function testMarkAsCompletedChangesStatusAndSetsCompletedAt(): void
    {
        // Arrange
        $this->assertNull($this->import->getCompletedAt());

        // Act
        $result = $this->import->markAsCompleted();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(ImportStatus::COMPLETED, $this->import->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->import->getCompletedAt());
    }

    public function testMarkAsFailedChangesStatusAndSetsCompletedAt(): void
    {
        // Arrange
        $this->assertNull($this->import->getCompletedAt());

        // Act
        $result = $this->import->markAsFailed();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(ImportStatus::FAILED, $this->import->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->import->getCompletedAt());
    }

    public function testMarkAsCancelledChangesStatusAndSetsCompletedAt(): void
    {
        // Arrange
        $this->assertNull($this->import->getCompletedAt());

        // Act
        $result = $this->import->markAsCancelled();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(ImportStatus::CANCELLED, $this->import->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->import->getCompletedAt());
    }

    public function testIncrementProcessedRowsIncrementsCounter(): void
    {
        // Arrange
        $this->assertSame(0, $this->import->getProcessedRows());

        // Act
        $result = $this->import->incrementProcessedRows();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(1, $this->import->getProcessedRows());

        // Act again
        $this->import->incrementProcessedRows();

        // Assert
        $this->assertSame(2, $this->import->getProcessedRows());
    }

    public function testIncrementSuccessRowsIncrementsCounter(): void
    {
        // Arrange
        $this->assertSame(0, $this->import->getSuccessRows());

        // Act
        $result = $this->import->incrementSuccessRows();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(1, $this->import->getSuccessRows());
    }

    public function testIncrementErrorRowsIncrementsCounter(): void
    {
        // Arrange
        $this->assertSame(0, $this->import->getErrorRows());

        // Act
        $result = $this->import->incrementErrorRows();

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertSame(1, $this->import->getErrorRows());
    }

    public function testGetProgressPercentageWithNoTotalRows(): void
    {
        // Arrange
        $this->import->setTotalRows(0);
        $this->import->setProcessedRows(0);

        // Act
        $percentage = $this->import->getProgressPercentage();

        // Assert
        $this->assertSame(0.0, $percentage);
    }

    public function testGetProgressPercentageWithPartialProgress(): void
    {
        // Arrange
        $this->import->setTotalRows(100);
        $this->import->setProcessedRows(25);

        // Act
        $percentage = $this->import->getProgressPercentage();

        // Assert
        $this->assertSame(25.0, $percentage);
    }

    public function testGetProgressPercentageWithCompleteProgress(): void
    {
        // Arrange
        $this->import->setTotalRows(100);
        $this->import->setProcessedRows(100);

        // Act
        $percentage = $this->import->getProgressPercentage();

        // Assert
        $this->assertSame(100.0, $percentage);
    }

    public function testGetProgressPercentageRoundsToTwoDecimals(): void
    {
        // Arrange
        $this->import->setTotalRows(3);
        $this->import->setProcessedRows(1);

        // Act
        $percentage = $this->import->getProgressPercentage();

        // Assert
        $this->assertSame(33.33, $percentage); // 1/3 = 33.33...
    }

    public function testGetDurationReturnsNullWhenNotStarted(): void
    {
        // Arrange & Act
        $duration = $this->import->getDuration();

        // Assert
        $this->assertNull($duration);
    }

    public function testGetDurationReturnsSecondsWhenStartedButNotCompleted(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('-5 seconds');
        $this->import->setStartedAt($startTime);

        // Act
        $duration = $this->import->getDuration();

        // Assert
        $this->assertNotNull($duration);
        $this->assertGreaterThanOrEqual(4, $duration); // At least 4 seconds (accounting for test execution time)
        $this->assertLessThanOrEqual(6, $duration); // At most 6 seconds (with some margin)
    }

    public function testGetDurationReturnsCorrectSecondsWhenCompleted(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2024-01-01 10:00:00');
        $endTime = new \DateTimeImmutable('2024-01-01 10:05:30');
        $this->import->setStartedAt($startTime);
        $this->import->setCompletedAt($endTime);

        // Act
        $duration = $this->import->getDuration();

        // Assert
        $this->assertSame(330, $duration); // 5 minutes 30 seconds = 330 seconds
    }

    public function testAddErrorAddsToCollection(): void
    {
        // Arrange
        $error = new ImportError();
        $error->setRowNumber(1);
        $error->setMessage('Test error');
        $error->setSeverity(ImportErrorSeverity::ERROR);

        // Act
        $result = $this->import->addError($error);

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertCount(1, $this->import->getErrors());
        $this->assertTrue($this->import->getErrors()->contains($error));
        $this->assertSame($this->import, $error->getImport());
    }

    public function testAddErrorDoesNotAddDuplicates(): void
    {
        // Arrange
        $error = new ImportError();
        $error->setRowNumber(1);
        $error->setMessage('Test error');
        $error->setSeverity(ImportErrorSeverity::ERROR);

        // Act
        $this->import->addError($error);
        $this->import->addError($error); // Add same error twice

        // Assert
        $this->assertCount(1, $this->import->getErrors());
    }

    public function testRemoveErrorRemovesFromCollection(): void
    {
        // Arrange
        $error = new ImportError();
        $error->setRowNumber(1);
        $error->setMessage('Test error');
        $error->setSeverity(ImportErrorSeverity::ERROR);
        $this->import->addError($error);

        // Act
        $result = $this->import->removeError($error);

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertCount(0, $this->import->getErrors());
        $this->assertFalse($this->import->getErrors()->contains($error));
    }

    public function testAddAnalysisResultAddsToCollection(): void
    {
        // Arrange
        $analysisResult = new ImportAnalysisResult();
        $analysisResult->setOperationType(ImportOperationType::CREATE);
        $analysisResult->setEntityType('Customer');
        $analysisResult->setCount(10);

        // Act
        $result = $this->import->addAnalysisResult($analysisResult);

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertCount(1, $this->import->getAnalysisResults());
        $this->assertTrue($this->import->getAnalysisResults()->contains($analysisResult));
        $this->assertSame($this->import, $analysisResult->getImport());
    }

    public function testRemoveAnalysisResultRemovesFromCollection(): void
    {
        // Arrange
        $analysisResult = new ImportAnalysisResult();
        $analysisResult->setOperationType(ImportOperationType::CREATE);
        $analysisResult->setEntityType('Customer');
        $analysisResult->setCount(10);
        $this->import->addAnalysisResult($analysisResult);

        // Act
        $result = $this->import->removeAnalysisResult($analysisResult);

        // Assert
        $this->assertSame($this->import, $result);
        $this->assertCount(0, $this->import->getAnalysisResults());
    }

    public function testSettersAndGetters(): void
    {
        // Arrange
        $company = new Company();
        $user = new User();
        $user->setCompany($company);
        $user->setEmail('test@example.com');

        // Act
        $this->import->setOriginalFilename('test.xlsx');
        $this->import->setStoredFilename('stored-123.xlsx');
        $this->import->setType(ImportType::CUSTOMER);
        $this->import->setUser($user);
        $this->import->setTotalRows(100);
        $this->import->setProcessedRows(50);
        $this->import->setSuccessRows(45);
        $this->import->setErrorRows(5);

        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $this->import->setCreatedAt($createdAt);

        $startedAt = new \DateTimeImmutable('2024-01-01 10:01:00');
        $this->import->setStartedAt($startedAt);

        $completedAt = new \DateTimeImmutable('2024-01-01 10:05:00');
        $this->import->setCompletedAt($completedAt);

        // Assert
        $this->assertSame('test.xlsx', $this->import->getOriginalFilename());
        $this->assertSame('stored-123.xlsx', $this->import->getStoredFilename());
        $this->assertSame(ImportType::CUSTOMER, $this->import->getType());
        $this->assertSame($user, $this->import->getUser());
        $this->assertSame(100, $this->import->getTotalRows());
        $this->assertSame(50, $this->import->getProcessedRows());
        $this->assertSame(45, $this->import->getSuccessRows());
        $this->assertSame(5, $this->import->getErrorRows());
        $this->assertSame($createdAt, $this->import->getCreatedAt());
        $this->assertSame($startedAt, $this->import->getStartedAt());
        $this->assertSame($completedAt, $this->import->getCompletedAt());
    }

    public function testStatusTransitionsWorkCorrectly(): void
    {
        // Arrange & Act & Assert - Complete workflow
        $this->assertSame(ImportStatus::PENDING, $this->import->getStatus());

        $this->import->markAsAnalyzing();
        $this->assertSame(ImportStatus::ANALYZING, $this->import->getStatus());

        $this->import->markAsAwaitingConfirmation();
        $this->assertSame(ImportStatus::AWAITING_CONFIRMATION, $this->import->getStatus());

        $this->import->markAsProcessing();
        $this->assertSame(ImportStatus::PROCESSING, $this->import->getStatus());

        $this->import->markAsCompleted();
        $this->assertSame(ImportStatus::COMPLETED, $this->import->getStatus());
    }

    public function testAlternativeStatusTransitionsForFailure(): void
    {
        // Arrange & Act & Assert - Failure workflow
        $this->import->markAsAnalyzing();
        $this->import->markAsFailed();

        $this->assertSame(ImportStatus::FAILED, $this->import->getStatus());
        $this->assertNotNull($this->import->getCompletedAt());
    }

    public function testAlternativeStatusTransitionsForCancellation(): void
    {
        // Arrange & Act & Assert - Cancellation workflow
        $this->import->markAsAnalyzing();
        $this->import->markAsCancelled();

        $this->assertSame(ImportStatus::CANCELLED, $this->import->getStatus());
        $this->assertNotNull($this->import->getCompletedAt());
    }
}
