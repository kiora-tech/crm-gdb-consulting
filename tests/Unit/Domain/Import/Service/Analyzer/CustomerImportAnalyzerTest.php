<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Import\Service\Analyzer;

use App\Domain\Import\Service\Analyzer\CustomerImportAnalyzer;
use App\Domain\Import\Service\ExcelReaderService;
use App\Entity\Customer;
use App\Entity\Import;
use App\Entity\ImportType;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for CustomerImportAnalyzer.
 *
 * Tests the analyzer logic in isolation using mocks.
 */
class CustomerImportAnalyzerTest extends TestCase
{
    private CustomerImportAnalyzer $analyzer;
    private ExcelReaderService $excelReader;
    private CustomerRepository $customerRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->excelReader = $this->createMock(ExcelReaderService::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $contactRepository = $this->createMock(\App\Repository\ContactRepository::class);
        $energyRepository = $this->createMock(\App\Repository\EnergyRepository::class);

        // Create analyzer with mocks
        $this->analyzer = new CustomerImportAnalyzer(
            $this->excelReader,
            $this->customerRepository,
            $contactRepository,
            $energyRepository,
            $this->entityManager,
            $this->logger
        );
    }

    /**
     * Test that analyzer supports CUSTOMER and FULL import types.
     */
    public function testSupportsCustomerAndFullImportTypes(): void
    {
        $this->assertTrue(
            $this->analyzer->supports(ImportType::CUSTOMER),
            'Analyzer should support CUSTOMER import type'
        );
        $this->assertTrue(
            $this->analyzer->supports(ImportType::FULL),
            'Analyzer should support FULL import type'
        );
    }

    /**
     * Test that analyzer does NOT support other import types.
     */
    public function testDoesNotSupportOtherImportTypes(): void
    {
        $this->assertFalse(
            $this->analyzer->supports(ImportType::CONTACT),
            'Analyzer should NOT support CONTACT import type'
        );
        $this->assertFalse(
            $this->analyzer->supports(ImportType::ENERGY),
            'Analyzer should NOT support ENERGY import type'
        );
    }

