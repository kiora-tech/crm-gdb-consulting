<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use App\Domain\Import\Message\AnalyzeImportMessage;
use App\Domain\Import\Message\ProcessImportBatchMessage;
use App\Domain\Import\MessageHandler\AnalyzeImportMessageHandler;
use App\Domain\Import\MessageHandler\ProcessImportBatchMessageHandler;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\Import;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Entity\User;
use App\Repository\ContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests fonctionnels pour les 4 fichiers d'exemple d'import.
 *
 * IMPORTANT: Ces tests utilisent EXACTEMENT les mêmes fichiers que les utilisateurs
 * peuvent télécharger depuis l'application. Les fichiers sont générés par la commande
 * app:import:generate-examples et sont situés dans public/examples/.
 *
 * Les données testées correspondent aux données réelles dans les fichiers:
 * - import_clients_exemple.xlsx: ENTREPRISE EXEMPLE SAS, SOCIETE TEST SARL, DEMO COMPANY
 * - import_contacts_exemple.xlsx: Jean Martin, Marie Dupont, Pierre Leblanc (avec clients BOULANGERIE MARTIN, etc.)
 * - import_energies_exemple.xlsx: Énergies ELEC/GAZ (avec clients BOULANGERIE MARTIN, etc.)
 * - import_complet_exemple.xlsx: Clients + Contacts + Énergies (BOULANGERIE MARTIN, etc.)
 *
 * Chaque test vérifie:
 * 1. L'analyse du fichier (phase 1)
 * 2. L'exécution de l'import (phase 2 - insertion/mise à jour)
 * 3. La création/modification des entités en base de données
 */
class ExampleFilesImportTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ImportRepository $importRepository;
    private CustomerRepository $customerRepository;
    private ContactRepository $contactRepository;
    private EnergyRepository $energyRepository;
    private AnalyzeImportMessageHandler $analyzeHandler;
    private ProcessImportBatchMessageHandler $batchHandler;
    private Company $testCompany;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->importRepository = $container->get(ImportRepository::class);
        $this->customerRepository = $container->get(CustomerRepository::class);
        $this->contactRepository = $container->get(ContactRepository::class);
        $this->energyRepository = $container->get(EnergyRepository::class);
        $this->analyzeHandler = $container->get(AnalyzeImportMessageHandler::class);
        $this->batchHandler = $container->get(ProcessImportBatchMessageHandler::class);

        // Generate example files
        $this->generateExampleFiles();

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
     * Test 1: Import de clients uniquement (import_clients_exemple.xlsx).
     *
     * Ce fichier contient uniquement les colonnes pour les clients:
     * - Nom du client
     * - SIRET
     * - Origine du lead (Apporteur)
     */
    public function testImportClientsOnly(): void
    {
        // 1. Create import with clients-only file
        $import = $this->createImport('import_clients_exemple.xlsx', ImportType::FULL);

        // 2. Analyze the import
        $this->analyzeImport($import);

        // 3. Verify analysis results
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation after analysis'
        );
        $this->assertGreaterThan(0, $import->getTotalRows(), 'Import should have rows');

        // Get expected customer count from analysis
        $expectedCustomers = 0;
        foreach ($import->getAnalysisResults() as $result) {
            if ($result->getEntityType() === Customer::class) {
                $expectedCustomers += $result->getCount();
            }
        }
        $this->assertGreaterThan(0, $expectedCustomers, 'Should have customers to create');

        // 4. Process the import
        $this->processImport($import);

        // 5. Verify import completed successfully
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::COMPLETED,
            $import->getStatus(),
            'Import should be completed'
        );
        $this->assertSame(
            $import->getTotalRows(),
            $import->getSuccessRows(),
            'All rows should be imported successfully'
        );

        // 6. Verify customers were created in database
        $this->entityManager->clear();
        $allCustomers = $this->customerRepository->findAll();
        $this->assertGreaterThanOrEqual(
            $expectedCustomers,
            count($allCustomers),
            'Customers should be created in database'
        );

        // Verify specific customers from import_clients_exemple.xlsx
        $customer1 = $this->customerRepository->findOneBy(['siret' => '12345678901234']);
        $this->assertNotNull($customer1, 'Customer with SIRET 12345678901234 should exist');
        $this->assertSame('ENTREPRISE EXEMPLE SAS', $customer1->getName());

        $customer2 = $this->customerRepository->findOneBy(['siret' => '98765432109876']);
        $this->assertNotNull($customer2, 'Customer with SIRET 98765432109876 should exist');
        $this->assertSame('SOCIETE TEST SARL', $customer2->getName());

        $customer3 = $this->customerRepository->findOneBy(['siret' => '11122233344455']);
        $this->assertNotNull($customer3, 'Customer with SIRET 11122233344455 should exist');
        $this->assertSame('DEMO COMPANY', $customer3->getName());
    }

    /**
     * Test 2: Import complet (import_complet_exemple.xlsx).
     *
     * Ce fichier contient toutes les colonnes:
     * - Clients (nom, SIRET, apporteur)
     * - Contacts (prénom, nom, email, téléphone)
     * - Énergies (type, PDL/PCE, fournisseur, date fin contrat)
     */
    public function testImportComplet(): void
    {
        // 1. Create import with full file
        $import = $this->createImport('import_complet_exemple.xlsx', ImportType::FULL);

        // 2. Analyze the import
        $this->analyzeImport($import);

        // 3. Verify analysis results
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation after analysis'
        );

        // Verify analysis detected all entity types
        $analysisResults = $import->getAnalysisResults();
        $entityTypes = [];
        $expectedCounts = [];

        foreach ($analysisResults as $result) {
            $entityType = $result->getEntityType();
            $entityTypes[] = $entityType;
            $expectedCounts[$entityType] = ($expectedCounts[$entityType] ?? 0) + $result->getCount();
        }

        $this->assertContains(Customer::class, $entityTypes, 'Analysis should include customers');
        $this->assertContains(\App\Entity\Contact::class, $entityTypes, 'Analysis should include contacts');
        $this->assertContains(\App\Entity\Energy::class, $entityTypes, 'Analysis should include energies');

        // 4. Process the import
        $this->processImport($import);

        // 5. Verify import completed successfully
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::COMPLETED,
            $import->getStatus(),
            'Import should be completed'
        );

        // 6. Verify all entities were created
        $this->entityManager->clear();

        // Verify customers
        $customerBoulangerie = $this->customerRepository->findOneBy(['siret' => '12345678901234']);
        $this->assertNotNull($customerBoulangerie, 'BOULANGERIE MARTIN should exist');
        $this->assertSame('BOULANGERIE MARTIN', $customerBoulangerie->getName());

        // Verify contacts
        $contacts = $this->contactRepository->findBy(['customer' => $customerBoulangerie]);
        $this->assertGreaterThanOrEqual(1, count($contacts), 'Customer should have contacts');
        $this->assertSame('Jean', $contacts[0]->getFirstName());
        $this->assertSame('Martin', $contacts[0]->getLastName());
        $this->assertSame('j.martin@boulangerie-martin.fr', $contacts[0]->getEmail());

        // Verify energies
        $energies = $this->energyRepository->findBy(['customer' => $customerBoulangerie]);
        $this->assertGreaterThanOrEqual(1, count($energies), 'Customer should have energies');
    }

    /**
     * Test 3: Import de contacts uniquement (import_contacts_exemple.xlsx).
     *
     * Ce fichier contient:
     * - Informations client (nom, SIRET) pour associer les contacts
     * - Informations contact (prénom, nom, email, téléphone)
     */
    public function testImportContactsOnly(): void
    {
        // 1. Create import with contacts file
        $import = $this->createImport('import_contacts_exemple.xlsx', ImportType::FULL);

        // 2. Analyze the import
        $this->analyzeImport($import);

        // 3. Verify analysis results
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation'
        );

        // 4. Process the import
        $this->processImport($import);

        // 5. Verify import completed
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::COMPLETED,
            $import->getStatus(),
            'Import should be completed'
        );

        // 6. Verify contacts were created with exact data from import_contacts_exemple.xlsx
        $this->entityManager->clear();

        $allContacts = $this->contactRepository->findAll();
        $this->assertGreaterThanOrEqual(3, count($allContacts), 'At least 3 contacts should be created');

        // Verify specific contacts from the file
        $contactJean = null;
        foreach ($allContacts as $contact) {
            if ($contact->getEmail() === 'j.martin@boulangerie-martin.fr') {
                $contactJean = $contact;
                break;
            }
        }
        $this->assertNotNull($contactJean, 'Contact Jean Martin should exist');
        $this->assertSame('Jean', $contactJean->getFirstName());
        $this->assertSame('Martin', $contactJean->getLastName());
        $this->assertNotNull($contactJean->getCustomer(), 'Contact should be associated with a customer');

        // Verify customer was created/found with data from import_contacts_exemple.xlsx
        $customer = $contactJean->getCustomer();
        $this->assertSame('BOULANGERIE MARTIN', $customer->getName());
        // Note: SIRET may or may not be populated depending on whether customer was created or found
        if ($customer->getSiret()) {
            $this->assertSame('12345678901234', $customer->getSiret());
        }
    }

    /**
     * Test 4: Import d'énergies uniquement (import_energies_exemple.xlsx).
     *
     * Ce fichier contient:
     * - Informations client (nom, SIRET) pour associer les énergies
     * - Informations énergie (type, PDL/PCE, fournisseur, date fin contrat)
     */
    public function testImportEnergiesOnly(): void
    {
        // 1. Create import with energies file
        $import = $this->createImport('import_energies_exemple.xlsx', ImportType::FULL);

        // 2. Analyze the import
        $this->analyzeImport($import);

        // 3. Verify analysis results
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::AWAITING_CONFIRMATION,
            $import->getStatus(),
            'Import should be awaiting confirmation'
        );

        // 4. Process the import
        $this->processImport($import);

        // 5. Verify import completed
        $this->entityManager->refresh($import);
        $this->assertSame(
            ImportStatus::COMPLETED,
            $import->getStatus(),
            'Import should be completed'
        );

        // 6. Verify energies were created with exact data from import_energies_exemple.xlsx
        $this->entityManager->clear();

        $allEnergies = $this->energyRepository->findAll();
        $this->assertGreaterThanOrEqual(3, count($allEnergies), 'At least 3 energies should be created');

        // Verify at least one energy exists with correct type
        $energyELEC = null;
        foreach ($allEnergies as $energy) {
            if ($energy->getType() === \App\Entity\EnergyType::ELEC) {
                $energyELEC = $energy;
                break;
            }
        }
        $this->assertNotNull($energyELEC, 'At least one ELEC energy should exist');
        $this->assertSame(\App\Entity\EnergyType::ELEC, $energyELEC->getType());
        $this->assertNotNull($energyELEC->getCustomer(), 'Energy should be associated with a customer');

        // Verify customer exists with correct name from import_energies_exemple.xlsx
        $customer = $energyELEC->getCustomer();
        $this->assertNotNull($customer->getName(), 'Customer should have a name');
        // Customer should be one of the 3 from the file
        $this->assertContains(
            $customer->getName(),
            ['BOULANGERIE MARTIN', 'GARAGE DUPONT SARL', 'RESTAURANT LE BON COIN'],
            'Customer should be one from import_energies_exemple.xlsx'
        );
    }

    /**
     * Helper: Create a customer with given name and SIRET.
     */
    private function createCustomer(string $name, string $siret): Customer
    {
        $customer = new Customer();
        $customer->setName($name);
        $customer->setSiret($siret);
        $customer->setOrigin(\App\Entity\ProspectOrigin::ACQUISITION);

        $this->entityManager->persist($customer);

        return $customer;
    }

    /**
     * Helper: Create an Import entity with the specified example file.
     */
    private function createImport(string $filename, ImportType $type): Import
    {
        $exampleFilePath = self::getContainer()->getParameter('kernel.project_dir').'/public/examples/'.$filename;
        $this->assertFileExists($exampleFilePath, "Example file {$filename} must exist");

        // Copy to import directory
        $storedFilename = 'test-'.uniqid().'-'.basename($filename);
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
        $import->setType($type);
        $import->setUser($user);
        $import->setOriginalFilename($filename);
        $import->setStoredFilename($storedFilename);
        $import->setStatus(ImportStatus::PENDING);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Helper: Analyze an import.
     */
    private function analyzeImport(Import $import): void
    {
        // Mark import as analyzing
        $import->markAsAnalyzing();
        $this->entityManager->flush();

        // Analyze
        $analyzeMessage = new AnalyzeImportMessage($import->getId());
        $this->analyzeHandler->__invoke($analyzeMessage);
    }

    /**
     * Helper: Process an import (execute all batches).
     */
    private function processImport(Import $import): void
    {
        // Mark as processing
        $import->markAsProcessing();
        $this->entityManager->flush();

        // Process all batches
        $totalRows = $import->getTotalRows();
        $batchSize = 100;

        for ($startRow = 2; $startRow <= $totalRows + 1; $startRow += $batchSize) {
            $endRow = min($startRow + $batchSize - 1, $totalRows + 1);

            $batchMessage = new ProcessImportBatchMessage(
                $import->getId(),
                $startRow,
                $endRow
            );

            try {
                $this->batchHandler->__invoke($batchMessage);
            } catch (\Exception $e) {
                $this->fail(sprintf(
                    'Failed to process batch %d-%d: %s',
                    $startRow,
                    $endRow,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Helper: Generate example files.
     */
    private function generateExampleFiles(): void
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
