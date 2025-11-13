<?php

declare(strict_types=1);

namespace App\Domain\Import\Service\Analyzer;

use App\Domain\Import\Contract\ImportAnalyzerInterface;
use App\Domain\Import\Service\ExcelReaderService;
use App\Domain\Import\ValueObject\AnalysisImpact;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyType;
use App\Entity\Import;
use App\Entity\ImportAnalysisResult;
use App\Entity\ImportError;
use App\Entity\ImportErrorSeverity;
use App\Entity\ImportOperationType;
use App\Entity\ImportType;
use App\Repository\ContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Analyzer for customer and full imports.
 *
 * This analyzer processes Excel files containing customer data and determines
 * which customers would be created, updated, or skipped during import execution.
 */
#[AutoconfigureTag('app.import_analyzer')]
class CustomerImportAnalyzer implements ImportAnalyzerInterface
{
    /**
     * Batch size for reading Excel rows to manage memory usage.
     */
    private const int BATCH_SIZE = 100;

    /**
     * @var array<string, int> Track creation counts by entity type
     */
    private array $creations = [];

    /**
     * @var array<string, int> Track update counts by entity type
     */
    private array $updates = [];

    /**
     * @var array<string, int> Track skip counts by entity type
     */
    private array $skips = [];

    /**
     * @var array<string, array<int, array<string, mixed>>> Track update details by entity type
     * Format: ['EntityClass' => [['entity_id' => 1, 'entity_label' => 'Name', 'fields' => ['field' => ['old' => 'X', 'new' => 'Y']]]]]
     */
    private array $updateDetails = [];

    /**
     * @var ?Energy Temporarily store the last found energy for change tracking
     */
    private ?Energy $lastFoundEnergy = null;

    private int $errorCount = 0;