    /**
     * Test that analyzer correctly identifies existing customer by SIRET.
     */
    public function testIdentifiesExistingCustomerBySiret(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers
        $headers = ['SIRET', 'Raison sociale', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Fournisseur', 'PDL', 'Élec / Gaz', 'Échéance'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(1);

        // Mock row data
        $rowData = [
            'SIRET' => '12345678901234',
            'Raison sociale' => 'Test Company',
            'Prénom' => 'John',
            'Nom' => 'Doe',
            'Email' => 'john@test.com',
            'Téléphone' => '0123456789',
            'Fournisseur' => 'EDF',
            'PDL' => '12345678901234',
            'Élec / Gaz' => 'ELEC',
            'Échéance' => '2025-12-31',
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([[$rowData]]));

        // Mock existing customer found by SIRET
        $existingCustomer = $this->createMock(Customer::class);
        $this->customerRepository->method('findOneBy')
            ->with(['siret' => '12345678901234'])
            ->willReturn($existingCustomer);

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should identify as UPDATE operation
        $this->assertSame(
            0,
            $result->creations[Customer::class] ?? 0,
            'Should not create new customer (already exists by SIRET)'
        );
        $this->assertSame(
            1,
            $result->updates[Customer::class] ?? 0,
            'Should mark customer for UPDATE (exists by SIRET)'
        );
    }

    /**
     * Test that analyzer correctly identifies new customer (SIRET not found).
     */
    public function testIdentifiesNewCustomerWhenSiretNotFound(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers
        $headers = ['SIRET', 'Raison sociale'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(1);

        // Mock row data
        $rowData = [
            'SIRET' => '12345678901234',
            'Raison sociale' => 'New Company',
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([[$rowData]]));

        // Mock: customer NOT found by SIRET
        $this->customerRepository->method('findOneBy')
            ->willReturn(null);

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should identify as CREATE operation
        $this->assertSame(
            1,
            $result->creations[Customer::class] ?? 0,
            'Should create new customer (SIRET not found)'
        );
        $this->assertSame(
            0,
            $result->updates[Customer::class] ?? 0,
            'Should not mark for UPDATE (customer does not exist)'
        );
    }

    /**
     * Test that analyzer matches by SIRET first, then by name.
     */
    public function testMatchesBySiretBeforeName(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers
        $headers = ['SIRET', 'Raison sociale'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(1);

        // Mock row data with both SIRET and name
        $rowData = [
            'SIRET' => '12345678901234',
            'Raison sociale' => 'Test Company',
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([[$rowData]]));

        // Mock: customer found by SIRET
        $existingCustomer = $this->createMock(Customer::class);
        $existingCustomer->method('getId')->willReturn(1);
        $existingCustomer->method('getName')->willReturn('Old Name');
        $existingCustomer->method('getSiret')->willReturn('12345678901234');
        $existingCustomer->method('getLeadOrigin')->willReturn('Old Origin');

        // Expect SIRET lookup to be called first
        $this->customerRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['siret' => '12345678901234'])
            ->willReturn($existingCustomer);

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should mark as UPDATE (found by SIRET)
        $this->assertSame(
            1,
            $result->updates[Customer::class] ?? 0,
            'Should mark for UPDATE when found by SIRET'
        );
    }

    /**
     * Test that analyzer falls back to name matching if SIRET not provided.
     */
    public function testFallsBackToNameMatchingWhenNoSiret(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers (no SIRET column)
        $headers = ['Raison sociale'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(1);

        // Mock row data without SIRET
        $rowData = [
            'Raison sociale' => 'Test Company',
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([[$rowData]]));

        // Mock: customer found by name
        $existingCustomer = $this->createMock(Customer::class);

        $this->customerRepository->method('findOneBy')
            ->with(['name' => 'Test Company'])
            ->willReturn($existingCustomer);

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should mark as UPDATE (found by name)
        $this->assertSame(
            1,
            $result->updates[Customer::class] ?? 0,
            'Should mark for UPDATE when found by name'
        );
    }

    /**
     * Test that analyzer validates required fields (name).
     */
    public function testValidatesRequiredFields(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers
        $headers = ['SIRET'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(1);

        // Mock row data WITHOUT required name field
        $rowData = [
            'SIRET' => '12345678901234',
            // Missing 'Raison sociale'
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([[$rowData]]));

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should record an error
        $this->assertSame(
            1,
            $result->errorRows,
            'Should record error when required field is missing'
        );
        $this->assertSame(
            0,
            $result->getTotalCreations(),
            'Should not create any records with validation errors'
        );
    }

    /**
     * Test that analyzer skips empty rows.
     */
    public function testSkipsEmptyRows(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers
        $headers = ['SIRET', 'Raison sociale'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(2);

        // Mock row data: one valid, one empty
        $validRow = [
            'SIRET' => '12345678901234',
            'Raison sociale' => 'Test Company',
        ];
        $emptyRow = [
            'SIRET' => null,
            'Raison sociale' => '',
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([[$validRow, $emptyRow]]));

        // Mock: customer not found (new)
        $this->customerRepository->method('findOneBy')
            ->willReturn(null);

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should only process valid row
        $this->assertSame(
            1,
            $result->creations[Customer::class] ?? 0,
            'Should only process non-empty rows'
        );
    }

    /**
     * Test that analyzer handles batch processing correctly.
     */
    public function testHandlesBatchProcessing(): void
    {
        // Prepare test data
        $import = $this->createMockImport(ImportType::FULL);
        $filePath = '/tmp/test.xlsx';

        // Mock file headers
        $headers = ['SIRET', 'Raison sociale'];
        $this->excelReader->method('getHeaders')->willReturn($headers);
        $this->excelReader->method('getTotalRows')->willReturn(3);

        // Mock row data in batches
        $batch1 = [
            ['SIRET' => '11111111111111', 'Raison sociale' => 'Company 1'],
            ['SIRET' => '22222222222222', 'Raison sociale' => 'Company 2'],
        ];
        $batch2 = [
            ['SIRET' => '33333333333333', 'Raison sociale' => 'Company 3'],
        ];

        $this->excelReader->method('readRowsInBatches')
            ->willReturn($this->generateBatches([$batch1, $batch2]));

        // Mock: no customers found (all new)
        $this->customerRepository->method('findOneBy')
            ->willReturn(null);

        // Execute analysis
        $result = $this->analyzer->analyze($filePath, $import);

        // Verify: Should process all rows across batches
        $this->assertSame(
            3,
            $result->creations[Customer::class] ?? 0,
            'Should process all rows across multiple batches'
        );
        $this->assertSame(3, $result->totalRows, 'Total rows should match file');
    }

    /**
     * Create a mock Import entity for testing.
     */
    private function createMockImport(ImportType $type): Import
    {
        $import = $this->createMock(Import::class);
        $import->method('getId')->willReturn(1);
        $import->method('getType')->willReturn($type);

        // Mock Collection objects for analysisResults and errors
        $analysisResults = $this->createMock(\Doctrine\Common\Collections\Collection::class);
        $analysisResults->method('count')->willReturn(0);
        $analysisResults->method('getIterator')->willReturn(new \ArrayIterator([]));

        $errors = $this->createMock(\Doctrine\Common\Collections\Collection::class);
        $errors->method('count')->willReturn(0);
        $errors->method('getIterator')->willReturn(new \ArrayIterator([]));

        $import->method('getAnalysisResults')->willReturn($analysisResults);
        $import->method('getErrors')->willReturn($errors);

        return $import;
    }

    /**
     * Helper method to create a Generator from batches array.
     *
     * @param array<int, array<int, array<string, mixed>>> $batches
     *
     * @return \Generator<int, array<int, array<string, mixed>>>
     */
    private function generateBatches(array $batches): \Generator
    {
        foreach ($batches as $batch) {
            yield $batch;
        }
    }
}
