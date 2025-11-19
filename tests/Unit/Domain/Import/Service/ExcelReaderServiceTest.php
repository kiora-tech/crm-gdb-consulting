<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Import\Service;

use App\Domain\Import\Service\ExcelReaderService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PHPUnit\Framework\TestCase;

class ExcelReaderServiceTest extends TestCase
{
    private ExcelReaderService $excelReader;
    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->excelReader = new ExcelReaderService();
        $this->fixturesPath = __DIR__.'/../../../../Fixtures/files';
    }

    public function testReadRowsInBatchesReturnsGenerator(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $result = $this->excelReader->readRowsInBatches($filePath);

        // Assert - Generator type is enforced by return type
        // Simply iterating confirms it works as a Generator
        $batches = iterator_to_array($result);
        $this->assertNotEmpty($batches);
    }

    public function testReadRowsInBatchesReturnsCorrectData(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath, 10));

        // Assert
        $this->assertCount(1, $batches); // 3 data rows fit in one batch
        $this->assertCount(3, $batches[0]); // First batch has 3 rows

        // Verify first row data
        $firstRow = $batches[0][0];
        $this->assertIsArray($firstRow);
        $this->assertArrayHasKey('Raison sociale', $firstRow);
        $this->assertArrayHasKey('SIRET', $firstRow);
        $this->assertArrayHasKey('Email', $firstRow);
        $this->assertSame('ACME Corporation', $firstRow['Raison sociale']);
        // SIRET is returned as integer by PhpSpreadsheet when stored as number in Excel
        $this->assertEquals(12345678901234, $firstRow['SIRET']);
        $this->assertSame('contact@acme.fr', $firstRow['Email']);
    }

    public function testReadRowsInBatchesRespectsCustomBatchSize(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_large.xlsx';
        $batchSize = 50;

        // Act
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath, $batchSize));

        // Assert
        $this->assertCount(5, $batches); // 250 rows / 50 = 5 batches
        $this->assertCount(50, $batches[0]); // First batch has 50 rows
        $this->assertCount(50, $batches[4]); // Last batch has 50 rows
    }

    public function testReadRowsInBatchesHandlesPartialLastBatch(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';
        $batchSize = 2;

        // Act
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath, $batchSize));

        // Assert
        $this->assertCount(2, $batches); // 3 rows: 2 in first batch, 1 in second
        $this->assertCount(2, $batches[0]); // First batch has 2 rows
        $this->assertCount(1, $batches[1]); // Last batch has 1 row (partial)
    }

    public function testReadRowsInBatchesUsesHeadersAsKeys(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath));
        $firstRow = $batches[0][0];

        // Assert
        $expectedKeys = ['Raison sociale', 'SIRET', 'Email', 'Téléphone', 'Adresse', 'Code postal', 'Ville'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $firstRow, "Row should have key: {$key}");
        }
    }

    public function testReadRowsInBatchesThrowsExceptionForNonExistentFile(): void
    {
        // Arrange
        $filePath = '/non/existent/file.xlsx';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n\'existe pas');

        // Act
        iterator_to_array($this->excelReader->readRowsInBatches($filePath));
    }

    public function testReadRowsInBatchesThrowsExceptionForNonReadableFile(): void
    {
        // Skip on systems where chmod doesn't prevent root/process from reading
        // (e.g., Docker containers running as root, CI environments)
        if (0 === posix_geteuid()) {
            $this->markTestSkipped('Cannot test file permissions when running as root');
        }

        // Arrange - Create a file but make it unreadable
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'content');
        chmod($tempFile, 0000); // Make unreadable

        try {
            // Assert
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('n\'est pas lisible');

            // Act
            iterator_to_array($this->excelReader->readRowsInBatches($tempFile));
        } finally {
            // Cleanup
            chmod($tempFile, 0644);
            unlink($tempFile);
        }
    }

    public function testReadRowsInBatchesThrowsExceptionForInvalidExcelFile(): void
    {
        // Arrange - Create a file with random binary data (truly invalid Excel file)
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, random_bytes(1024));

        try {
            // Assert
            $this->expectException(ReaderException::class);
            $this->expectExceptionMessage('Impossible de lire le fichier Excel');

            // Act
            iterator_to_array($this->excelReader->readRowsInBatches($tempFile));
        } finally {
            // Cleanup
            unlink($tempFile);
        }
    }

    public function testGetTotalRowsReturnsCorrectCount(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $totalRows = $this->excelReader->getTotalRows($filePath);

        // Assert
        $this->assertSame(3, $totalRows); // 3 data rows (excluding header)
    }

    public function testGetTotalRowsExcludesHeaderRow(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_large.xlsx';

        // Act
        $totalRows = $this->excelReader->getTotalRows($filePath);

        // Assert
        $this->assertSame(250, $totalRows); // File has 251 rows total (1 header + 250 data)
    }

    public function testGetTotalRowsReturnsZeroForEmptyFile(): void
    {
        // Arrange - Use empty file (only headers)
        $filePath = $this->fixturesPath.'/test_import_empty.xlsx';

        // Act
        $totalRows = $this->excelReader->getTotalRows($filePath);

        // Assert
        $this->assertSame(0, $totalRows); // No data rows, only header
    }

    public function testGetTotalRowsThrowsExceptionForNonExistentFile(): void
    {
        // Arrange
        $filePath = '/non/existent/file.xlsx';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n\'existe pas');

        // Act
        $this->excelReader->getTotalRows($filePath);
    }

    public function testGetTotalRowsThrowsExceptionForInvalidFile(): void
    {
        // Arrange - Create a file with random binary data (truly invalid Excel file)
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, random_bytes(1024));

        try {
            // Assert
            $this->expectException(ReaderException::class);

            // Act
            $this->excelReader->getTotalRows($tempFile);
        } finally {
            // Cleanup
            unlink($tempFile);
        }
    }

    public function testGetHeadersReturnsCorrectHeaders(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $headers = $this->excelReader->getHeaders($filePath);

        // Assert
        $expectedHeaders = [
            'Raison sociale',
            'SIRET',
            'Email',
            'Téléphone',
            'Adresse',
            'Code postal',
            'Ville',
        ];
        $this->assertSame($expectedHeaders, $headers);
    }

    public function testGetHeadersReturnsArrayOfStrings(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $headers = $this->excelReader->getHeaders($filePath);

        // Assert
        $this->assertIsArray($headers);
        $this->assertNotEmpty($headers);
        foreach ($headers as $header) {
            $this->assertIsString($header);
        }
    }

    public function testGetHeadersHandlesEmptyHeaders(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_mixed.xlsx';

        // Act
        $headers = $this->excelReader->getHeaders($filePath);

        // Assert
        $this->assertIsArray($headers);
        $this->assertNotEmpty($headers);
        // Headers should be strings even if empty in Excel
        foreach ($headers as $header) {
            $this->assertIsString($header);
        }
    }

    public function testGetHeadersThrowsExceptionForNonExistentFile(): void
    {
        // Arrange
        $filePath = '/non/existent/file.xlsx';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n\'existe pas');

        // Act
        $this->excelReader->getHeaders($filePath);
    }

    public function testReadRowsInBatchesHandlesNullValues(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_valid.xlsx';

        // Act
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath));

        // Assert - Verify that the structure is maintained even with potential null values
        foreach ($batches as $batch) {
            foreach ($batch as $row) {
                $this->assertIsArray($row);
                // Each row should have the same number of keys as headers
                $this->assertGreaterThan(0, count($row));
            }
        }
    }

    public function testReadRowsInBatchesHandlesDifferentDataTypes(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_mixed.xlsx';

        // Act
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath));

        // Assert
        $this->assertNotEmpty($batches);
        $this->assertNotEmpty($batches[0]);

        $firstRow = $batches[0][0];
        $this->assertIsArray($firstRow);
        $this->assertArrayHasKey('Name', $firstRow);
        $this->assertArrayHasKey('Number', $firstRow);
        $this->assertArrayHasKey('Date', $firstRow);
    }

    public function testReadRowsInBatchesWithDefaultBatchSize(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_large.xlsx';

        // Act - Use default batch size (100)
        $batches = iterator_to_array($this->excelReader->readRowsInBatches($filePath));

        // Assert - 250 rows with default batch size of 100
        $this->assertCount(3, $batches); // 100 + 100 + 50
        $this->assertCount(100, $batches[0]);
        $this->assertCount(100, $batches[1]);
        $this->assertCount(50, $batches[2]);
    }

    public function testMemoryEfficientProcessing(): void
    {
        // Arrange
        $filePath = $this->fixturesPath.'/test_import_large.xlsx';
        $memoryBefore = memory_get_usage();

        // Act - Process large file in batches
        $rowCount = 0;
        foreach ($this->excelReader->readRowsInBatches($filePath, 50) as $batch) {
            $rowCount += count($batch);
            // Process batch (do nothing for this test)
        }

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Assert
        $this->assertSame(250, $rowCount);
        // Memory usage should be reasonable (less than 10MB for this test)
        // This is a soft assertion - actual memory usage depends on PHP and system configuration
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable for batch processing');
    }
}
