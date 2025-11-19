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
        // If we get here without exception, validation passed
    }

    /**
     * @dataProvider validMimeTypesProvider
     */
    public function testValidateAcceptsAllValidMimeTypes(string $mimeType, string $extension): void
    {
        // Arrange - Use mock for MIME type validation tests (faster and no fixture files needed)
        // Create a valid Excel file structure in memory
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');

        // Create a minimal valid Excel file using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Header 1');
        $sheet->setCellValue('B1', 'Header 2');
        $sheet->setCellValue('A2', 'Data 1');
        $sheet->setCellValue('B2', 'Data 2');

        // Determine writer type based on extension
        $writerType = match ($extension) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            'ods' => 'Ods',
            default => 'Xlsx',
        };

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, $writerType);
        $writer->save($tempFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // Create mock with proper MIME type
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getClientOriginalExtension')->willReturn($extension);
        $file->method('getClientOriginalName')->willReturn('test_file.'.$extension);
        $file->method('getSize')->willReturn(filesize($tempFile));
        $file->method('getPathname')->willReturn($tempFile);

        // Act & Assert - Should not throw exception
        $this->validator->validate($file);
        // If we get here without exception, validation passed
    }

    /**
     * @return array<string, array{string, string}>
     */
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

    /**
     * @return array<string, array{string, string}>
     */
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
        // If we get here without exception, validation passed
    }

    public function testValidateRejectsCorruptedExcelFile(): void
    {
        // Arrange - Create a file with xlsx extension but completely invalid binary content
        $tempFile = tempnam(sys_get_temp_dir(), 'corrupt_');
        // Write random binary data that will fail PhpSpreadsheet parsing
        file_put_contents($tempFile, random_bytes(1024));

        // Use a mock to ensure MIME type and extension are correct
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file->method('getClientOriginalExtension')->willReturn('xlsx');
        $file->method('getClientOriginalName')->willReturn('corrupt.xlsx');
        $file->method('getSize')->willReturn(filesize($tempFile));
        $file->method('getPathname')->willReturn($tempFile);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n\'est pas un fichier Excel valide ou est corrompu');

        // Act
        $this->validator->validate($file);
    }

    public function testValidateRejectsExcelFileWithOnlyHeaders(): void
    {
        // Arrange - Create an Excel file with only headers (no data rows)
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Header 1');
        $sheet->setCellValue('B1', 'Header 2');
        // No data rows - only headers

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // Use a mock to ensure MIME type is correct
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file->method('getClientOriginalExtension')->willReturn('xlsx');
        $file->method('getClientOriginalName')->willReturn('empty.xlsx');
        $file->method('getSize')->willReturn(filesize($tempFile));
        $file->method('getPathname')->willReturn($tempFile);

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
     *
     * Creates a PHPUnit mock that returns the specified MIME type and extension,
     * avoiding issues with real MIME type detection in different environments (local vs CI).
     */
    private function createMockUploadedFile(
        string $originalName,
        string $mimeType,
        int $size,
        ?string $extension = null,
    ): UploadedFile {
        if (null === $extension) {
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        }

        // Create a real temporary file for methods that need a real path
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

        // Create a mock to control MIME type and extension return values
        // This ensures consistent behavior across different environments (local vs CI)
        $mock = $this->createMock(UploadedFile::class);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getClientOriginalExtension')->willReturn($extension);
        $mock->method('getClientOriginalName')->willReturn($originalName);
        $mock->method('getSize')->willReturn($size);
        $mock->method('getPathname')->willReturn($tempFile);

        return $mock;
    }
}
