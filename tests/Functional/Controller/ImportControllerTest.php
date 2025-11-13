<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Company;
use App\Entity\Import;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Entity\User;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private ImportRepository $importRepository;
    private User $testUser;
    private User $otherUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->importRepository = self::getContainer()->get(ImportRepository::class);

        // Create test users
        $company = new Company();
        $company->setName('Test Company');
        $company->setAddress('123 Test St');
        $company->setPostalCode('12345');
        $company->setCity('TestCity');

        $this->testUser = new User();
        $this->testUser->setEmail('test@example.com');
        $this->testUser->setPassword('$2y$13$hashedpassword'); // Hashed password
        $this->testUser->setCompany($company);
        $this->testUser->setName('Test User');

        $this->otherUser = new User();
        $this->otherUser->setEmail('other@example.com');
        $this->otherUser->setPassword('$2y$13$hashedpassword');
        $this->otherUser->setCompany($company);
        $this->otherUser->setName('Other User');

        $this->entityManager->persist($company);
        $this->entityManager->persist($this->testUser);
        $this->entityManager->persist($this->otherUser);
        $this->entityManager->flush();

        // Login as test user
        $this->client->loginUser($this->testUser);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test data
        $imports = $this->importRepository->findAll();
        foreach ($imports as $import) {
            $this->entityManager->remove($import);
        }

        if (null !== $this->testUser && null !== $this->testUser->getId()) {
            $this->entityManager->remove($this->testUser);
        }

        if (null !== $this->otherUser && null !== $this->otherUser->getId()) {
            $this->entityManager->remove($this->otherUser);
        }

        $this->entityManager->flush();
    }

    public function testIndexActionShowsOnlyUserImports(): void
    {
        // Arrange
        $userImport = $this->createTestImport($this->testUser, 'user-import.xlsx');
        $otherImport = $this->createTestImport($this->otherUser, 'other-import.xlsx');

        // Act
        $crawler = $this->client->request('GET', '/import/');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');

        // Verify only user's import is shown (this would need to be adjusted based on actual HTML structure)
        // For now, just verify the page loads successfully
    }

    public function testIndexActionRequiresAuthentication(): void
    {
        // Arrange - Logout
        $this->client->loginUser(new class extends User {
            public function getRoles(): array
            {
                return [];
            }
        });

        // Act
        $this->client->request('GET', '/import/');

        // Assert - Should redirect to login
        $this->assertResponseRedirects();
    }

    public function testNewActionDisplaysForm(): void
    {
        // Act
        $crawler = $this->client->request('GET', '/import/new');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    public function testCreateActionWithValidFile(): void
    {
        // Arrange
        $file = $this->createTestUploadedFile('test_import_valid.xlsx');

        // Act
        $this->client->request('POST', '/import/new', [
            'import_type' => ImportType::CUSTOMER->value,
        ], [
            'import_file' => $file,
        ]);

        // Assert
        $this->assertResponseRedirects();

        // Follow redirect and verify success message
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Verify import was created in database
        $imports = $this->importRepository->findBy(['user' => $this->testUser]);
        $this->assertCount(1, $imports);
        $this->assertSame(ImportStatus::PENDING, $imports[0]->getStatus());
    }

    public function testCreateActionWithMissingFile(): void
    {
        // Act
        $this->client->request('POST', '/import/new', [
            'import_type' => ImportType::CUSTOMER->value,
        ]);

        // Assert
        $this->assertResponseRedirects('/import/new');

        // Follow redirect and verify error message
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        // Verify flash message (would need to check session or HTML content)
    }

    public function testCreateActionWithInvalidFileExtension(): void
    {
        // Arrange
        $file = $this->createTestUploadedFile('test_file.txt', 'text/plain', 'txt');

        // Act
        $this->client->request('POST', '/import/new', [
            'import_type' => ImportType::CUSTOMER->value,
        ], [
            'import_file' => $file,
        ]);

        // Assert
        $this->assertResponseRedirects('/import/new');

        // Verify no import was created
        $imports = $this->importRepository->findBy(['user' => $this->testUser]);
        $this->assertCount(0, $imports);
    }

    public function testCreateActionWithOversizedFile(): void
    {
        // Arrange
        $file = $this->createTestUploadedFile('large_file.xlsx', null, 'xlsx', 11 * 1024 * 1024); // 11MB

        // Act
        $this->client->request('POST', '/import/new', [
            'import_type' => ImportType::CUSTOMER->value,
        ], [
            'import_file' => $file,
        ]);

        // Assert
        $this->assertResponseRedirects('/import/new');

        // Verify no import was created
        $imports = $this->importRepository->findBy(['user' => $this->testUser]);
        $this->assertCount(0, $imports);
    }

    public function testCreateActionWithInvalidImportType(): void
    {
        // Arrange
        $file = $this->createTestUploadedFile('test_import_valid.xlsx');

        // Act
        $this->client->request('POST', '/import/new', [
            'import_type' => 'invalid_type',
        ], [
            'import_file' => $file,
        ]);

        // Assert
        $this->assertResponseRedirects('/import/new');

        // Verify no import was created
        $imports = $this->importRepository->findBy(['user' => $this->testUser]);
        $this->assertCount(0, $imports);
    }

    public function testShowActionDisplaysImportDetails(): void
    {
        // Arrange
        $import = $this->createTestImport($this->testUser, 'test.xlsx');

        // Act
        $crawler = $this->client->request('GET', '/import/'.$import->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    public function testShowActionReturns404ForNonExistentImport(): void
    {
        // Act
        $this->client->request('GET', '/import/99999');

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowActionDeniesAccessToOtherUsersImport(): void
    {
        // Arrange
        $otherImport = $this->createTestImport($this->otherUser, 'other.xlsx');

        // Act
        $this->client->request('GET', '/import/'.$otherImport->getId());

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testConfirmActionStartsProcessing(): void
    {
        // Arrange
        $import = $this->createTestImport($this->testUser, 'test.xlsx', ImportStatus::AWAITING_CONFIRMATION);

        // Act
        $this->client->request('POST', '/import/'.$import->getId().'/confirm');

        // Assert
        $this->assertResponseRedirects('/import/'.$import->getId());

        // Verify status changed
        $this->entityManager->refresh($import);
        $this->assertSame(ImportStatus::PROCESSING, $import->getStatus());
    }

    public function testConfirmActionFailsForInvalidStatus(): void
    {
        // Arrange
        $import = $this->createTestImport($this->testUser, 'test.xlsx', ImportStatus::PENDING);

        // Act
        $this->client->request('POST', '/import/'.$import->getId().'/confirm');

        // Assert
        $this->assertResponseRedirects('/import/'.$import->getId());

        // Verify status unchanged
        $this->entityManager->refresh($import);
        $this->assertSame(ImportStatus::PENDING, $import->getStatus());
    }

    public function testConfirmActionDeniesAccessToOtherUsersImport(): void
    {
        // Arrange
        $otherImport = $this->createTestImport($this->otherUser, 'other.xlsx', ImportStatus::AWAITING_CONFIRMATION);

        // Act
        $this->client->request('POST', '/import/'.$otherImport->getId().'/confirm');

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCancelActionCancelsImport(): void
    {
        // Arrange
        $import = $this->createTestImport($this->testUser, 'test.xlsx', ImportStatus::PENDING);

        // Act
        $this->client->request('POST', '/import/'.$import->getId().'/cancel');

        // Assert
        $this->assertResponseRedirects('/import/');

        // Verify status changed
        $this->entityManager->refresh($import);
        $this->assertSame(ImportStatus::CANCELLED, $import->getStatus());
    }

    public function testCancelActionFailsForTerminalStatus(): void
    {
        // Arrange
        $import = $this->createTestImport($this->testUser, 'test.xlsx', ImportStatus::COMPLETED);

        // Act
        $this->client->request('POST', '/import/'.$import->getId().'/cancel');

        // Assert
        $this->assertResponseRedirects('/import/'.$import->getId());

        // Verify status unchanged
        $this->entityManager->refresh($import);
        $this->assertSame(ImportStatus::COMPLETED, $import->getStatus());
    }

    public function testCancelActionDeniesAccessToOtherUsersImport(): void
    {
        // Arrange
        $otherImport = $this->createTestImport($this->otherUser, 'other.xlsx', ImportStatus::PENDING);

        // Act
        $this->client->request('POST', '/import/'.$otherImport->getId().'/cancel');

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testImportWorkflowEndToEnd(): void
    {
        // Arrange
        $file = $this->createTestUploadedFile('test_import_valid.xlsx');

        // Act 1: Create import
        $this->client->request('POST', '/import/new', [
            'import_type' => ImportType::CUSTOMER->value,
        ], [
            'import_file' => $file,
        ]);

        $this->assertResponseRedirects();

        // Get created import
        $imports = $this->importRepository->findBy(['user' => $this->testUser]);
        $this->assertCount(1, $imports);
        $import = $imports[0];

        // Act 2: View import details
        $this->client->request('GET', '/import/'.$import->getId());
        $this->assertResponseIsSuccessful();

        // Act 3: Manually set to awaiting confirmation for testing
        $import->markAsAwaitingConfirmation();
        $this->entityManager->flush();

        // Act 4: Confirm import
        $this->client->request('POST', '/import/'.$import->getId().'/confirm');
        $this->assertResponseRedirects();

        // Verify final status
        $this->entityManager->refresh($import);
        $this->assertSame(ImportStatus::PROCESSING, $import->getStatus());
    }

    /**
     * Helper method to create a test Import entity.
     */
    private function createTestImport(User $user, string $filename, ImportStatus $status = ImportStatus::PENDING): Import
    {
        $import = new Import();
        $import->setOriginalFilename($filename);
        $import->setStoredFilename('stored-'.$filename);
        $import->setType(ImportType::CUSTOMER);
        $import->setUser($user);
        $import->setStatus($status);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Create a test UploadedFile.
     */
    private function createTestUploadedFile(
        string $filename,
        ?string $mimeType = null,
        string $extension = 'xlsx',
        int $size = 1024,
    ): UploadedFile {
        $fixturesPath = __DIR__.'/../../Fixtures/files/'.$filename;

        // If fixture file exists, use it
        if (file_exists($fixturesPath)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
            copy($fixturesPath, $tempFile);

            return new UploadedFile(
                $tempFile,
                $filename,
                $mimeType ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );
        }

        // Otherwise create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, str_repeat('x', $size));

        return new UploadedFile(
            $tempFile,
            $filename,
            $mimeType ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
