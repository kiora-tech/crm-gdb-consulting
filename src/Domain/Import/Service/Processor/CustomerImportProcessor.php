<?php

declare(strict_types=1);

namespace App\Domain\Import\Service\Processor;

use App\Domain\Import\Contract\ImportProcessorInterface;
use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyProvider;
use App\Entity\EnergyType;
use App\Entity\Import;
use App\Entity\ImportError;
use App\Entity\ImportErrorSeverity;
use App\Entity\ImportType;
use App\Entity\ProspectOrigin;
use App\Entity\User;
use App\Repository\ContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Processor for customer and full imports.
 *
 * Handles creation and update of Customer entities with their related entities
 * (Contacts, Energies, Comments) based on imported Excel data.
 */
readonly class CustomerImportProcessor implements ImportProcessorInterface
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private EnergyRepository $energyRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function supports(ImportType $type): bool
    {
        return ImportType::CUSTOMER === $type || ImportType::FULL === $type;
    }

    public function processBatch(array $rows, Import $import): void
    {
        $userId = $import->getUser()->getId();

        if (null === $userId) {
            throw new \RuntimeException('L\'utilisateur de l\'import n\'a pas d\'ID');
        }

        foreach ($rows as $rowIndex => $rowData) {
            try {
                // Normalize the row data using the header key mapping
                $normalizedData = $this->normalizeRowData($rowData);

                // Skip rows without a name
                if (empty($normalizedData['name'])) {
                    $this->logger->warning('Ligne ignorée car le nom est manquant', [
                        'import_id' => $import->getId(),
                        'row_index' => $rowIndex,
                    ]);
                    $import->incrementProcessedRows();
                    $import->incrementErrorRows();
                    $this->addImportError($import, $rowIndex, 'Le nom du client est obligatoire');
                    continue;
                }

                // Process the row within a transaction
                $this->processRow($import, $rowIndex, $normalizedData, $userId);

                $import->incrementProcessedRows();
                $import->incrementSuccessRows();

                // Flush after each successful row to avoid losing work
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors du traitement de la ligne', [
                    'import_id' => $import->getId(),
                    'row_index' => $rowIndex,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                $import->incrementProcessedRows();
                $import->incrementErrorRows();
                $this->addImportError($import, $rowIndex, $e->getMessage());

                // Rollback if transaction is active
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->getConnection()->rollBack();
                }

                // Clear and continue with next row
                $this->entityManager->clear();

                // Flush the error to database
                $this->entityManager->persist($import);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * Normalize row data using the same mapping logic as CustomerImportAnalyzer.
     *
     * Handles duplicate column names by mapping based on order of appearance:
     * - First "Nom" column → 'name' (raison sociale du client)
     * - Second "Nom" column → 'contact_lastname' (nom de famille du contact)
     *
     * @param array<string, mixed> $rowData
     *
     * @return array<string, mixed>
     */
    private function normalizeRowData(array $rowData): array
    {
        $normalized = [];
        $keyOccurrences = []; // Track occurrences of each normalized key

        foreach ($rowData as $key => $value) {
            if (empty($key)) {
                continue;
            }

            $normalizedKey = $this->normalizeHeaderKey((string) $key);

            // Track how many times we've seen this normalized key
            if (!isset($keyOccurrences[$normalizedKey])) {
                $keyOccurrences[$normalizedKey] = 0;
            }
            ++$keyOccurrences[$normalizedKey];

            // Handle duplicate "nom" columns: first occurrence is customer name, second is contact lastname
            if ('name' === $normalizedKey && $keyOccurrences[$normalizedKey] > 1) {
                $normalizedKey = 'contact_lastname';
            }

            // Handle Excel date conversion for contract_end
            if ('contract_end' === $normalizedKey && is_numeric($value)) {
                $value = $this->convertExcelDate($value);
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

        return $normalized;
    }

    /**
     * Normalize header key using the same mapping as ProcessExcelBatchMessageHandler.
     */
    private function normalizeHeaderKey(string $headerName): string
    {
        // Convert to lowercase, replace spaces with underscores
        $key = strtolower(trim($headerName));
        // Convert accented characters to ASCII before removing special chars
        $key = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key) ?: $key;
        $key = (string) preg_replace('/\s+/', '_', $key);
        $key = (string) preg_replace('/[^a-z0-9_]/', '', $key);

        // Map column names to standardized keys
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
            'cheance' => 'contract_end', // Fix for accented "Échéance" which becomes "cheance"
            'contract_end' => 'contract_end',
            'date_chance_elec' => 'contract_end',
            'pdl' => 'pce_pdl',
            'pce' => 'pce_pdl',
            'pce_pdl' => 'pce_pdl',
            'pdl_pce' => 'pce_pdl',
            'pdlpce' => 'pce_pdl', // Fix for "PDL/PCE" which becomes "pdlpce" (slash removed)
            'pdl__pce' => 'pce_pdl',
            'origine_lead' => 'lead_origin',
            'origine_du_lead' => 'lead_origin',
            'origine' => 'lead_origin',
            'commentaire' => 'comment',
            'commentaires' => 'comment',
            'comment' => 'comment',
            'elec__gaz' => 'energy_type',
            'type_energie' => 'energy_type',
            'commercial' => 'commercial_email', // For "Commercial" column
        ];

        return $mappings[$key] ?? $key;
    }

    /**
     * Convert Excel date number to DateTime object.
     *
     * @param int|float|string|null $excelDate
     */
    private function convertExcelDate($excelDate): ?\DateTime
    {
        if (!is_numeric($excelDate)) {
            return null;
        }

        try {
            // Excel date = number of days since December 30, 1899
            $unixTimestamp = (int) round(($excelDate - 25569) * 86400);
            $date = new \DateTime();
            $date->setTimestamp($unixTimestamp);

            return $date;
        } catch (\Exception $e) {
            $this->logger->warning('Impossible de convertir la date Excel', [
                'value' => $excelDate,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process a single row of data.
     *
     * @param array<string, mixed> $rowData
     */
    private function processRow(Import $import, int $rowIndex, array $rowData, int $userId): void
    {
        // Note: Transaction is managed by the Handler (ProcessImportBatchMessageHandler)
        // No need for nested transactions here

        // Get or create customer
        $name = $rowData['name'] ?? '';
        $siret = $rowData['siret'] ?? '';
        $leadOrigin = $rowData['lead_origin'] ?? '';
        $commercialEmail = $rowData['commercial_email'] ?? null;

        $customer = $this->getOrCreateCustomer(
            is_string($name) ? $name : '',
            is_string($siret) || is_numeric($siret) ? (string) $siret : '',
            is_string($leadOrigin) ? $leadOrigin : '',
            $userId,
            is_string($commercialEmail) ? $commercialEmail : null
        );

        $this->logger->info('Client créé/récupéré', [
            'import_id' => $import->getId(),
            'row_index' => $rowIndex,
            'customer_id' => $customer->getId(),
            'customer_name' => $customer->getName(),
            'customer_siret' => $customer->getSiret(),
        ]);

        // Process contact if present (either full name or firstname/lastname)
        $hasContactInfo = (!empty($rowData['contact']) && is_string($rowData['contact']))
            || !empty($rowData['contact_firstname'])
            || !empty($rowData['contact_lastname'])
            || !empty($rowData['email'])
            || !empty($rowData['phone']);

        if ($hasContactInfo) {
            $this->processContact($customer, $rowData);
        }

        // Process comment if present
        if (!empty($rowData['comment']) && is_string($rowData['comment'])) {
            $this->processComment($customer, $rowData['comment']);
        }

        // Process energy if this is a full import and energy data is present
        if (ImportType::FULL === $import->getType()) {
            if (isset($rowData['pce_pdl']) || !empty($rowData['provider']) || !empty($rowData['energy_type'])) {
                $this->processEnergy($customer, $rowData);
            }
        }
    }

    private function getOrCreateCustomer(string $name, string $siret, string $leadOrigin, int $userId, ?string $commercialEmail = null): Customer
    {
        // Clean the name
        $name = trim($name);

        // Limit size if necessary
        if (strlen($name) > 255) {
            $name = substr($name, 0, 252).'...';
        }

        $siret = str_replace(' ', '', $siret);

        // Try to find by SIRET first
        $customer = null;
        if (!empty($siret)) {
            $customer = $this->customerRepository->findOneBy(['siret' => $siret]);
        }

        // If not found by SIRET, try by name
        if (!$customer) {
            $customer = $this->customerRepository->findOneBy(['name' => $name]);
        }

        if (!$customer) {
            // Create new customer
            $customer = new Customer();
            $customer->setName($name);
            if (!empty($siret)) {
                $customer->setSiret($siret);
            }
            $customer->setLeadOrigin($leadOrigin ?: 'Import Excel');
            $customer->setOrigin(ProspectOrigin::ACQUISITION);
            $this->entityManager->persist($customer);
        } else {
            // Update existing customer
            // Always update the name to reflect the latest data from import
            $customer->setName($name);

            // Update SIRET only if not already set
            if (!empty($siret) && empty($customer->getSiret())) {
                $customer->setSiret($siret);
            }

            // Update lead origin if provided and not already set
            if (!empty($leadOrigin) && empty($customer->getLeadOrigin())) {
                $customer->setLeadOrigin($leadOrigin);
            }
        }

        // Set user reference
        // If a commercial email is provided, try to find the user by email
        // If no commercial email is provided or user not found, leave customer unassigned (null)
        if (!empty($commercialEmail)) {
            $commercialUser = $this->userRepository->findOneBy(['email' => $commercialEmail]);
            if ($commercialUser && $commercialUser->getId()) {
                $customer->setUser($this->entityManager->getReference(User::class, $commercialUser->getId()));
                $this->logger->debug('Commercial assigné depuis l\'email', [
                    'customer' => $name,
                    'commercial_email' => $commercialEmail,
                    'commercial_id' => $commercialUser->getId(),
                ]);
            } else {
                $this->logger->warning('Commercial non trouvé, customer non assigné', [
                    'customer' => $name,
                    'commercial_email' => $commercialEmail,
                ]);
            }
        }

        return $customer;
    }

    /**
     * Process contact from row data.
     *
     * @param array<string, mixed> $rowData
     */
    private function processContact(Customer $customer, array $rowData): void
    {
        /** @var ContactRepository $contactRepository */
        $contactRepository = $this->entityManager->getRepository(Contact::class);

        $email = isset($rowData['email']) && is_string($rowData['email']) ? $rowData['email'] : null;
        // Bug fix: Excel renvoie les numéros de téléphone comme des int, pas des string
        $phone = isset($rowData['phone']) && (is_string($rowData['phone']) || is_numeric($rowData['phone']))
            ? (string) $rowData['phone']
            : null;

        // Determine first name and last name from available data
        $firstName = '';
        $lastName = '';

        // Priority 1: Use separated firstname/lastname columns if available
        if (isset($rowData['contact_firstname']) && is_string($rowData['contact_firstname'])) {
            $firstName = trim($rowData['contact_firstname']);
        }
        if (isset($rowData['contact_lastname']) && is_string($rowData['contact_lastname'])) {
            $lastName = trim($rowData['contact_lastname']);
        }

        // Priority 2: Parse from 'contact' column (full name) if firstname/lastname not set
        if (empty($firstName) && empty($lastName)) {
            $contactNameValue = $rowData['contact'] ?? '';
            $contactName = is_string($contactNameValue) ? trim($contactNameValue) : '';

            if (!empty($contactName)) {
                if (!str_contains($contactName, ' ')) {
                    $firstName = $contactName;
                } else {
                    [$firstName, $lastName] = explode(' ', $contactName, 2);
                }
            }
        }

        // Build search name for duplicate detection
        $searchName = trim($firstName.' '.$lastName);

        $existingContact = $contactRepository->findContactByCustomerAndEmailOrNumber(
            $customer,
            $searchName,
            $email,
            $phone
        );

        if (!$existingContact) {
            $contact = new Contact();
            $contact->setFirstName($firstName);
            $contact->setLastName($lastName);
            $contact->setEmail($email);
            $contact->setPhone($phone);
            $contact->setCustomer($customer);
            $this->entityManager->persist($contact);

            $this->logger->debug('Contact created', [
                'customer_id' => $customer->getId(),
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
            ]);
        }
    }

    private function processComment(Customer $customer, string $commentText): void
    {
        $commentText = trim($commentText);
        if (empty($commentText)) {
            return;
        }

        $comment = $this->entityManager->getRepository(Comment::class)
            ->findOneBy(['customer' => $customer]);

        if (!$comment) {
            $comment = new Comment();
            $comment->setCustomer($customer);
            $comment->setNote($commentText);
            $this->entityManager->persist($comment);
        }
    }

    /**
     * Process energy from row data.
     *
     * @param array<string, mixed> $rowData
     */
    private function processEnergy(Customer $customer, array $rowData): void
    {
        // Extract PCE/PDL code
        $pceValue = $rowData['pce_pdl'] ?? null;
        $pceCode = null;

        if (is_numeric($pceValue) && $pceValue > 0) {
            $pceCode = (int) $pceValue;
        }

        // Determine energy type
        $energyType = EnergyType::ELEC; // Default value
        if (!empty($rowData['energy_type']) && is_string($rowData['energy_type'])) {
            $energyType = $this->parseEnergyType($rowData['energy_type']);
        }

        // Get or create provider
        $provider = null;
        if (!empty($rowData['provider']) && is_string($rowData['provider'])) {
            $provider = $this->entityManager->getRepository(EnergyProvider::class)
                ->findOneBy(['name' => $rowData['provider']]);

            if (!$provider) {
                $provider = new EnergyProvider();
                $provider->setName($rowData['provider']);
                $this->entityManager->persist($provider);
                $this->entityManager->flush(); // Immediate flush to use it
            }
        }

        // Find existing energy
        $energy = null;

        // 1. Try to find by code + type (unique)
        if ($pceCode) {
            $energy = $this->energyRepository->findOneBy([
                'code' => (string) $pceCode,
                'type' => $energyType,
            ]);
        }

        // 2. If not found and we have a provider, try by customer + provider + type
        if (!$energy && $provider) {
            $energy = $this->energyRepository->findOneBy([
                'customer' => $customer,
                'energyProvider' => $provider,
                'type' => $energyType,
            ]);
        }

        // 3. If customer has exactly one energy of this type, use it
        // Bug fix: Ne réutiliser que si on n'a PAS de code PDL/PCE dans les données
        // Si on a un code PDL/PCE différent, c'est un nouveau compteur à créer
        if (!$energy && !$pceCode) {
            $energiesOfType = $this->energyRepository->findBy([
                'customer' => $customer,
                'type' => $energyType,
            ]);

            if (1 === count($energiesOfType)) {
                $energy = $energiesOfType[0];
            }
        }

        if (!$energy) {
            // Create new energy
            $energy = new Energy();

            if ($provider) {
                $energy->setEnergyProvider($provider);
            }

            $energy->setType($energyType);

            if ($pceCode) {
                $energy->setCode((string) $pceCode);
            }

            $energy->setCustomer($customer);
            $this->entityManager->persist($energy);
        } else {
            // Update existing energy
            if ($provider) {
                $energy->setEnergyProvider($provider);
            }

            // Update code if provided and energy doesn't have one
            if ($pceCode && empty($energy->getCode())) {
                $energy->setCode((string) $pceCode);
            }

            // Update customer if energy belonged to another customer
            if ($energy->getCustomer() !== $customer) {
                $energy->setCustomer($customer);
            }
        }

        // Handle contract end date
        if (!empty($rowData['contract_end'])) {
            if ($rowData['contract_end'] instanceof \DateTime) {
                $newDate = $rowData['contract_end'];
            } elseif (is_string($rowData['contract_end'])) {
                $newDate = $this->parseDate($rowData['contract_end']);
            } else {
                $newDate = null;
            }

            if ($newDate) {
                $existingDate = $energy->getContractEnd();

                // Update only if no existing date or new date is more recent
                if (!$existingDate || $newDate > $existingDate) {
                    $energy->setContractEnd($newDate);
                    $this->logger->debug('Mise à jour de la date d\'échéance', [
                        'customer' => $customer->getName(),
                        'old_date' => $existingDate ? $existingDate->format('Y-m-d') : 'aucune',
                        'new_date' => $newDate->format('Y-m-d'),
                    ]);
                } else {
                    $this->logger->debug('Conservation de la date d\'échéance existante (plus récente)', [
                        'customer' => $customer->getName(),
                        'existing_date' => $existingDate->format('Y-m-d'),
                        'imported_date' => $newDate->format('Y-m-d'),
                    ]);
                }
            }
        }
    }

    private function parseDate(string $dateStr): ?\DateTimeInterface
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        // Try multiple common date formats
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d', 'd.m.Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date && $date->format($format) === $dateStr) {
                return $date;
            }
        }

        return null;
    }

    private function parseEnergyType(string $typeStr): EnergyType
    {
        $typeStr = strtoupper(trim($typeStr));

        return match ($typeStr) {
            'GAZ', 'GAS', 'G' => EnergyType::GAZ,
            default => EnergyType::ELEC,
        };
    }

    private function addImportError(Import $import, int $rowIndex, string $message): void
    {
        $error = new ImportError();
        $error->setImport($import);
        $error->setRowNumber($rowIndex);
        $error->setMessage($message);
        $error->setSeverity(ImportErrorSeverity::ERROR);
        $error->setCreatedAt(new \DateTimeImmutable());

        $import->addError($error);
        $this->entityManager->persist($error);
    }
}
