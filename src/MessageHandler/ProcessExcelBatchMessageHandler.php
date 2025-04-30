<?php

namespace App\MessageHandler;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyType;
use App\Entity\ProspectOrigin;
use App\Entity\ProspectStatus;
use App\Entity\User;
use App\Message\ProcessExcelBatchMessage;
use App\Service\ExcelBatchReadFilter;
use App\Service\ImportErrorTracker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
class ProcessExcelBatchMessageHandler
{
    // Nombre de lignes à traiter avant de réinitialiser l'EntityManager
    private const FLUSH_INTERVAL = 10;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger,
        private readonly ImportErrorTracker $errorTracker,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function __invoke(ProcessExcelBatchMessage $message): void
    {
        $filePath = $message->getFilePath();
        $startRow = $message->getStartRow();
        $endRow = $message->getEndRow();
        $headerRow = $message->getHeaderRow();
        $originalFilename = $message->getOriginalFilename() ?? basename($filePath);
        $userId = $message->getUserId();

        $this->logger->info('Processing batch', [
            'file' => $filePath,
            'start_row' => $startRow,
            'end_row' => $endRow
        ]);

        try {
            // Démarrer le suivi des erreurs pour ce lot
            $this->errorTracker->startTracking($originalFilename, $headerRow);

            // Charger seulement les lignes nécessaires pour ce lot
            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new ExcelBatchReadFilter($startRow, $endRow));
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Obtenir toutes les données des lignes et les mettre en mémoire
            $rowsData = [];
            for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
                try {
                    // Récupérer les données brutes AVANT le filtrage
                    $rawRowData = $worksheet->rangeToArray(
                        'A' . $rowIndex . ':' . $worksheet->getHighestColumn() . $rowIndex,
                        null,
                        true,
                        false
                    )[0];

                    $rowData = $this->getRowData($worksheet, $rowIndex, $headerRow);

                    if (!empty($rowData['name'])) {
                        $rowsData[] = [
                            'rowIndex' => $rowIndex,
                            'data' => $rowData
                        ];
                    } else {
                        $this->logger->warning('Skipping row due to missing name', [
                            'row' => $rowIndex,
                            'raw_data' => $rawRowData
                        ]);
                        // Enregistrer comme warning avec les données brutes
                        $this->errorTracker->trackWarning(
                            $rowIndex,
                            ['raw_data' => $rawRowData],
                            'Ligne ignorée car le nom est manquant'
                        );
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error getting row data: ' . $e->getMessage(), [
                        'row' => $rowIndex,
                        'exception' => $e
                    ]);
                    // Enregistrer l'erreur avec les données brutes disponibles
                    $rawData = $worksheet->rangeToArray(
                        'A' . $rowIndex . ':' . $worksheet->getHighestColumn() . $rowIndex,
                        null,
                        true,
                        false
                    )[0];
                    $this->errorTracker->trackException(
                        $rowIndex,
                        ['raw_data' => $rawData],
                        'Erreur lors de l\'extraction des données de la ligne: ' . $e->getMessage(),
                        $e
                    );
                }
            }

            // Libérer la mémoire du spreadsheet car nous avons maintenant toutes les données en mémoire
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();

            // Traiter les données en petits lots pour éviter les problèmes de mémoire
            foreach (array_chunk($rowsData, self::FLUSH_INTERVAL) as $batch) {
                $this->processBatch($batch, $userId);
            }

            // Générer un rapport d'erreurs si des erreurs ont été trouvées
            $errorSummary = $this->errorTracker->getErrorSummary();
            if ($errorSummary['total_errors'] > 0) {
                $errorFilePath = $this->errorTracker->exportErrorReport();
                $this->logger->info('Error report generated', [
                    'error_file' => $errorFilePath,
                    'errors' => $errorSummary
                ]);
            }

            $this->logger->info('Batch processed successfully', [
                'start_row' => $startRow,
                'end_row' => $endRow,
                'errors_found' => $errorSummary['total_errors']
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing batch: ' . $e->getMessage(), [
                'start_row' => $startRow,
                'end_row' => $endRow,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * Traite un lot de lignes avec un EntityManager frais
     */
    private function processBatch(array $rows, int $userId): void
    {

        try {
            // Pour chaque groupe de lignes
            foreach ($rows as $row) {
                // Obtenir un EntityManager frais pour chaque ligne problématique
                $entityManager = $this->doctrine->getManager();

                if (!$entityManager->isOpen()) {
                    $this->doctrine->resetManager();
                    $entityManager = $this->doctrine->getManager();
                }

                try {
                    $this->processRow($entityManager, $row['rowIndex'], $row['data'], $userId);

                    // Flush après chaque ligne réussie pour éviter de perdre le travail déjà fait
                    $entityManager->flush();
                } catch (\Exception $e) {

                    // Log et tracking de l'erreur
                    $this->logger->error('Error processing row: ' . $e->getMessage(), [
                        'row' => $row['rowIndex'],
                        'data' => $row['data'],
                        'exception' => $e
                    ]);

                    $this->errorTracker->trackException(
                        $row['rowIndex'],
                        $row['data'],
                        'Erreur lors du traitement de la ligne: ' . $e->getMessage() . ' ('.$e->getCode().')',
                        $e
                    );

                    // Annuler la transaction si active
                    if ($entityManager->isOpen() && $entityManager->getConnection()->isTransactionActive()) {
                        $entityManager->getConnection()->rollBack();
                    }

                    // Nettoyer l'EM et le réinitialiser
                    if ($entityManager->isOpen()) {
                        $entityManager->clear();
                    }

                    // Libérer complètement l'EntityManager pour la prochaine ligne
                    $this->doctrine->resetManager();
                }
            }
        } catch (\Exception $e) {
            // Log l'erreur au niveau du lot
            $this->logger->error('Error processing batch: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // S'assurer que l'erreur est bien enregistrée dans le rapport
            $this->errorTracker->trackException(
                0, // Ligne inconnue pour une erreur de lot
                ['batch_error' => true],
                'Erreur lors du traitement du lot: ' . $e->getMessage(),
                $e
            );
        }
    }

    private function processRow(EntityManagerInterface $entityManager, int $rowIndex, array $rowData, int $userId): void
    {
        // Log pour déboguer
        $this->logger->debug('Données de ligne brutes', ['row' => $rowIndex, 'data' => $rowData]);

        // Vérifier que les données minimales sont présentes
        if (empty($rowData['name'])) {
            throw new \InvalidArgumentException("Le nom du client est obligatoire");
        }

        // Transaction pour cette ligne uniquement
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // Créer ou récupérer le client
            $customer = $this->getOrCreateCustomer($entityManager, $rowData['name'], $rowData['lead_origin'] ?? '', $userId);

            // Mise à jour du SIRET si disponible
            if (!empty($rowData['siret'])) {
                $siretWithoutSpaces = str_replace(' ', '', (string)$rowData['siret']);
                $customer->setSiret($siretWithoutSpaces);
            }
            if(!empty($rowData['contact'])){
                $this->processContact($entityManager, $customer, $rowData);
            }

            // Traiter les commentaires
            if (!empty($rowData['comment'])) {
                $this->processComment($entityManager, $customer, $rowData['comment']);
            }

            // Traiter l'énergie - même si pce_pdl est 0, on peut créer une énergie
            if (isset($rowData['pce_pdl']) || !empty($rowData['provider'])) {
                $this->processEnergy($entityManager, $customer, $rowData);
            }

            // Valider les entités avant de committer
            $violations = $this->validator->validate($customer);
            if (count($violations) > 0) {
                $errorMessages = [];
                foreach ($violations as $violation) {
                    $errorMessages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                }
                throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errorMessages));
            }

            // Commettre la transaction
            $connection->commit();
        } catch (\Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            // Throw l'exception pour qu'elle soit capturée par le niveau supérieur
            throw $e;
        }
    }

    private function getRowData(Worksheet $worksheet, int $rowIndex, array $headerRow): array
    {
        // Récupérer les données de la ligne
        $rowArray = $worksheet->rangeToArray(
            'A' . $rowIndex . ':' . $worksheet->getHighestColumn() . $rowIndex,
            null,
            true,
            false
        )[0];

        // Normaliser les clés en fonction du header
        $rowData = [];
        foreach ($headerRow as $colIndex => $headerName) {
            // Sauter les cellules vides dans l'en-tête
            if (empty($headerName) || !is_string($headerName)) {
                continue;
            }

            $normalizedKey = $this->normalizeHeaderKey($headerName);
            $value = $rowArray[$colIndex] ?? null;

            // Traitement spécifique des valeurs
            if ($normalizedKey === 'contract_end' && is_numeric($value)) {
                // Convertir les dates Excel (nombre de jours depuis 1900-01-01)
                $value = $this->convertExcelDate($value);
            }

            $rowData[$normalizedKey] = $value;
        }

        return $rowData;
    }

    /**
     * Convertit un nombre Excel en objet DateTime
     */
    private function convertExcelDate($excelDate): ?\DateTime
    {
        if (!is_numeric($excelDate)) {
            return null;
        }

        try {
            // Date Excel = nombre de jours depuis le 30 décembre 1899 (Excel a un bug avec 1900)
            $unixTimestamp = ($excelDate - 25569) * 86400;
            $date = new \DateTime();
            $date->setTimestamp($unixTimestamp);
            return $date;
        } catch (\Exception $e) {
            $this->logger->warning('Impossible de convertir la date Excel: ' . $excelDate, [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function normalizeHeaderKey(string $headerName): string
    {
        // Convertir en minuscules, remplacer les espaces par des underscores
        $key = strtolower(trim($headerName));
        $key = preg_replace('/\s+/', '_', $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key);

        // Mapper les noms de colonnes courants
        $mappings = [
            'siret' => 'siret',
            'raison_sociale' => 'name',
            'nom' => 'name',
            'nom_dtablissement' => 'name',
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
            'origine_lead' => 'lead_origin',
            'origine' => 'lead_origin',
            'commentaire' => 'comment',
            'commentaires' => 'comment',
            'comment' => 'comment',
            'elec__gaz' => 'energy_type',
            'type_energie' => 'energy_type',
        ];

        return $mappings[$key] ?? $key;
    }

    private function getOrCreateCustomer(EntityManagerInterface $entityManager, string $name, string $leadOrigin, int $userId): Customer
    {
        // Nettoyer le nom pour éviter les problèmes
        $name = trim($name);

        // Limiter la taille si nécessaire
        if (strlen($name) > 255) {
            $name = substr($name, 0, 252) . '...';
        }

        $customer = $entityManager->getRepository(Customer::class)
            ->findOneBy(['name' => $name]);

        if (!$customer) {
            $customer = new Customer();
            $customer->setName($name);
            $customer->setLeadOrigin($leadOrigin ?: 'Import Excel');
            $customer->setOrigin(ProspectOrigin::ACQUISITION);
            $entityManager->persist($customer);
        }

        $customer->setUser($entityManager->getReference(User::class, $userId));

        return $customer;
    }


    private function processContact(EntityManagerInterface $entityManager, Customer $customer, array $rowData): void
    {
        $existingContact = $entityManager->getRepository(Contact::class)
            ->findContactByCustomerAndEmailOrNumber(
                $customer,
                $rowData['contact'] ?? '',
                $rowData['email'] ?? null,
                $rowData['phone'] ?? null
            );

        if (!$existingContact) {
            $contact = new Contact();
            $contact->setEmail($rowData['email']?? null);
            $contact->setPhone($rowData['phone']?? null);

            // Valeurs par défaut pour éviter les contraintes NOT NULL
            $contactName = trim($rowData['contact']);
            //si le contact n'a pas " " alors on met le nom complet dans le nom
            if(!str_contains($contactName, ' ')){
                $contact->setFirstName($contactName);
                $contact->setLastName('');
            }else {
                [$firstName, $lastName] = explode(' ', $contactName, 2);
                $contact->setFirstName($firstName);
                $contact->setLastName($lastName);
            }

            $contact->setCustomer($customer);
            $entityManager->persist($contact);
        }
    }

    private function processComment(EntityManagerInterface $entityManager, Customer $customer, string $commentText): void
    {
        // Vérifier que le commentaire n'est pas vide
        $commentText = trim($commentText);
        if (empty($commentText)) {
            return;
        }

        $comment = $entityManager->getRepository(Comment::class)
            ->findOneBy(['customer' => $customer]);

        if (!$comment) {
            $comment = new Comment();
            $comment->setCustomer($customer);
            $comment->setNote($commentText);
            $entityManager->persist($comment);
        } else {
            $comment->setNote($commentText);
        }
    }

    private function processEnergy(EntityManagerInterface $entityManager, Customer $customer, array $rowData): void
    {
        // Extraire la valeur de code PDL/PCE
        $pceValue = $rowData['pce_pdl'] ?? null;
        $pceCode = null;

        if (is_numeric($pceValue) && $pceValue > 0) {
            $pceCode = (int)$pceValue;
        }

        // Chercher une énergie existante
        $energy = null;
        if ($pceCode) {
            $energy = $entityManager->getRepository(Energy::class)
                ->findOneBy(['code' => $pceCode, 'customer' => $customer]);
        } else {
            // Si pas de code, chercher par fournisseur
            if (!empty($rowData['provider'])) {
                $energy = $entityManager->getRepository(Energy::class)
                    ->findOneBy(['provider' => $rowData['provider'], 'customer' => $customer]);
            }
        }

        if (!$energy) {
            // Créer une nouvelle énergie
            $energy = new Energy();

            if (!empty($rowData['provider'])) {
                $energy->setProvider($rowData['provider']);
            }

            // Déterminer le type d'énergie
            $energyType = EnergyType::ELEC; // Valeur par défaut
            if (!empty($rowData['energy_type'])) {
                $energyType = $this->parseEnergyType($rowData['energy_type']);
            }
            $energy->setType($energyType);

            // Définir le code PDL/PCE si disponible
            if ($pceCode) {
                $energy->setCode($pceCode);
            }

            $energy->setCustomer($customer);
            $entityManager->persist($energy);
        } else {
            // Mettre à jour l'énergie existante
            if (!empty($rowData['provider'])) {
                $energy->setProvider($rowData['provider']);
            }
        }

        if (!empty($rowData['contract_end']) && $rowData['contract_end'] instanceof \DateTime) {
            $energy->setContractEnd($rowData['contract_end']);
        } elseif (!empty($rowData['contract_end']) && !$energy->getContractEnd()) {
            $date = $this->parseDate($rowData['contract_end']);
            if ($date) {
                $energy->setContractEnd($date);
            }
        }
    }

    private function parseDate(string $dateStr): ?\DateTimeInterface
    {
        // Nettoyer la chaîne
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        // Essayer plusieurs formats de date courants
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
            default => EnergyType::ELEC
        };
    }
}
