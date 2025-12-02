<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Domain\Import\Service\FileStorageService;
use App\Domain\Import\Service\ImportAnalyzer;
use App\Domain\Import\Service\ImportNotifier;
use App\Domain\Import\Service\ImportOrchestrator;
use App\Domain\Import\Service\ImportProcessor;
use App\Domain\Import\ValueObject\ImportFileInfo;
use App\Entity\Company;
use App\Entity\Import;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Entity\User;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImportOrchestratorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ImportRepository $importRepository;
    private ImportOrchestrator $orchestrator;
    private User $testUser;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->importRepository = self::getContainer()->get(ImportRepository::class);

        // Create mocked dependencies
        $fileStorage = $this->createMock(FileStorageService::class);
        // deleteImportFile() has void return type - no need to specify return value

        $analyzer = $this->createMock(ImportAnalyzer::class);
        $processor = $this->createMock(ImportProcessor::class);
        $notifier = $this->createMock(ImportNotifier::class);

        $this->orchestrator = new ImportOrchestrator(
            $this->importRepository,
            $this->entityManager,
            $fileStorage,
            $analyzer,
            $processor,
            $notifier
        );

        // Create test user
        $company = new Company();
        $company->setName('Test Company');

        $this->testUser = new User();
        $this->testUser->setEmail('test@example.com');
        $this->testUser->setPassword('hashed_password');
        $this->testUser->setCompany($company);
        $this->testUser->setName('Test User');

        $this->entityManager->persist($company);
        $this->entityManager->persist($this->testUser);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // No manual cleanup needed - DAMA Doctrine Test Bundle automatically
        // rolls back all database changes after each test
    }

    public function testInitializeImportCreatesImportEntity(): void
    {
        // Arrange
        $fileInfo = new ImportFileInfo(
            originalName: 'test-import.xlsx',
            storedPath: '/tmp/stored-file.xlsx',
            storedFilename: 'stored-file-123.xlsx',
            fileSize: 1024,
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // Act
        $import = $this->orchestrator->initializeImport($fileInfo, ImportType::CUSTOMER, $this->testUser);

        // Assert
        $this->assertNotNull($import->getId());
        $this->assertSame('test-import.xlsx', $import->getOriginalFilename());
        $this->assertSame('stored-file-123.xlsx', $import->getStoredFilename());
        $this->assertSame(ImportType::CUSTOMER, $import->getType());
        $this->assertSame($this->testUser, $import->getUser());
        $this->assertSame(ImportStatus::PENDING, $import->getStatus());
        // Verify created date is recent (within last minute)
        $this->assertLessThan(60, (new \DateTimeImmutable())->getTimestamp() - $import->getCreatedAt()->getTimestamp());
    }

    public function testInitializeImportPersistsToDatabase(): void
    {
        // Arrange
        $fileInfo = new ImportFileInfo(
            originalName: 'test-import.xlsx',
            storedPath: '/tmp/stored-file.xlsx',
            storedFilename: 'stored-file-123.xlsx',
            fileSize: 1024,
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // Act
        $import = $this->orchestrator->initializeImport($fileInfo, ImportType::CUSTOMER, $this->testUser);
        $this->entityManager->clear(); // Clear to force database fetch

        // Assert
        $fetchedImport = $this->importRepository->find($import->getId());
        $this->assertNotNull($fetchedImport);
        $this->assertSame($import->getId(), $fetchedImport->getId());
        $this->assertSame('test-import.xlsx', $fetchedImport->getOriginalFilename());
    }

    public function testStartAnalysisChangesStatusToAnalyzing(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PENDING);

        // Act
        $this->orchestrator->startAnalysis($import);

        // Assert
        $this->assertSame(ImportStatus::ANALYZING, $import->getStatus());
        $this->assertNotNull($import->getStartedAt());
    }

    public function testStartAnalysisThrowsExceptionForInvalidStatus(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PROCESSING);

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('doit être en statut PENDING');

        // Act
        $this->orchestrator->startAnalysis($import);
    }

    public function testStartAnalysisPersistsChanges(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PENDING);
        $importId = $import->getId();

        // Act
        $this->orchestrator->startAnalysis($import);
        $this->entityManager->clear();

        // Assert
        $fetchedImport = $this->importRepository->find($importId);
        $this->assertSame(ImportStatus::ANALYZING, $fetchedImport->getStatus());
    }

    public function testConfirmAndProcessChangesStatusToProcessing(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::AWAITING_CONFIRMATION);

        // Act
        $this->orchestrator->confirmAndProcess($import);

        // Assert
        $this->assertSame(ImportStatus::PROCESSING, $import->getStatus());
    }

    public function testConfirmAndProcessThrowsExceptionForInvalidStatus(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PENDING);

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('doit être en statut AWAITING_CONFIRMATION');

        // Act
        $this->orchestrator->confirmAndProcess($import);
    }

    public function testConfirmAndProcessPersistsChanges(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::AWAITING_CONFIRMATION);
        $importId = $import->getId();

        // Act
        $this->orchestrator->confirmAndProcess($import);
        $this->entityManager->clear();

        // Assert
        $fetchedImport = $this->importRepository->find($importId);
        $this->assertSame(ImportStatus::PROCESSING, $fetchedImport->getStatus());
    }

    public function testCancelImportChangesStatusToCancelled(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PENDING);

        // Act
        $this->orchestrator->cancelImport($import);

        // Assert
        $this->assertSame(ImportStatus::CANCELLED, $import->getStatus());
        $this->assertNotNull($import->getCompletedAt());
    }

    #[DataProvider('cancellableStatusesProvider')]
    public function testCancelImportWorksForAllCancellableStatuses(ImportStatus $status): void
    {
        // Arrange
        $import = $this->createTestImport($status);

        // Act
        $this->orchestrator->cancelImport($import);

        // Assert
        $this->assertSame(ImportStatus::CANCELLED, $import->getStatus());
    }

    /**
     * @return array<string, array{ImportStatus}>
     */
    public static function cancellableStatusesProvider(): array
    {
        return [
            'pending' => [ImportStatus::PENDING],
            'analyzing' => [ImportStatus::ANALYZING],
            'awaiting_confirmation' => [ImportStatus::AWAITING_CONFIRMATION],
            'processing' => [ImportStatus::PROCESSING],
        ];
    }

    #[DataProvider('nonCancellableStatusesProvider')]
    public function testCancelImportThrowsExceptionForTerminalStatuses(ImportStatus $status): void
    {
        // Arrange
        $import = $this->createTestImport($status);

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ne peut pas être annulé');

        // Act
        $this->orchestrator->cancelImport($import);
    }

    /**
     * @return array<string, array{ImportStatus}>
     */
    public static function nonCancellableStatusesProvider(): array
    {
        return [
            'completed' => [ImportStatus::COMPLETED],
            'failed' => [ImportStatus::FAILED],
            'cancelled' => [ImportStatus::CANCELLED],
        ];
    }

    public function testCancelImportPersistsChanges(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PENDING);
        $importId = $import->getId();

        // Act
        $this->orchestrator->cancelImport($import);
        $this->entityManager->clear();

        // Assert
        $fetchedImport = $this->importRepository->find($importId);
        $this->assertSame(ImportStatus::CANCELLED, $fetchedImport->getStatus());
    }

    public function testCancelImportHandlesFileStorageErrors(): void
    {
        // Arrange
        $fileStorage = $this->createMock(FileStorageService::class);
        $fileStorage->method('deleteImportFile')
            ->willThrowException(new \RuntimeException('File deletion failed'));

        $orchestrator = new ImportOrchestrator(
            $this->importRepository,
            $this->entityManager,
            $fileStorage,
            $this->createMock(ImportAnalyzer::class),
            $this->createMock(ImportProcessor::class),
            $this->createMock(ImportNotifier::class)
        );

        $import = $this->createTestImport(ImportStatus::PENDING);

        // Act - Should not throw exception
        $orchestrator->cancelImport($import);

        // Assert - Import should still be cancelled despite file deletion error
        $this->assertSame(ImportStatus::CANCELLED, $import->getStatus());
    }

    public function testCancelImportHandlesNotifierErrors(): void
    {
        // Arrange
        $notifier = $this->createMock(ImportNotifier::class);
        $notifier->method('notifyCancellation')
            ->willThrowException(new \Exception('Notification failed'));

        $orchestrator = new ImportOrchestrator(
            $this->importRepository,
            $this->entityManager,
            $this->createMock(FileStorageService::class),
            $this->createMock(ImportAnalyzer::class),
            $this->createMock(ImportProcessor::class),
            $notifier
        );

        $import = $this->createTestImport(ImportStatus::PENDING);

        // Act - Should not throw exception
        $orchestrator->cancelImport($import);

        // Assert - Import should still be cancelled despite notification error
        $this->assertSame(ImportStatus::CANCELLED, $import->getStatus());
    }

    public function testGetImportWithDetailsReturnsImport(): void
    {
        // Arrange
        $import = $this->createTestImport(ImportStatus::PENDING);
        $importId = $import->getId();

        // Act
        $result = $this->orchestrator->getImportWithDetails($importId);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($importId, $result->getId());
    }

    public function testGetImportWithDetailsReturnsNullForNonExistentImport(): void
    {
        // Act
        $result = $this->orchestrator->getImportWithDetails(99999);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Helper method to create a test Import entity.
     */
    private function createTestImport(ImportStatus $status): Import
    {
        $import = new Import();
        $import->setOriginalFilename('test.xlsx');
        $import->setStoredFilename('stored-test.xlsx');
        $import->setType(ImportType::CUSTOMER);
        $import->setUser($this->testUser);
        $import->setStatus($status);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }
}
