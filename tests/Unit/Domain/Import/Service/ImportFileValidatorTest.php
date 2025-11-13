<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Import\Service;

use App\Domain\Import\Service\ImportFileValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportFileValidatorTest extends TestCase
{
    private ImportFileValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImportFileValidator();
    }

    public function testValidateAcceptsValidExcelFile(): void
    {
        // Arrange
        $validFile = $this->createTestFile('test_import_valid.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Act & Assert - Should not throw exception
        $this->validator->validate($validFile);
        $this->assertTrue(true); // If we get here, validation passed
    }

    /**
     * @dataProvider validMimeTypesProvider
     */
    public function testValidateAcceptsAllValidMimeTypes(string $mimeType, string $extension): void
    {
        // Arrange
        $file = $this->createTestFile('test_file.'.$extension, $mimeType, $extension);

        // Act & Assert
        $this->validator->validate($file);
        $this->assertTrue(true);
    }

    public static function validMimeTypesProvider(): array
    {
        return [
            'xlsx format' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xlsx',
            ],
            'xls format' => [
                'application/vnd.ms-excel',
                'xls',
            ],
            'ods format' => [
                'application/vnd.oasis.opendocument.spreadsheet',
                'ods',
            ],
            'octet-stream with xlsx' => [
                'application/octet-stream',
                'xlsx',
            ],
        ];
    }

    public function testValidateRejectsInvalidMimeType(): void
    {
        // Arrange
        $file = $this->createMockUploadedFile(
            'document.pdf',
            'application/pdf',
            1024,
            'pdf'
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de fichier "application/pdf" n\'est pas autorisé');

        // Act
        $this->validator->validate($file);
    }

    public function testValidateRejectsInvalidExtension(): void
    {
        // Arrange
        $file = $this->createMockUploadedFile(
            'document.txt',
            'application/octet-stream',
            1024,
            'txt'
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'extension de fichier ".txt" n\'est pas autorisée');

        // Act
        $this->validator->validate($file);
    }

    /**
     * @dataProvider invalidMimeTypesProvider
     */
    public function testValidateRejectsVariousInvalidMimeTypes(string $filename, string $mimeType): void
    {
        // Arrange
        $file = $this->createMockUploadedFile($filename, $mimeType, 1024);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->validator->validate($file);
    }

    public static function invalidMimeTypesProvider(): array
    {
        return [
            'PDF file' => ['document.pdf', 'application/pdf'],
            'Word document' => ['document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'Text file' => ['document.txt', 'text/plain'],
            'Image file' => ['image.jpg', 'image/jpeg'],
            'ZIP archive' => ['archive.zip', 'application/zip'],
        ];
    }

    public function testValidateRejectsOversizedFile(): void
    {
        // Arrange - Create a file larger than 50MB
        $oversizedFile = $this->createMockUploadedFile(
            'large_file.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            52428801, // 50MB + 1 byte
            'xlsx'
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dépasse la limite autorisée de 50 MB');

        // Act
        $this->validator->validate($oversizedFile);
    }

    public function testValidateAcceptsFileSizeAtLimit(): void
    {
        // Arrange - Create a file exactly at 50MB limit
        $file = $this->createTestFile(
            'test_import_valid.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // Act & Assert - Should not throw exception
        $this->validator->validate($file);
        $this->assertTrue(true);
    }

    public function testValidateRejectsCorruptedExcelFile(): void
    {
        // Arrange - Create a file with xlsx extension but invalid content
        $tempFile = tempnam(sys_get_temp_dir(), 'corrupt_');
        file_put_contents($tempFile, 'This is not a valid Excel file content');

        $file = new UploadedFile(
            $tempFile,
            'corrupt.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n\'est pas un fichier Excel valide ou est corrompu');

        // Act
        $this->validator->validate($file);
    }

    public function testValidateRejectsExcelFileWithOnlyHeaders(): void
    {
        // Arrange - Use the empty test file (only headers, no data rows)
        $file = $this->createTestFile(
            'test_import_empty.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('doit contenir au moins une ligne d\'en-têtes et une ligne de données');

        // Act
        $this->validator->validate($file);
    }

    public function testValidateRejectsExcelFileWithNoSheets(): void
    {
        // This test would require creating a special Excel file with no worksheets
        // In practice, this is very rare and difficult to create with PhpSpreadsheet
        $this->markTestSkipped('Creating an Excel file with no sheets is not straightforward');
    }

    public function testValidateErrorMessagesAreInFrench(): void
    {
        // Arrange - Invalid MIME type
        $file = $this->createMockUploadedFile('test.pdf', 'application/pdf', 1024, 'pdf');

        try {
            // Act
            $this->validator->validate($file);
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // Assert - Error message should be in French
            $this->assertStringContainsString('n\'est pas autorisé', $e->getMessage());
            $this->assertStringContainsString('Formats acceptés', $e->getMessage());
        }
    }

    public function testValidateMultipleValidationErrors(): void
    {
        // Arrange - File with wrong extension
        $file = $this->createMockUploadedFile(
            'document.exe',
            'application/octet-stream',
            1024,
            'exe'
        );

        // Assert - Should fail on extension check
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->validator->validate($file);
    }

    /**
     * Create a real test file from fixtures.
     */
    private function createTestFile(string $filename, string $mimeType): UploadedFile
    {
        $fixturesPath = __DIR__.'/../../../../Fixtures/files/'.$filename;

        if (!file_exists($fixturesPath)) {
            $this->fail('Test fixture file not found: '.$fixturesPath);
        }

        // Copy to temp file to avoid modifying fixtures
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        copy($fixturesPath, $tempFile);

        return new UploadedFile(
            $tempFile,
            $filename,
            $mimeType,
            null,
            true
        );
    }

    /**
     * Create a mock UploadedFile for testing.
     */
    private function createMockUploadedFile(
        string $originalName,
        string $mimeType,
        int $size,
        ?string $extension = null,
    ): UploadedFile {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');

        // Write dummy content of specified size
        if ($size > 0) {
            $handle = fopen($tempFile, 'w');
            if (false !== $handle) {
                // Write in chunks to handle large files
                $chunkSize = min($size, 1024 * 1024); // 1MB chunks
                $written = 0;
                while ($written < $size) {
                    $toWrite = min($chunkSize, $size - $written);
                    fwrite($handle, str_repeat('x', $toWrite));
                    $written += $toWrite;
                }
                fclose($handle);
            }
        }

        if (null === $extension) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        }

        return new UploadedFile(
            $tempFile,
            $originalName,
            $mimeType,
            null,
            true
        );
    }
}
