<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use App\Domain\Import\Message\AnalyzeImportMessage;
use App\Domain\Import\MessageHandler\AnalyzeImportMessageHandler;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyType;
use App\Entity\Import;
use App\Entity\ImportOperationType;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Functional test for import analysis functionality.
 *
 * Tests that the import analyzer correctly identifies:
 * - Which customers will be created vs updated (based on SIRET)
 * - Which contacts will be created
 * - Which energies will be created
 */
class ImportAnalysisTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AnalyzeImportMessageHandler $analyzeHandler;
    private Company $testCompany;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->analyzeHandler = $container->get(AnalyzeImportMessageHandler::class);

        // Create test company and user
        $this->testCompany = new Company();
        $this->testCompany->setName('Test Company '.uniqid());
        $this->entityManager->persist($this->testCompany);

        $this->testUser = new User();
        $this->testUser->setEmail('test-'.uniqid().'@example.com');
        $this->testUser->setPassword('test');
        $this->testUser->setRoles(['ROLE_USER']);
        $this->testUser->setCompany($this->testCompany);
        $this->entityManager->persist($this->testUser);

        $this->entityManager->flush();
    }

    /**
     * Test analysis with all existing customers (by SIRET).
     *
     * This test reproduces the reported bug where the analysis incorrectly
     * reports "2 new, 1 update" instead of "3 updates, 3 contacts to create, 3 energies to create".
     */
    public function testAnalysisWithAllExistingCustomersBySiret(): void
    {
        // 1. Create 3 existing customers with the exact SIRETs from the example file
        $customer1 = $this->createCustomer('BOULANGERIE MARTIN', '12345678901234');
        $customer2 = $this->createCustomer('GARAGE DUPONT SARL', '98765432109876');
        $customer3 = $this->createCustomer('RESTAURANT LE BON COIN', '11122233344455');

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 2. Create import with the example file
        $import = $this->createImportWithExampleFile();

        // 3. Mark import as analyzing (required by handler)
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // 4. Analyze the import
        $analyzeMessage = new AnalyzeImportMessage($import->getId());
        $this->analyzeHandler->__invoke($analyzeMessage);

        // 5. Refresh the import entity to get analysis results
        $this->entityManager->refresh($import);

        // 6. Verify the import status
        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation after analysis'
        );

        // 7. Verify total rows detected
        $this->assertSame(3, $import->getTotalRows(), 'Should detect 3 rows in the file');

        // 8. Verify no errors
        $this->assertCount(0, $import->getErrors(), 'Should have no errors');

        // 9. Get analysis results grouped by entity type and operation
        $analysisResults = $import->getAnalysisResults();
        $resultsByEntityAndOperation = [];

        foreach ($analysisResults as $result) {
            $entityType = $result->getEntityType();
            $operationType = $result->getOperationType()->value;
            $resultsByEntityAndOperation[$entityType][$operationType] = $result->getCount();
        }

        // 10. CUSTOMER ANALYSIS: Should show 3 updates (all SIRETs exist)
        $this->assertArrayHasKey(
            Customer::class,
            $resultsByEntityAndOperation,
            'Analysis should include Customer entity'
        );

        $customerResults = $resultsByEntityAndOperation[Customer::class];

        // All 3 customers should be marked for UPDATE (not CREATE)
        $this->assertArrayHasKey(
            ImportOperationType::UPDATE->value,
            $customerResults,
            'Should have UPDATE operations for customers'
        );
        $this->assertSame(
            3,
            $customerResults[ImportOperationType::UPDATE->value],
            'Should show 3 customers to UPDATE (all exist by SIRET)'
        );

        // No new customers should be created
        $this->assertArrayNotHasKey(
            ImportOperationType::CREATE->value,
            $customerResults,
            'Should NOT show any customers to CREATE (all exist)'
        );

        // 10. CONTACT ANALYSIS: Should show 3 new contacts to create
        // This is currently MISSING from the analyzer - this assertion will fail until fixed
        $this->assertArrayHasKey(
            Contact::class,
            $resultsByEntityAndOperation,
            'Analysis should include Contact entity (currently missing - BUG)'
        );

        $contactResults = $resultsByEntityAndOperation[Contact::class];
        $this->assertArrayHasKey(
            ImportOperationType::CREATE->value,
            $contactResults,
            'Should show CREATE operations for contacts'
        );
        $this->assertSame(
            3,
            $contactResults[ImportOperationType::CREATE->value],
            'Should show 3 contacts to CREATE (one per customer)'
        );

        // 11. ENERGY ANALYSIS: Should show 3 new energies to create
        // This is currently MISSING from the analyzer - this assertion will fail until fixed
        $this->assertArrayHasKey(
            Energy::class,
            $resultsByEntityAndOperation,
            'Analysis should include Energy entity (currently missing - BUG)'
        );

        $energyResults = $resultsByEntityAndOperation[Energy::class];
        $this->assertArrayHasKey(
            ImportOperationType::CREATE->value,
            $energyResults,
            'Should show CREATE operations for energies'
        );
        $this->assertSame(
            3,
            $energyResults[ImportOperationType::CREATE->value],
            'Should show 3 energies to CREATE (one per customer)'
        );
    }

    /**
     * Test analysis with mixed scenario: some existing, some new customers.
     */
    public function testAnalysisWithMixedCustomers(): void
    {
        // 1. Create only 2 existing customers (leave one to be created)
        $customer1 = $this->createCustomer('BOULANGERIE MARTIN', '12345678901234');
        $customer2 = $this->createCustomer('GARAGE DUPONT SARL', '98765432109876');
        // customer3 with SIRET 11122233344455 will be NEW

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 2. Create import with the example file
        $import = $this->createImportWithExampleFile();

        // 3. Mark import as analyzing (required by handler)
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // 4. Analyze the import
        $analyzeMessage = new AnalyzeImportMessage($import->getId());
        $this->analyzeHandler->__invoke($analyzeMessage);

        // 5. Refresh and verify
        $this->entityManager->refresh($import);

        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation'
        );

        // 5. Get analysis results
        $analysisResults = $import->getAnalysisResults();
        $resultsByEntityAndOperation = [];

        foreach ($analysisResults as $result) {
            $entityType = $result->getEntityType();
            $operationType = $result->getOperationType()->value;
            $resultsByEntityAndOperation[$entityType][$operationType] = $result->getCount();
        }

        // 6. Verify customer operations
        $customerResults = $resultsByEntityAndOperation[Customer::class];

        $this->assertSame(
            1,
            $customerResults[ImportOperationType::CREATE->value] ?? 0,
            'Should show 1 customer to CREATE (SIRET 11122233344455)'
        );
        $this->assertSame(
            2,
            $customerResults[ImportOperationType::UPDATE->value] ?? 0,
            'Should show 2 customers to UPDATE (SIRETs 12345678901234 and 98765432109876)'
        );

        // 7. Verify all contacts will be created (even for existing customers)
        $this->assertArrayHasKey(
            Contact::class,
            $resultsByEntityAndOperation,
            'Analysis should include Contact entity'
        );
        $this->assertSame(
            3,
            $resultsByEntityAndOperation[Contact::class][ImportOperationType::CREATE->value] ?? 0,
            'Should show 3 contacts to CREATE'
        );

        // 8. Verify all energies will be created
        $this->assertArrayHasKey(
            Energy::class,
            $resultsByEntityAndOperation,
            'Analysis should include Energy entity'
        );
        $this->assertSame(
            3,
            $resultsByEntityAndOperation[Energy::class][ImportOperationType::CREATE->value] ?? 0,
            'Should show 3 energies to CREATE'
        );
    }

    /**
     * Test analysis with no existing customers (all new).
     */
    public function testAnalysisWithAllNewCustomers(): void
    {
        // 1. Don't create any existing customers - all will be new

        // 2. Create import with the example file
        $import = $this->createImportWithExampleFile();

        // 3. Mark import as analyzing (required by handler)
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // 4. Analyze the import
        $analyzeMessage = new AnalyzeImportMessage($import->getId());
        $this->analyzeHandler->__invoke($analyzeMessage);

        // 5. Refresh and verify
        $this->entityManager->refresh($import);

        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation'
        );

        // 5. Get analysis results
        $analysisResults = $import->getAnalysisResults();
        $resultsByEntityAndOperation = [];

        foreach ($analysisResults as $result) {
            $entityType = $result->getEntityType();
            $operationType = $result->getOperationType()->value;
            $resultsByEntityAndOperation[$entityType][$operationType] = $result->getCount();
        }

        // 6. Verify all customers are new
        $customerResults = $resultsByEntityAndOperation[Customer::class] ?? [];

        $this->assertSame(
            3,
            $customerResults[ImportOperationType::CREATE->value] ?? 0,
            'Should show 3 customers to CREATE (none exist)'
        );
        $this->assertArrayNotHasKey(
            ImportOperationType::UPDATE->value,
            $customerResults,
            'Should NOT show any customers to UPDATE'
        );

        // 7. Verify all contacts will be created
        $this->assertArrayHasKey(
            Contact::class,
            $resultsByEntityAndOperation,
            'Analysis should include Contact entity'
        );
        $this->assertSame(
            3,
            $resultsByEntityAndOperation[Contact::class][ImportOperationType::CREATE->value] ?? 0,
            'Should show 3 contacts to CREATE'
        );

        // 8. Verify all energies will be created
        $this->assertArrayHasKey(
            Energy::class,
            $resultsByEntityAndOperation,
            'Analysis should include Energy entity'
        );
        $this->assertSame(
            3,
            $resultsByEntityAndOperation[Energy::class][ImportOperationType::CREATE->value] ?? 0,
            'Should show 3 energies to CREATE'
        );
    }

    /**
     * Test that customers are matched by SIRET, not by name.
     */
    public function testCustomerMatchingBySiretNotByName(): void
    {
        // 1. Create a customer with same SIRET but different name
        $customer = $this->createCustomer('OLD NAME', '12345678901234');

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 2. Create import (file has "BOULANGERIE MARTIN" with same SIRET)
        $import = $this->createImportWithExampleFile();

        // 3. Mark import as analyzing (required by handler)
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // 4. Analyze
        $analyzeMessage = new AnalyzeImportMessage($import->getId());
        $this->analyzeHandler->__invoke($analyzeMessage);

        // 4. Verify
        $this->entityManager->refresh($import);

        $analysisResults = $import->getAnalysisResults();
        $resultsByEntityAndOperation = [];

        foreach ($analysisResults as $result) {
            $entityType = $result->getEntityType();
            $operationType = $result->getOperationType()->value;
            $resultsByEntityAndOperation[$entityType][$operationType] = $result->getCount();
        }

        // Should match by SIRET and mark as UPDATE
        $customerResults = $resultsByEntityAndOperation[Customer::class] ?? [];

        // At least 1 customer should be marked for UPDATE (the one with matching SIRET)
        $this->assertGreaterThanOrEqual(
            1,
            $customerResults[ImportOperationType::UPDATE->value] ?? 0,
            'Customer with matching SIRET should be marked for UPDATE (even with different name)'
        );
    }

    /**
     * Test analysis with existing customers that already have contacts and energies.
     */
    public function testAnalysisWithExistingCustomersAndRelatedEntities(): void
    {
        // 1. Create customer with existing contact and energy
        $customer = $this->createCustomer('BOULANGERIE MARTIN', '12345678901234');

        $existingContact = new Contact();
        $existingContact->setCustomer($customer);
        $existingContact->setFirstName('Existing');
        $existingContact->setLastName('Contact');
        $existingContact->setEmail('existing@test.com');
        $this->entityManager->persist($existingContact);

        $existingEnergy = new Energy();
        $existingEnergy->setCustomer($customer);
        $existingEnergy->setType(EnergyType::ELEC);
        $existingEnergy->setCode('EXISTING123');
        $this->entityManager->persist($existingEnergy);

        $this->entityManager->flush();
        $this->entityManager->clear();

        // 2. Create import
        $import = $this->createImportWithExampleFile();

        // 3. Mark import as analyzing (required by handler)
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // 4. Analyze
        $analyzeMessage = new AnalyzeImportMessage($import->getId());
        $this->analyzeHandler->__invoke($analyzeMessage);

        // 5. Verify
        $this->entityManager->refresh($import);

        $analysisResults = $import->getAnalysisResults();
        $resultsByEntityAndOperation = [];

        foreach ($analysisResults as $result) {
            $entityType = $result->getEntityType();
            $operationType = $result->getOperationType()->value;
            $resultsByEntityAndOperation[$entityType][$operationType] = $result->getCount();
        }

        // The existing customer should be marked for UPDATE
        $this->assertGreaterThanOrEqual(
            1,
            $resultsByEntityAndOperation[Customer::class][ImportOperationType::UPDATE->value] ?? 0,
            'Existing customer should be marked for UPDATE'
        );

        // New contacts should still be created (import creates additional contacts)
        $this->assertGreaterThanOrEqual(
            1,
            $resultsByEntityAndOperation[Contact::class][ImportOperationType::CREATE->value] ?? 0,
            'New contacts should be created even for existing customers'
        );

        // New energies should still be created (import creates additional energies)
        $this->assertGreaterThanOrEqual(
            1,
            $resultsByEntityAndOperation[Energy::class][ImportOperationType::CREATE->value] ?? 0,
            'New energies should be created even for existing customers'
        );
    }

    /**
     * Helper method to create a customer with given name and SIRET.
     */
    private function createCustomer(string $name, string $siret): Customer
    {
        $customer = new Customer();
        $customer->setName($name);
        $customer->setSiret($siret);
        $customer->setOrigin(\App\Entity\ProspectOrigin::ACQUISITION);
        // Note: Customer entity doesn't have a direct company relationship

        $this->entityManager->persist($customer);

        return $customer;
    }

    /**
     * Helper method to create an Import entity with the example file.
     */
    private function createImportWithExampleFile(): Import
    {
        // Ensure example file exists
        $this->generateExampleFile();

        $exampleFilePath = self::getContainer()->getParameter('kernel.project_dir').'/public/examples/import_complet_exemple.xlsx';
        $this->assertFileExists($exampleFilePath, 'Example file must exist');

        // Copy to import directory
        $storedFilename = 'test-analysis-'.uniqid().'.xlsx';
        $importDir = self::getContainer()->getParameter('kernel.project_dir').'/var/import';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0755, true);
        }
        copy($exampleFilePath, $importDir.'/'.$storedFilename);

        // Re-fetch the user from the database to ensure it's managed
        $user = $this->entityManager->find(User::class, $this->testUser->getId());
        if (null === $user) {
            throw new \RuntimeException('Test user not found in database');
        }

        // Create import entity
        $import = new Import();
        $import->setType(ImportType::FULL);
        $import->setUser($user);
        $import->setOriginalFilename('import_complet_exemple.xlsx');
        $import->setStoredFilename($storedFilename);
        $import->setStatus(ImportStatus::PENDING);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Generate the example Excel file.
     */
    private function generateExampleFile(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        exec("cd $projectDir && bin/console app:import:generate-examples", $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('Failed to generate example files');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->clear();
    }
}
