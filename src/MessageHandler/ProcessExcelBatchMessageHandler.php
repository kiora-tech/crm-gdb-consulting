<?php

namespace App\MessageHandler;

use App\Entity\Customer;
use App\Entity\User;
use App\Message\ProcessExcelBatchMessage;
use App\Service\ExcelBatchReadFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessExcelBatchMessageHandler
{
    // Nombre de lignes à traiter avant de réinitialiser l'EntityManager
    private const FLUSH_INTERVAL = 20;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessExcelBatchMessage $message): void
    {
        $filePath = $message->getFilePath();
        $startRow = $message->getStartRow();
        $endRow = $message->getEndRow();
        $headerRow = $message->getHeaderRow();
        $userId = $message->getUserId();

        $this->logger->info('Processing customer assignment batch', [
            'file' => $filePath,
            'start_row' => $startRow,
            'end_row' => $endRow
        ]);

        try {
            // Charger seulement les lignes nécessaires pour ce lot
            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new ExcelBatchReadFilter($startRow, $endRow));
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Récupérer une référence à l'utilisateur une seule fois
            $entityManager = $this->doctrine->getManager();
            $user = $entityManager->getReference(User::class, $userId);

            // Compteur pour le flush périodique
            $processedCount = 0;

            // Traiter chaque ligne du fichier
            for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
                try {
                    $rowData = $this->getRowData($worksheet, $rowIndex, $headerRow);

                    // OPTION 1: Recherche par SIRET
                    if (!empty($rowData['siret'])) {
                        $siret = $this->cleanSiret($rowData['siret']);

                        if (!empty($siret)) {
                            if ($this->assignUserToCustomerBySiret($entityManager, $siret, $user)) {
                                $processedCount++;
                            }
                        }
                    }
                    // OPTION 2: Fallback - Recherche par nom d'établissement si le SIRET est absent
                    elseif (!empty($rowData['name'])) {
                        $name = trim($rowData['name']);

                        if (!empty($name)) {
                            if ($this->assignUserToCustomerByName($entityManager, $name, $user)) {
                                $processedCount++;
                            }
                        }
                    }

                    // Flush périodique pour éviter de saturer la mémoire
                    if ($processedCount > 0 && $processedCount % self::FLUSH_INTERVAL === 0) {
                        $entityManager->flush();
                        $entityManager->clear();

                        // Récupérer à nouveau la référence à l'utilisateur après clear()
                        $user = $entityManager->getReference(User::class, $userId);

                        $this->logger->info('Intermediary flush completed', [
                            'processed_rows' => $processedCount
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing row: ' . $e->getMessage(), [
                        'row' => $rowIndex,
                        'exception' => $e
                    ]);
                }
            }

            // Flush final pour les dernières entités
            if ($processedCount > 0 && $processedCount % self::FLUSH_INTERVAL !== 0) {
                $entityManager->flush();
            }

            // Libérer la mémoire du spreadsheet
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();

            $this->logger->info('Customer assignment batch completed', [
                'processed_rows' => $processedCount
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing batch: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * Assigne un utilisateur à un client trouvé par SIRET
     * @return bool True si un client a été trouvé et assigné
     */
    private function assignUserToCustomerBySiret(EntityManagerInterface $entityManager, string $siret, User $user): bool
    {
        $customer = $entityManager->getRepository(Customer::class)
            ->findOneBy(['siret' => $siret]);

        if ($customer) {
            $customer->setUser($user);
            $this->logger->info('User assigned to customer by SIRET', [
                'siret' => $siret,
                'user_id' => $user->getId(),
                'customer_id' => $customer->getId()
            ]);
            return true;
        } else {
            $this->logger->warning('Customer not found for SIRET', [
                'siret' => $siret
            ]);
            return false;
        }
    }

    /**
     * Assigne un utilisateur à un client trouvé par nom d'établissement
     * @return bool True si un client a été trouvé et assigné
     */
    private function assignUserToCustomerByName(EntityManagerInterface $entityManager, string $name, User $user): bool
    {
        $customer = $entityManager->getRepository(Customer::class)
            ->findOneBy(['name' => $name]);

        if ($customer) {
            $customer->setUser($user);
            $this->logger->info('User assigned to customer by name', [
                'name' => $name,
                'user_id' => $user->getId(),
                'customer_id' => $customer->getId()
            ]);
            return true;
        } else {
            $this->logger->warning('Customer not found for name', [
                'name' => $name
            ]);
            return false;
        }
    }

    /**
     * Nettoie le SIRET en supprimant les espaces et caractères non numériques
     */
    private function cleanSiret($siret): string
    {
        if (!is_string($siret) && !is_numeric($siret)) {
            return '';
        }

        $siret = (string)$siret;

        // Supprimer tous les caractères non numériques
        return preg_replace('/[^0-9]/', '', $siret);
    }

    /**
     * Récupère les données d'une ligne en utilisant les en-têtes
     */
    private function getRowData($worksheet, int $rowIndex, array $headerRow): array
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

            $rowData[$normalizedKey] = $value;
        }

        return $rowData;
    }

    /**
     * Normalise une clé d'en-tête pour avoir des noms de champs cohérents
     */
    private function normalizeHeaderKey(string $headerName): string
    {
        // Convertir en minuscules, remplacer les espaces par des underscores
        $key = strtolower(trim($headerName));
        $key = preg_replace('/\s+/', '_', $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key);

        // Mapper les noms de colonnes courants
        $mappings = [
            // SIRET mappings
            'siret' => 'siret',
            'numero_siret' => 'siret',
            'siren' => 'siret',
            'numero_siren' => 'siret',

            // NAME mappings
            'name' => 'name',
            'nom' => 'name',
            'raison_sociale' => 'name',
            'nom_etablissement' => 'name',
            'etablissement' => 'name',
            'client' => 'name',
            'nom_client' => 'name',
            'nom_de_letablissement' => 'name',
        ];

        return $mappings[$key] ?? $key;
    }
}