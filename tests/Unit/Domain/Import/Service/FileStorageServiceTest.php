<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Import\Service;

use App\Domain\Import\Service\FileStorageService;
use App\Domain\Import\ValueObject\ImportFileInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class FileStorageServiceTest extends TestCase
{
    private FileStorageService $fileStorageService;
    private string $testImportDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a temporary directory for tests
        $this->testImportDirectory = sys_get_temp_dir().'/test_import_'.uniqid();

        $this->fileStorageService = new FileStorageService(
            new AsciiSlugger(),
            $this->testImportDirectory
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directory
        if (is_dir($this->testImportDirectory)) {
            $this->removeDirectory($this->testImportDirectory);
        }
    }

    public function testStoreUploadedFileCreatesUniqueFilenames(): void
    {
        // Arrange
        $originalFilename = 'test-import.xlsx';
        $uploadedFile = $this->createRealExcelFile($originalFilename);

        // Act
        $fileInfo = $this->fileStorageService->storeUploadedFile($uploadedFile);

        // Assert - ImportFileInfo type is enforced by return type
        $this->assertSame($originalFilename, $fileInfo->originalName);
        $this->assertNotSame($originalFilename, $fileInfo->storedFilename);
        $this->assertStringContainsString('test-import-', $fileInfo->storedFilename);
        $this->assertStringEndsWith('.xlsx', $fileInfo->storedFilename);
        $this->assertFileExists($fileInfo->storedPath);
    }

    public function testStoreUploadedFileCreatesDirectoryIfNotExists(): void
    {
        // Arrange
        $this->assertDirectoryDoesNotExist($this->testImportDirectory);
        $uploadedFile = $this->createRealExcelFile('test.xlsx');

        // Act
        $this->fileStorageService->storeUploadedFile($uploadedFile);

        // Assert
        $this->assertDirectoryExists($this->testImportDirectory);
    }

    public function testStoreUploadedFileSanitizesFilename(): void
    {
        // Arrange
        $unsafeFilename = 'Fichier Spécial Été 2024.xlsx';
        $uploadedFile = $this->createRealExcelFile($unsafeFilename);

        // Act
        $fileInfo = $this->fileStorageService->storeUploadedFile($uploadedFile);

        // Assert
        $this->assertSame($unsafeFilename, $fileInfo->originalName);
        $this->assertMatchesRegularExpression('/^fichier-special-ete-2024-[a-z0-9]+\.xlsx$/', $fileInfo->storedFilename);
    }

    public function testStoreUploadedFilePreservesExtension(): void
    {
        // Arrange
        $uploadedFile = $this->createRealExcelFile('document.xlsx');

        // Act
        $fileInfo = $this->fileStorageService->storeUploadedFile($uploadedFile);

        // Assert
        $this->assertStringEndsWith('.xlsx', $fileInfo->storedFilename);
        $this->assertSame('xlsx', $fileInfo->getExtension());
    }

    public function testGetImportFilePathReturnsCorrectPath(): void
    {
        // Arrange
        $import = new class {
            public function getStoredFilename(): string
            {
                return 'test-import-12345.xlsx';
            }
        };

        // Act
        $filePath = $this->fileStorageService->getImportFilePath($import);

        // Assert
        $expectedPath = $this->testImportDirectory.'/test-import-12345.xlsx';
        $this->assertSame($expectedPath, $filePath);
    }

    public function testGetImportFilePathThrowsExceptionForInvalidEntity(): void
    {
        // Arrange
        $invalidImport = new class {
            // Missing getStoredFilename method
        };

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'entité Import doit avoir une méthode getStoredFilename()');

        // Act
        $this->fileStorageService->getImportFilePath($invalidImport);
    }

    public function testGetImportFilePathThrowsExceptionForEmptyFilename(): void
    {
        // Arrange
        $import = new class {
            public function getStoredFilename(): string
            {
                return '';
            }
        };

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de fichier stocké est vide');

        // Act
        $this->fileStorageService->getImportFilePath($import);
    }

    public function testDeleteImportFileRemovesFile(): void
    {
        // Arrange
        $uploadedFile = $this->createRealExcelFile('test.xlsx');
        $fileInfo = $this->fileStorageService->storeUploadedFile($uploadedFile);

        $import = new class($fileInfo->storedFilename) {
            public function __construct(private string $filename)
            {
            }

            public function getStoredFilename(): string
            {
                return $this->filename;
            }
        };

        $this->assertFileExists($fileInfo->storedPath);

        // Act
        $this->fileStorageService->deleteImportFile($import);

        // Assert
        $this->assertFileDoesNotExist($fileInfo->storedPath);
    }

    public function testDeleteImportFileDoesNotThrowIfFileDoesNotExist(): void
    {
        // Arrange
        $import = new class {
            public function getStoredFilename(): string
            {
                return 'non-existent-file.xlsx';
            }
        };

        // Act & Assert - Should not throw exception
        $this->fileStorageService->deleteImportFile($import);
        // If we get here without exception, deletion was successful
    }

    public function testDeleteImportFileThrowsExceptionForNonWritableFile(): void
    {
        // Skip on systems where chmod doesn't prevent root/process from writing
        // (e.g., Docker containers running as root, CI environments)
        if (0 === posix_geteuid()) {
            $this->markTestSkipped('Cannot test file permissions when running as root');
        }

        // Arrange
        $uploadedFile = $this->createRealExcelFile('test.xlsx');
        $fileInfo = $this->fileStorageService->storeUploadedFile($uploadedFile);

        $import = new class($fileInfo->storedFilename) {
            public function __construct(private string $filename)
            {
            }

            public function getStoredFilename(): string
            {
                return $this->filename;
            }
        };

        // Make file read-only
        chmod($fileInfo->storedPath, 0444);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('n\'est pas accessible en écriture');

        try {
            // Act
            $this->fileStorageService->deleteImportFile($import);
        } finally {
            // Cleanup: restore permissions so tearDown can clean up
            chmod($fileInfo->storedPath, 0644);
        }
    }

    public function testMultipleFilesGetUniqueNames(): void
    {
        // Arrange
        $filenames = [];

        // Act - Store multiple files with the same original name
        for ($i = 0; $i < 3; ++$i) {
            $uploadedFile = $this->createRealExcelFile('same-name.xlsx');
            $fileInfo = $this->fileStorageService->storeUploadedFile($uploadedFile);
            $filenames[] = $fileInfo->storedFilename;
        }

        // Assert - All stored filenames should be unique
        $this->assertSame(3, count($filenames));
        $this->assertSame(3, count(array_unique($filenames)));
    }

    /**
     * Create a real Excel file for testing using test fixtures.
     */
    private function createRealExcelFile(string $originalFilename): UploadedFile
    {
        $fixturesPath = __DIR__.'/../../../../Fixtures/files/test_import_valid.xlsx';

        if (!file_exists($fixturesPath)) {
            throw new \RuntimeException('Test fixture not found: '.$fixturesPath);
        }

        // Copy fixture to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        copy($fixturesPath, $tempFile);

        return new UploadedFile(
            $tempFile,
            $originalFilename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * Recursively remove a directory and its contents.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