    public function __construct(
        private readonly ExcelReaderService $excelReader,
        private readonly CustomerRepository $customerRepository,
        private readonly ContactRepository $contactRepository,
        private readonly EnergyRepository $energyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function analyze(string $filePath, object $import): AnalysisImpact
    {
        if (!$import instanceof Import) {
            throw new \InvalidArgumentException('Import entity must be an instance of Import');
        }

        $this->logger->info('Starting customer import analysis', [
            'import_id' => $import->getId(),
            'file_path' => $filePath,
            'import_type' => $import->getType()->value,
        ]);

        // Reset counters
        $this->creations = [];
        $this->updates = [];
        $this->skips = [];
        $this->updateDetails = [];
        $this->errorCount = 0;

        try {
            // Validate file headers before processing
            $this->validateHeaders($filePath);

            // Get total rows for progress tracking
            $totalRows = $this->excelReader->getTotalRows($filePath);
            $import->setTotalRows($totalRows);

            $this->logger->info('Total rows to analyze', ['total_rows' => $totalRows]);

            // Clear any existing analysis results and errors
            foreach ($import->getAnalysisResults() as $result) {
                $import->removeAnalysisResult($result);
            }
            foreach ($import->getErrors() as $error) {
                $import->removeError($error);
            }

            // Process file in batches
            $rowNumber = 2; // Start at 2 (1 is header)

            foreach ($this->excelReader->readRowsInBatches($filePath, self::BATCH_SIZE) as $batch) {
                foreach ($batch as $row) {
                    $this->analyzeRow($import, $row, $rowNumber);
                    ++$rowNumber;
                }

                // Flush periodically to avoid memory issues
                $this->entityManager->flush();
            }

            // Create ImportAnalysisResult entities for each operation type
            $this->createAnalysisResults($import);

            // Final flush
            $this->entityManager->flush();

            $analysisImpact = new AnalysisImpact(
                creations: $this->creations,
                updates: $this->updates,
                skips: $this->skips,
                totalRows: $totalRows,
                errorRows: $this->errorCount,
            );

            $this->logger->info('Customer import analysis completed', [
                'import_id' => $import->getId(),
                'total_rows' => $totalRows,
                'creations' => $analysisImpact->getTotalCreations(),
                'updates' => $analysisImpact->getTotalUpdates(),
                'skips' => $analysisImpact->getTotalSkips(),
                'errors' => $this->errorCount,
                'success_rate' => $analysisImpact->getSuccessRate(),
            ]);

            return $analysisImpact;
        } catch (\Exception $e) {
            $this->logger->error('Error during customer import analysis', [
                'import_id' => $import->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(sprintf('Échec de l\'analyse de l\'import: %s', $e->getMessage()), 0, $e);
        }
    }

    public function supports(ImportType $type): bool
    {
        return ImportType::CUSTOMER === $type || ImportType::FULL === $type;
    }

    /**
     * Validate Excel file headers for duplicate columns.
     *
     * @throws \RuntimeException If duplicate column names are detected
     */
    private function validateHeaders(string $filePath): void
    {
        $headers = $this->excelReader->getHeaders($filePath);

        $this->logger->debug('Validating file headers', [
            'headers' => $headers,
        ]);

        // Normalize headers and detect duplicates
        $normalizedHeaders = [];
        $duplicates = [];

        foreach ($headers as $position => $header) {
            $headerStr = (string) $header;
            if ('' === trim($headerStr)) {
                continue; // Skip empty headers
            }

            $normalized = $this->normalizeHeaderKey($headerStr);

            if (isset($normalizedHeaders[$normalized])) {
                // Special case: Allow duplicate "name" columns (for customer name + contact lastname)
                // The normalizeRowData method handles this by converting the second occurrence to "contact_lastname"
                if ('name' === $normalized) {
                    // Count how many times we've seen "name"
                    $occurrences = array_count_values(array_map(fn($h) => $this->normalizeHeaderKey((string) $h), array_filter($headers, fn($h) => '' !== trim((string) $h))));

                    // Allow exactly 2 occurrences of "name" (customer name + contact lastname)
                    if (isset($occurrences['name']) && $occurrences['name'] <= 2) {
                        // Skip this duplicate - it's handled by normalization
                        continue;
                    }
                }

                // Found a duplicate that's not allowed
                $duplicates[] = [
                    'header' => $header,
                    'normalized' => $normalized,
                    'positions' => [$normalizedHeaders[$normalized], $position],
                ];
            } else {
                $normalizedHeaders[$normalized] = $position;
            }
        }

        if (!empty($duplicates)) {
            $errorMessage = "Le fichier contient des colonnes en double :\n";
            foreach ($duplicates as $duplicate) {
                $positions = implode(', ', array_map(fn ($p) => chr(65 + $p), $duplicate['positions']));
                $errorMessage .= sprintf(
                    "- Colonne '%s' (colonnes %s) : Les noms de colonnes doivent être uniques.\n",
                    $duplicate['header'],
                    $positions
                );
            }
            $errorMessage .= "\nVeuillez corriger le fichier Excel et réessayer.";

            $this->logger->error('Duplicate column names detected', [
                'duplicates' => $duplicates,
            ]);

            throw new \RuntimeException($errorMessage);
        }
    }

    /**
     * Analyze a single row from the Excel file.
     *
     * @param Import               $import    The import entity
     * @param array<string, mixed> $row       Row data from Excel
     * @param int                  $rowNumber Row number in the file (1-indexed)
     */
    private function analyzeRow(Import $import, array $row, int $rowNumber): void
    {
        // Reset last found energy for this row
        $this->lastFoundEnergy = null;

        try {
            // Normalize and validate row data
            $rowData = $this->normalizeRowData($row);

            // Skip completely empty rows (all values are null or empty strings)
            if ($this->isEmptyRow($rowData)) {
                $this->logger->debug('Skipping empty row', ['row_number' => $rowNumber]);

                return;
            }

            // Validate required fields
            $validationError = $this->validateRow($rowData);
            if (null !== $validationError) {
                $this->recordError($import, $rowNumber, $rowData, $validationError, 'name');
                ++$this->errorCount;

                return;
            }

            // Determine customer operation and get existing customer if any
            $existingCustomer = $this->findExistingCustomer($rowData);
            $hasCustomerChanges = false;

            // For existing customers, check if there are actual changes
            if ($existingCustomer) {
                try {
                    $hasCustomerChanges = $this->captureCustomerChanges($existingCustomer, $rowData);
                } catch (\Throwable $e) {
                    // Log but don't fail the analysis if detail capture fails
                    $this->logger->warning('Failed to capture customer changes', [
                        'error' => $e->getMessage(),
                        'customer_id' => $existingCustomer->getId(),
                    ]);
                }
            }

            // Determine operation type based on existence and changes
            $customerOperationType = !$existingCustomer
                ? ImportOperationType::CREATE
                : ($hasCustomerChanges ? ImportOperationType::UPDATE : ImportOperationType::SKIP);

            // Track customer operation
            $entityType = Customer::class;
            match ($customerOperationType) {
                ImportOperationType::CREATE => $this->creations[$entityType] = ($this->creations[$entityType] ?? 0) + 1,
                ImportOperationType::UPDATE => $this->updates[$entityType] = ($this->updates[$entityType] ?? 0) + 1,
                ImportOperationType::SKIP => $this->skips[$entityType] = ($this->skips[$entityType] ?? 0) + 1,
            };

            // Analyze Contact if contact data is present
            if ($this->hasContactData($rowData)) {
                $existingContact = null;
                $hasContactChanges = false;

                // If customer doesn't exist yet, contact will be created
                if (!$existingCustomer) {
                    $contactOperationType = ImportOperationType::CREATE;
                } else {
                    // Find existing contact
                    $existingContact = $this->findExistingContact($rowData, $existingCustomer);

                    if (!$existingContact) {
                        $contactOperationType = ImportOperationType::CREATE;
                    } else {
                        // Check if there are actual changes
                        try {
                            $hasContactChanges = $this->captureContactChanges($existingContact, $rowData);
                        } catch (\Throwable $e) {
                            $this->logger->warning('Failed to capture contact changes', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                        $contactOperationType = $hasContactChanges ? ImportOperationType::UPDATE : ImportOperationType::SKIP;
                    }
                }

                $contactEntityType = Contact::class;
                match ($contactOperationType) {
                    ImportOperationType::CREATE => $this->creations[$contactEntityType] = ($this->creations[$contactEntityType] ?? 0) + 1,
                    ImportOperationType::UPDATE => $this->updates[$contactEntityType] = ($this->updates[$contactEntityType] ?? 0) + 1,
                    ImportOperationType::SKIP => $this->skips[$contactEntityType] = ($this->skips[$contactEntityType] ?? 0) + 1,
                };
            }

            // Analyze Energy if energy data is present and it's a FULL import
            if (ImportType::FULL === $import->getType() && $this->hasEnergyData($rowData)) {
                // Determine energy operation
                $this->determineEnergyOperationType($rowData, $existingCustomer); // Sets $this->lastFoundEnergy
                $hasEnergyChanges = false;

                // Determine operation type based on existence and changes
                if (!$this->lastFoundEnergy) {
                    $energyOperationType = ImportOperationType::CREATE;
                } else {
                    // Check if there are actual changes
                    $this->logger->info('Attempting to capture energy changes', [
                        'energy_id' => $this->lastFoundEnergy->getId(),
                        'energy_code' => $this->lastFoundEnergy->getCode(),
                        'row_number' => $rowNumber,
                    ]);
                    try {
                        $hasEnergyChanges = $this->captureEnergyChanges($this->lastFoundEnergy, $rowData);
                    } catch (\Throwable $e) {
                        $this->logger->warning('Failed to capture energy changes', [
                            'error' => $e->getMessage(),
                            'energy_id' => $this->lastFoundEnergy->getId(),
                        ]);
                    }
                    $energyOperationType = $hasEnergyChanges ? ImportOperationType::UPDATE : ImportOperationType::SKIP;
                }

                $energyEntityType = Energy::class;
                match ($energyOperationType) {
                    ImportOperationType::CREATE => $this->creations[$energyEntityType] = ($this->creations[$energyEntityType] ?? 0) + 1,
                    ImportOperationType::UPDATE => $this->updates[$energyEntityType] = ($this->updates[$energyEntityType] ?? 0) + 1,
                    ImportOperationType::SKIP => $this->skips[$energyEntityType] = ($this->skips[$energyEntityType] ?? 0) + 1,
                };
            }
        } catch (\Exception $e) {
            $this->logger->error('Error analyzing row', [
                'row_number' => $rowNumber,
                'exception' => $e->getMessage(),
            ]);

            $this->recordError(
                $import,
                $rowNumber,
                $row,
                sprintf('Erreur lors de l\'analyse de la ligne: %s', $e->getMessage())
            );
            ++$this->errorCount;
        }
    }

    /**
     * Normalize row data from Excel columns to application field names.
     *
     * Handles duplicate column names by mapping based on order of appearance:
     * - First "Nom" column → 'name' (raison sociale du client)
     * - Second "Nom" column → 'contact_lastname' (nom de famille du contact)
     *
     * @param array<string, mixed> $row Raw row data from Excel
     *
     * @return array<string, mixed> Normalized row data
     */
    private function normalizeRowData(array $row): array
    {
        $normalized = [];
        $keyOccurrences = []; // Track occurrences of each normalized key

        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeHeaderKey((string) $key);

            // Track how many times we've seen this normalized key
            if (!isset($keyOccurrences[$normalizedKey])) {
                $keyOccurrences[$normalizedKey] = 0;
            }
            $keyOccurrences[$normalizedKey]++;

            // Handle duplicate "nom" columns: first occurrence is customer name, second is contact lastname
            if ('name' === $normalizedKey && $keyOccurrences[$normalizedKey] > 1) {
                $normalizedKey = 'contact_lastname';
            }

            $normalized[$normalizedKey] = $value;
        }

        // Handle "firstname" and "lastname" columns for contact
        if (isset($normalized['firstname'])) {
            $normalized['contact_firstname'] = $normalized['firstname'];
            unset($normalized['firstname']);
        }
        if (isset($normalized['lastname'])) {
            $normalized['contact_lastname'] = $normalized['lastname'];
            unset($normalized['lastname']);
        }

        // Handle Excel date conversion for contract_end
        if (isset($normalized['contract_end']) && is_numeric($normalized['contract_end'])) {
            $normalized['contract_end'] = $this->convertExcelDate($normalized['contract_end']);
        }

        return $normalized;
    }

    /**
     * Normalize column header to application field name.
     *
     * This mapping should match the logic in ProcessExcelBatchMessageHandler
     * to ensure consistency between analysis and execution.
     */
    private function normalizeHeaderKey(string $headerName): string
    {
        // Convert to lowercase, replace spaces with underscores
        $key = strtolower(trim($headerName));
        // Convert accented characters to ASCII before removing special chars
        $key = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key) ?: $key;
        $key = preg_replace('/\s+/', '_', $key) ?? $key;
        $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? $key;

        // Map common column names
        $mappings = [
            'siret' => 'siret',
            'raison_sociale' => 'name',
            'nom' => 'lastname',
            'nom_dtablissement' => 'name',
            'nom_d_etablissement' => 'name',
            'prenom' => 'firstname',
            'prnom' => 'firstname',
            'contact_name' => 'contact',
            'contact' => 'contact',
            'adresse_mail' => 'email',
            'mail' => 'email',
            'email' => 'email',
            'telephone' => 'phone',
            'numro_tel' => 'phone',
            'numero' => 'phone',
            'fournisseur_actuel' => 'provider',
            'fournisseur' => 'provider',
            'echeance' => 'contract_end',
            'contract_end' => 'contract_end',
            'date_chance_elec' => 'contract_end',
            'pdl' => 'pce_pdl',
            'pce' => 'pce_pdl',
            'pce_pdl' => 'pce_pdl',
            'pdl_pce' => 'pce_pdl',
            'pdl__pce' => 'pce_pdl',
            'origine_lead' => 'lead_origin',
            'origine_du_lead' => 'lead_origin',
            'origine' => 'lead_origin',
            'commentaire' => 'comment',
            'commentaires' => 'comment',
            'comment' => 'comment',
            'elec__gaz' => 'energy_type',
            'type_energie' => 'energy_type',
        ];

        return $mappings[$key] ?? $key;
    }

    /**
     * Convert Excel date number to DateTime.
     *
     * Excel stores dates as the number of days since December 30, 1899.
     *
     * @param int|float|string $excelDate Excel date number
     */
    private function convertExcelDate(mixed $excelDate): ?\DateTime
    {
        if (!is_numeric($excelDate)) {
            return null;
        }

        try {
            // Excel date = number of days since December 30, 1899
            $unixTimestamp = (int) round(((float) $excelDate - 25569) * 86400);
            $date = new \DateTime();
            $date->setTimestamp($unixTimestamp);

            return $date;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to convert Excel date', [
                'excel_date' => $excelDate,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate a row of data.
     *
     * @param array<string, mixed> $rowData Normalized row data
     *
     * @return string|null Error message if validation fails, null if valid
     */
    private function validateRow(array $rowData): ?string
    {
        // Name is required minimum
        if (empty($rowData['name'])) {
            return 'Le nom du client est obligatoire';
        }

        // Validate name length
        if (!is_string($rowData['name'])) {
            return 'Le nom du client doit être une chaîne de caractères';
        }

        $name = trim($rowData['name']);
        if (strlen($name) > 255) {
            return sprintf('Le nom du client est trop long (maximum 255 caractères, %d fournis)', strlen($name));
        }

        return null;
    }

    /**
     * Determine the operation type for a row.
     *
     * Checks if the customer exists by SIRET or name and determines
     * whether this would be a CREATE or UPDATE operation.
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function determineOperationType(array $rowData): ImportOperationType
    {
        $existingCustomer = $this->findExistingCustomer($rowData);

        // Determine operation type
        return $existingCustomer ? ImportOperationType::UPDATE : ImportOperationType::CREATE;
    }

    /**
     * Find existing customer by SIRET or name.
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function findExistingCustomer(array $rowData): ?Customer
    {
        if (!is_string($rowData['name'])) {
            return null;
        }

        $name = trim($rowData['name']);
        $siret = '';

        // Handle SIRET from Excel (can be integer or string)
        if (isset($rowData['siret'])) {
            if (is_string($rowData['siret'])) {
                $siret = str_replace(' ', '', $rowData['siret']);
            } elseif (is_numeric($rowData['siret'])) {
                $siret = (string) $rowData['siret'];
            }
        }

        // Try to find existing customer by SIRET first
        $existingCustomer = null;
        if (!empty($siret)) {
            $existingCustomer = $this->customerRepository->findOneBy(['siret' => $siret]);
        }

        // If not found by SIRET, try by name
        if (!$existingCustomer) {
            $existingCustomer = $this->customerRepository->findOneBy(['name' => $name]);
        }

        return $existingCustomer;
    }

    /**
     * Check if row has contact data.
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function hasContactData(array $rowData): bool
    {
        // Contact data is present if we have at least the contact name/field
        // The ProcessExcelBatchMessageHandler checks for 'contact' field
        return !empty($rowData['contact'])
            || !empty($rowData['contact_firstname'])
            || !empty($rowData['contact_lastname'])
            || !empty($rowData['email'])
            || !empty($rowData['phone']);
    }

    /**
     * Check if row has energy data.
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function hasEnergyData(array $rowData): bool
    {
        // Energy data is present if we have PCE/PDL, provider, or energy type
        return isset($rowData['pce_pdl'])
            || !empty($rowData['provider'])
            || !empty($rowData['energy_type']);
    }

    /**
     * Determine the operation type for a contact.
     *
     * @param array<string, mixed> $rowData         Normalized row data
     * @param Customer|null        $existingCustomer Existing customer if found
     */
    private function determineContactOperationType(array $rowData, ?Customer $existingCustomer): ImportOperationType
    {
        // If customer doesn't exist yet, contact will be created
        if (!$existingCustomer) {
            return ImportOperationType::CREATE;
        }

        // Extract contact information - handle both 'contact' field and separate firstname/lastname
        $contactName = '';
        if (!empty($rowData['contact']) && is_string($rowData['contact'])) {
            $contactName = trim($rowData['contact']);
        } elseif (!empty($rowData['contact_firstname']) || !empty($rowData['contact_lastname'])) {
            $firstName = isset($rowData['contact_firstname']) && is_string($rowData['contact_firstname']) ? trim($rowData['contact_firstname']) : '';
            $lastName = isset($rowData['contact_lastname']) && is_string($rowData['contact_lastname']) ? trim($rowData['contact_lastname']) : '';
            $contactName = trim($firstName.' '.$lastName);
        }

        $email = isset($rowData['email']) && is_string($rowData['email']) ? $rowData['email'] : null;
        $phone = isset($rowData['phone']) && is_string($rowData['phone']) ? $rowData['phone'] : null;

        // Use the same logic as ProcessExcelBatchMessageHandler
        // which uses ContactRepository::findContactByCustomerAndEmailOrNumber
        if (empty($contactName) && empty($email) && empty($phone)) {
            return ImportOperationType::SKIP;
        }

        $existingContact = $this->contactRepository->findContactByCustomerAndEmailOrNumber(
            $existingCustomer,
            $contactName,
            $email,
            $phone
        );

        return $existingContact ? ImportOperationType::UPDATE : ImportOperationType::CREATE;
    }

    /**
     * Determine the operation type for an energy.
     *
     * @param array<string, mixed> $rowData         Normalized row data
     * @param Customer|null        $existingCustomer Existing customer if found
     */
    private function determineEnergyOperationType(array $rowData, ?Customer $existingCustomer): ImportOperationType
    {
        // If customer doesn't exist yet, energy will be created
        if (!$existingCustomer) {
            return ImportOperationType::CREATE;
        }

        // Extract energy information
        $pceValue = $rowData['pce_pdl'] ?? null;
        $pceCode = null;

        if (is_numeric($pceValue) && $pceValue > 0) {
            $pceCode = (int) $pceValue;
        }

        // Determine energy type (default to ELECTRICITY)
        $energyType = EnergyType::ELEC;
        if (!empty($rowData['energy_type']) && is_string($rowData['energy_type'])) {
            $energyType = $this->parseEnergyType($rowData['energy_type']);
        }

        // Use the same matching logic as ProcessExcelBatchMessageHandler::processEnergy
        $existingEnergy = null;

        // 1. If we have a code PDL/PCE, search by code + type (unique)
        if ($pceCode) {
            $existingEnergy = $this->energyRepository->findOneBy([
                'code' => (string) $pceCode,
                'type' => $energyType,
            ]);
        }

        // 2. If not found and we have a provider, search by customer + provider + type
        if (!$existingEnergy && !empty($rowData['provider'])) {
            // We can't easily check by provider name without loading the provider entity
            // For now, check if customer has any energy of this type
            // This matches the logic in ProcessExcelBatchMessageHandler
            $energiesOfType = $this->energyRepository->findBy([
                'customer' => $existingCustomer,
                'type' => $energyType,
            ]);

            // If exactly one energy of this type exists, it will be updated
            if (1 === count($energiesOfType)) {
                $existingEnergy = $energiesOfType[0];
            }
        }

        // 3. If still not found, check if customer has only one energy of this type
        if (!$existingEnergy) {
            $energiesOfType = $this->energyRepository->findBy([
                'customer' => $existingCustomer,
                'type' => $energyType,
            ]);

            // If exactly one energy of this type exists, it will be updated
            if (1 === count($energiesOfType)) {
                $existingEnergy = $energiesOfType[0];
            }
        }

        // Store the found energy for later change tracking
        $this->lastFoundEnergy = $existingEnergy;

        return $existingEnergy ? ImportOperationType::UPDATE : ImportOperationType::CREATE;
    }

    /**
     * Parse energy type from string.
     */
    private function parseEnergyType(string $typeStr): EnergyType
    {
        $typeStr = strtoupper(trim($typeStr));

        return match ($typeStr) {
            'GAZ', 'GAS', 'G' => EnergyType::GAZ,
            default => EnergyType::ELEC,
        };
    }

    /**
     * Check if a row is completely empty (all values are null or empty strings).
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            // If any value is not null and not empty string, row is not empty
            if (null !== $value && '' !== $value) {
                return false;
            }
        }

        // All values are null or empty
        return true;
    }

    /**
     * Record an error for a row.
     *
     * @param Import               $import    The import entity
     * @param int                  $rowNumber Row number in the file
     * @param array<string, mixed> $rowData   Row data
     * @param string               $message   Error message
     * @param string|null          $fieldName Field that caused the error (optional)
     * @param ImportErrorSeverity  $severity  Error severity (default: ERROR)
     */
    private function recordError(
        Import $import,
        int $rowNumber,
        array $rowData,
        string $message,
        ?string $fieldName = null,
        ImportErrorSeverity $severity = ImportErrorSeverity::ERROR,
    ): void {
        $error = new ImportError();
        $error->setImport($import);
        $error->setRowNumber($rowNumber);
        $error->setMessage($message);
        $error->setFieldName($fieldName);
        $error->setSeverity($severity);
        $error->setRowData($rowData);

        $import->addError($error);
        $this->entityManager->persist($error);
    }

    /**
     * Create ImportAnalysisResult entities for tracked operations.
     */
    private function createAnalysisResults(Import $import): void
    {
        // Create results for creations
        foreach ($this->creations as $entityType => $count) {
            if ($count > 0) {
                $result = new ImportAnalysisResult();
                $result->setImport($import);
                $result->setOperationType(ImportOperationType::CREATE);
                $result->setEntityType($entityType);
                $result->setCount($count);

                $import->addAnalysisResult($result);
                $this->entityManager->persist($result);
            }
        }

        // Create results for updates
        foreach ($this->updates as $entityType => $count) {
            if ($count > 0) {
                $result = new ImportAnalysisResult();
                $result->setImport($import);
                $result->setOperationType(ImportOperationType::UPDATE);
                $result->setEntityType($entityType);
                $result->setCount($count);

                // Add details if available
                if (isset($this->updateDetails[$entityType]) && !empty($this->updateDetails[$entityType])) {
                    $result->setDetails(['changes' => $this->updateDetails[$entityType]]);
                }

                $import->addAnalysisResult($result);
                $this->entityManager->persist($result);
            }
        }

        // Create results for skips
        foreach ($this->skips as $entityType => $count) {
            if ($count > 0) {
                $result = new ImportAnalysisResult();
                $result->setImport($import);
                $result->setOperationType(ImportOperationType::SKIP);
                $result->setEntityType($entityType);
                $result->setCount($count);

                $import->addAnalysisResult($result);
                $this->entityManager->persist($result);
            }
        }
    }

    /**
     * Capture field-level changes for a customer update.
     *
     * @param Customer             $existingCustomer The existing customer entity
     * @param array<string, mixed> $rowData          Normalized row data from import
     *
     * @return bool True if changes were detected, false otherwise
     */
    private function captureCustomerChanges(Customer $existingCustomer, array $rowData): bool
    {
        $changes = [];

        // Extract new values from row data
        $newName = isset($rowData['name']) && is_string($rowData['name']) ? trim($rowData['name']) : null;
        $newSiret = '';
        if (isset($rowData['siret'])) {
            if (is_string($rowData['siret'])) {
                $newSiret = str_replace(' ', '', $rowData['siret']);
            } elseif (is_numeric($rowData['siret'])) {
                $newSiret = (string) $rowData['siret'];
            }
        }
        $newLeadOrigin = isset($rowData['lead_origin']) && is_string($rowData['lead_origin']) ? trim($rowData['lead_origin']) : null;

        // Compare and track changes
        // Name - always updated (case-insensitive comparison)
        if ($newName && 0 !== strcasecmp($newName, $existingCustomer->getName() ?? '')) {
            $changes['name'] = [
                'old' => $existingCustomer->getName(),
                'new' => $newName,
            ];
        }

        // SIRET - only updated if currently empty
        if (!empty($newSiret) && empty($existingCustomer->getSiret())) {
            $changes['siret'] = [
                'old' => $existingCustomer->getSiret() ?? null,
                'new' => $newSiret,
            ];
        }

        // Lead origin - only updated if currently empty
        if (!empty($newLeadOrigin) && empty($existingCustomer->getLeadOrigin())) {
            $changes['lead_origin'] = [
                'old' => $existingCustomer->getLeadOrigin(),
                'new' => $newLeadOrigin,
            ];
        }

        // Only record if there are changes
        if (!empty($changes)) {
            $entityType = Customer::class;
            if (!isset($this->updateDetails[$entityType])) {
                $this->updateDetails[$entityType] = [];
            }

            $this->updateDetails[$entityType][] = [
                'entity_id' => $existingCustomer->getId(),
                'entity_label' => $existingCustomer->getName(),
                'fields' => $changes,
            ];
        }

        return !empty($changes);
    }

    /**
     * Find existing contact for change tracking.
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function findExistingContact(array $rowData, Customer $customer): ?Contact
    {
        $contactName = '';
        if (!empty($rowData['contact']) && is_string($rowData['contact'])) {
            $contactName = trim($rowData['contact']);
        } elseif (!empty($rowData['contact_firstname']) || !empty($rowData['contact_lastname'])) {
            $firstName = isset($rowData['contact_firstname']) && is_string($rowData['contact_firstname']) ? trim($rowData['contact_firstname']) : '';
            $lastName = isset($rowData['contact_lastname']) && is_string($rowData['contact_lastname']) ? trim($rowData['contact_lastname']) : '';
            $contactName = trim($firstName.' '.$lastName);
        }

        $email = isset($rowData['email']) && is_string($rowData['email']) ? $rowData['email'] : null;
        $phone = isset($rowData['phone']) && is_string($rowData['phone']) ? $rowData['phone'] : null;

        if (empty($contactName) && empty($email) && empty($phone)) {
            return null;
        }

        return $this->contactRepository->findContactByCustomerAndEmailOrNumber(
            $customer,
            $contactName,
            $email,
            $phone
        );
    }

    /**
     * Capture field-level changes for a contact update.
     *
     * @return bool True if changes were detected, false otherwise
     */
    private function captureContactChanges(Contact $existingContact, array $rowData): bool
    {
        $changes = [];

        // Extract new values
        $newFirstName = isset($rowData['contact_firstname']) && is_string($rowData['contact_firstname']) ? trim($rowData['contact_firstname']) : null;
        $newLastName = isset($rowData['contact_lastname']) && is_string($rowData['contact_lastname']) ? trim($rowData['contact_lastname']) : null;
        $newEmail = isset($rowData['email']) && is_string($rowData['email']) ? trim($rowData['email']) : null;
        $newPhone = isset($rowData['phone']) && is_string($rowData['phone']) ? trim($rowData['phone']) : null;

        // Track changes (case-insensitive comparison)
        if ($newFirstName && 0 !== strcasecmp($newFirstName, $existingContact->getFirstName() ?? '')) {
            $changes['firstName'] = ['old' => $existingContact->getFirstName(), 'new' => $newFirstName];
        }
        if ($newLastName && 0 !== strcasecmp($newLastName, $existingContact->getLastName() ?? '')) {
            $changes['lastName'] = ['old' => $existingContact->getLastName(), 'new' => $newLastName];
        }
        if ($newEmail && 0 !== strcasecmp($newEmail, $existingContact->getEmail() ?? '')) {
            $changes['email'] = ['old' => $existingContact->getEmail(), 'new' => $newEmail];
        }
        // Phone comparison - check against both phone and mobile phone fields
        $existingPhone = $existingContact->getPhone() ?? $existingContact->getMobilePhone() ?? '';
        if ($newPhone && 0 !== strcasecmp($newPhone, $existingPhone)) {
            $changes['phone'] = ['old' => $existingContact->getPhone() ?? $existingContact->getMobilePhone(), 'new' => $newPhone];
        }

        if (!empty($changes)) {
            $entityType = Contact::class;
            if (!isset($this->updateDetails[$entityType])) {
                $this->updateDetails[$entityType] = [];
            }

            $this->updateDetails[$entityType][] = [
                'entity_id' => $existingContact->getId(),
                'entity_label' => $existingContact->getFirstName().' '.$existingContact->getLastName(),
                'fields' => $changes,
            ];
        }

        return !empty($changes);
    }

    /**
     * Find existing energy for change tracking.
     *
     * @param array<string, mixed> $rowData Normalized row data
     */
    private function findExistingEnergy(array $rowData, Customer $customer): ?Energy
    {
        $pceValue = $rowData['pce_pdl'] ?? null;
        $pceCode = null;

        if (is_numeric($pceValue) && $pceValue > 0) {
            $pceCode = (int) $pceValue;
        }

        $energyType = EnergyType::ELEC;
        if (!empty($rowData['energy_type']) && is_string($rowData['energy_type'])) {
            $energyType = $this->parseEnergyType($rowData['energy_type']);
        }

        // Try to find by code
        if ($pceCode) {
            $energy = $this->energyRepository->findOneBy([
                'code' => (string) $pceCode,
                'type' => $energyType,
            ]);
            if ($energy) {
                return $energy;
            }
        }

        // Try by customer + type
        $energiesOfType = $this->energyRepository->findBy([
            'customer' => $customer,
            'type' => $energyType,
        ]);

        return 1 === count($energiesOfType) ? $energiesOfType[0] : null;
    }

    /**
     * Capture field-level changes for an energy update.
     *
     * @return bool True if changes were detected, false otherwise
     */
    private function captureEnergyChanges(Energy $existingEnergy, array $rowData): bool
    {
        $changes = [];

        // Extract new values
        $pceValue = $rowData['pce_pdl'] ?? null;
        $newCode = null;
        if (is_numeric($pceValue) && $pceValue > 0) {
            $newCode = (string) ((int) $pceValue);
        }

        $newProvider = isset($rowData['provider']) && is_string($rowData['provider']) ? trim($rowData['provider']) : null;
        $newContractEnd = $rowData['contract_end'] ?? null;
        if ($newContractEnd instanceof \DateTime) {
            $newContractEnd = $newContractEnd->format('Y-m-d');
        }

        $this->logger->info('Capturing energy changes', [
            'energy_id' => $existingEnergy->getId(),
            'old_code' => $existingEnergy->getCode(),
            'new_code' => $newCode,
            'old_provider' => $existingEnergy->getEnergyProvider()?->getName(),
            'new_provider' => $newProvider,
            'old_contract_end' => $existingEnergy->getContractEnd()?->format('Y-m-d'),
            'new_contract_end' => $newContractEnd,
        ]);

        // Track changes (case-insensitive comparison for provider)
        if ($newCode && $newCode !== $existingEnergy->getCode()) {
            $changes['code'] = ['old' => $existingEnergy->getCode(), 'new' => $newCode];
        }
        if ($newProvider && (!$existingEnergy->getEnergyProvider() || 0 !== strcasecmp($newProvider, $existingEnergy->getEnergyProvider()->getName()))) {
            $changes['provider'] = [
                'old' => $existingEnergy->getEnergyProvider()?->getName(),
                'new' => $newProvider,
            ];
        }
        if ($newContractEnd) {
            $oldContractEnd = $existingEnergy->getContractEnd()?->format('Y-m-d');
            if ($newContractEnd !== $oldContractEnd) {
                $changes['contractEnd'] = ['old' => $oldContractEnd, 'new' => $newContractEnd];
            }
        }

        $this->logger->info('Changes detected for energy', [
            'energy_id' => $existingEnergy->getId(),
            'changes_count' => count($changes),
            'changes' => $changes,
        ]);

        if (!empty($changes)) {
            $entityType = Energy::class;
            if (!isset($this->updateDetails[$entityType])) {
                $this->updateDetails[$entityType] = [];
            }

            $label = $existingEnergy->getType()->value.' - '.($existingEnergy->getCode() ?? 'Sans code');
            $this->updateDetails[$entityType][] = [
                'entity_id' => $existingEnergy->getId(),
                'entity_label' => $label,
                'fields' => $changes,
            ];
        }

        return !empty($changes);
    }
}
