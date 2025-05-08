<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service pour suivre et exporter les erreurs d'importation.
 */
class ImportErrorTracker
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $importErrors = [];

    /**
     * @var array<int|string, mixed>|null
     */
    private ?array $headers = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $currentImport = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/import')]
        private readonly string $importDirectory,
    ) {
    }

    /**
     * @param string                   $originalFilename Nom de fichier d'origine
     * @param array<int|string, mixed> $headers          En-têtes du fichier
     *
     * Démarre le suivi d'un nouvel import
     */
    public function startTracking(string $originalFilename, array $headers): void
    {
        $this->headers = $headers;

        // Initialiser le tableau des erreurs pour ce fichier s'il n'existe pas encore
        if (!isset($this->importErrors[$originalFilename])) {
            $this->importErrors[$originalFilename] = [];
        }

        $id = uniqid('batch_');
        $errorFilename = $this->generateErrorFilename($originalFilename);

        $this->currentImport = [
            'id' => $id,
            'original_filename' => $originalFilename,
            'started_at' => new \DateTime(),
            'error_filename' => $errorFilename,
            'batch_errors' => 0,
            'warnings' => 0,
            'exceptions' => 0,
        ];

        $this->logger->info('Started tracking import errors', [
            'batch_id' => $id,
            'original_file' => $originalFilename,
            'error_file' => $errorFilename,
        ]);
    }

    /**
     * @param int                  $rowIndex Index de la ligne dans le fichier
     * @param array<string, mixed> $rowData  Données de la ligne
     * @param string               $message  Message d'erreur
     *
     * Enregistre une ligne qui n'a pas pu être importée à cause d'un avertissement
     */
    public function trackWarning(int $rowIndex, array $rowData, string $message): void
    {
        $this->trackError($rowIndex, $rowData, $message, 'warning');
    }

    /**
     * @param int                  $rowIndex  Index de la ligne dans le fichier
     * @param array<string, mixed> $rowData   Données de la ligne
     * @param string               $message   Message d'erreur
     * @param \Throwable|null      $exception Exception associée
     *
     * Enregistre une ligne qui n'a pas pu être importée à cause d'une exception
     */
    public function trackException(int $rowIndex, array $rowData, string $message, ?\Throwable $exception = null): void
    {
        $this->trackError($rowIndex, $rowData, $message, 'exception', $exception);
    }

    /**
     * @param int                  $rowIndex  Index de la ligne dans le fichier
     * @param array<string, mixed> $rowData   Données de la ligne
     * @param string               $message   Message d'erreur
     * @param string               $type      Type d'erreur (warning, exception)
     * @param \Throwable|null      $exception Exception associée
     *
     * Enregistre une erreur d'importation générique
     */
    private function trackError(
        int $rowIndex,
        array $rowData,
        string $message,
        string $type = 'error',
        ?\Throwable $exception = null,
    ): void {
        if (!$this->currentImport) {
            // Au lieu de déclencher une exception, initialisons le suivi
            $this->startTracking('emergency_import_'.date('Y-m-d_His'), []);
            $this->logger->warning('Auto-started import tracking session for error tracking');
        }

        // Augmenter le compteur approprié
        if ('warning' === $type) {
            ++$this->currentImport['warnings'];
        } elseif ('exception' === $type) {
            ++$this->currentImport['exceptions'];
        }

        ++$this->currentImport['batch_errors'];

        // Enregistrer l'erreur dans le tableau indexé par nom de fichier
        $filename = $this->currentImport['original_filename'];

        $this->importErrors[$filename][] = [
            'row_index' => $rowIndex,
            'data' => $rowData,
            'message' => $message,
            'type' => $type,
            'batch_id' => $this->currentImport['id'],
            'exception' => $exception ? [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'trace_summary' => $this->getShortTrace($exception),
            ] : null,
            'timestamp' => new \DateTime(),
        ];

        $this->logger->debug('Tracked import error', [
            'file' => $filename,
            'batch_id' => $this->currentImport['id'],
            'row' => $rowIndex,
            'type' => $type,
            'message' => $message,
        ]);
    }

    /**
     * Génère un fichier Excel contenant toutes les lignes qui n'ont pas pu être importées.
     */
    public function exportErrorReport(): ?string
    {
        // Même si aucune erreur n'est trouvée, créer quand même un rapport vide
        if (!$this->currentImport) {
            $this->logger->warning('No active import tracking session when exporting error report');

            return null;
        }

        $filename = $this->currentImport['original_filename'];
        $fileErrors = $this->importErrors[$filename] ?? [];

        try {
            // Créer un nouveau fichier Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Erreurs d\'import');

            // Créer l'en-tête avec les colonnes originales + une colonne d'erreur
            $headers = $this->headers ? [...$this->headers, 'Num. ligne', 'Message d\'erreur', 'Type d\'erreur'] : ['Données', 'Num. ligne', 'Message d\'erreur', 'Type d\'erreur'];
            $sheet->fromArray([$headers], null, 'A1');

            // Style pour l'en-tête
            $headerStyle = $sheet->getStyle('A1:'.$this->columnLetter(count($headers)).'1');
            $headerStyle->getFont()->setBold(true);

            // Ajouter les données d'erreur
            $rowIndex = 2;

            if (!empty($fileErrors)) {
                foreach ($fileErrors as $error) {
                    $rowData = $error['data'];

                    try {
                        // Si nous avons les en-têtes, réorganiser les données dans le bon ordre
                        if ($this->headers) {
                            $orderedData = [];
                            foreach ($this->headers as $headerKey) {
                                if (!is_string($headerKey)) {
                                    continue; // Ignorer les clés non valides
                                }
                                $normalizedKey = $this->normalizeHeaderKey($headerKey);
                                // Sécuriser l'accès aux données qui pourraient ne pas exister
                                $orderedData[] = isset($rowData[$normalizedKey]) ? $rowData[$normalizedKey] : null;
                            }

                            // Ajouter les informations d'erreur
                            $orderedData[] = $error['row_index']; // Numéro de ligne
                            $orderedData[] = $error['message']; // Message d'erreur
                            $orderedData[] = $error['type']; // Type d'erreur

                            $sheet->fromArray([$orderedData], null, 'A'.$rowIndex);
                        } else {
                            // Si pas d'en-têtes ou si les données sont brutes/incomplètes
                            if (is_array($rowData)) {
                                if (array_key_exists('raw_data', $rowData)) {
                                    // Données brutes de l'extraction
                                    $raw_data = $rowData['raw_data'];
                                    $sheet->setCellValue('A'.$rowIndex, json_encode($raw_data, JSON_UNESCAPED_UNICODE));
                                } else {
                                    // Données normales
                                    $sheet->setCellValue('A'.$rowIndex, json_encode($rowData, JSON_UNESCAPED_UNICODE));
                                }
                            } else {
                                // Cas où $rowData n'est pas un tableau
                                $sheet->setCellValue('A'.$rowIndex, 'Données non disponibles');
                            }

                            $sheet->setCellValue('B'.$rowIndex, $error['row_index']);
                            $sheet->setCellValue('C'.$rowIndex, $error['message']);
                            $sheet->setCellValue('D'.$rowIndex, $error['type']);
                        }

                        // Style pour les lignes d'erreur
                        if ('exception' === $error['type']) {
                            $sheet->getStyle('A'.$rowIndex.':'.$this->columnLetter(count($headers)).$rowIndex)
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FFCCCC'); // Light red
                        } elseif ('warning' === $error['type']) {
                            $sheet->getStyle('A'.$rowIndex.':'.$this->columnLetter(count($headers)).$rowIndex)
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('FFFFCC'); // Light yellow
                        }
                    } catch (\Exception $e) {
                        // En cas d'erreur pendant l'écriture d'une ligne, on écrit une version simplifiée
                        $sheet->setCellValue('A'.$rowIndex, 'Erreur de données');
                        $sheet->setCellValue('B'.$rowIndex, $error['row_index']);
                        $sheet->setCellValue('C'.$rowIndex, 'Erreur lors de l\'écriture des données: '.$e->getMessage());
                        $sheet->setCellValue('D'.$rowIndex, 'error_report_failure');

                        $this->logger->warning('Error while writing row to error report', [
                            'row' => $error['row_index'],
                            'exception' => $e->getMessage(),
                        ]);
                    }

                    ++$rowIndex;
                }
            } else {
                // Ajouter une ligne indiquant qu'aucune erreur n'a été trouvée
                $sheet->setCellValue('A'.$rowIndex, 'Aucune erreur détectée');
                $sheet->setCellValue('B'.$rowIndex, 'L\'importation s\'est terminée avec succès');
                $sheet->setCellValue('C'.$rowIndex, 'info');

                // Fusionner les cellules pour le message
                $sheet->mergeCells('A'.$rowIndex.':'.$this->columnLetter(count($headers) - 1).$rowIndex);
            }

            // Auto-size columns
            foreach (range('A', $this->columnLetter(count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Créer le dossier d'export si nécessaire
            $exportDir = $this->importDirectory.'/errors';
            if (!$this->filesystem->exists($exportDir)) {
                $this->filesystem->mkdir($exportDir, 0777);
            }

            // Vérifier que le dossier est accessible en écriture
            if (!is_writable($exportDir)) {
                throw new \RuntimeException('Export directory is not writable: '.$exportDir);
            }

            // Utiliser un nom de fichier basé sur le fichier original
            $safeName = $this->slugger->slug(pathinfo($filename, PATHINFO_FILENAME));
            $errorFilename = 'errors_'.$safeName.'.xlsx';

            // Enregistrer le fichier
            $filePath = $exportDir.'/'.$errorFilename;
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            $this->logger->info('Export of error report completed', [
                'file' => $filename,
                'error_count' => count($fileErrors),
                'error_file' => $filePath,
            ]);

            return $filePath;
        } catch (\Exception $e) {
            $this->logger->error('Failed to export error report: '.$e->getMessage(), [
                'file' => $filename,
                'exception' => $e,
            ]);

            // Tenter d'écrire le rapport d'erreur dans le journal avec tous les détails
            $this->logger->error('Error report details that could not be exported:', [
                'errors_count' => count($fileErrors),
            ]);

            return null;
        }
    }

    /**
     * Obtient un résumé des erreurs.
     *
     * @return array<string, mixed>
     */
    public function getErrorSummary(): array
    {
        if (!$this->currentImport) {
            return [
                'status' => 'no_import',
                'error_count' => 0,
            ];
        }

        $filename = $this->currentImport['original_filename'];
        $fileErrors = $this->importErrors[$filename] ?? [];

        return [
            'original_filename' => $filename,
            'started_at' => $this->currentImport['started_at'],
            'total_errors' => count($fileErrors),
            'batch_errors' => $this->currentImport['batch_errors'],
            'warnings' => $this->currentImport['warnings'],
            'exceptions' => $this->currentImport['exceptions'],
            'error_filename' => 'errors_'.$this->slugger->slug(pathinfo($filename, PATHINFO_FILENAME)).'.xlsx',
        ];
    }

    /**
     * Génère un nom de fichier pour le rapport d'erreurs.
     */
    private function generateErrorFilename(string $originalFilename): string
    {
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($baseName);

        return sprintf(
            'errors_%s_%s.xlsx',
            $safeName,
            (new \DateTime())->format('Y-m-d_His')
        );
    }

    /**
     * Convertit un index de colonne en lettre (1 = A, 2 = B, etc.).
     */
    private function columnLetter(int $columnIndex): string
    {
        $columnLetter = '';

        while ($columnIndex > 0) {
            $modulo = ($columnIndex - 1) % 26;
            $columnLetter = chr(65 + $modulo).$columnLetter;
            $columnIndex = intval(($columnIndex - $modulo) / 26);
        }

        return $columnLetter;
    }

    /**
     * Obtient un résumé court de la stack trace.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getShortTrace(\Throwable $e, int $limit = 3): array
    {
        $trace = [];
        $traceData = $e->getTrace();

        $count = 0;
        foreach ($traceData as $frame) {
            if ($count >= $limit) {
                break;
            }

            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            // @phpstan-ignore-next-line
            $function = $frame['function'] ?? 'unknown';
            $class = $frame['class'] ?? '';

            $trace[] = [
                'file' => basename($file),
                'line' => $line,
                'call' => ($class ? "$class::" : '')."$function()",
            ];

            ++$count;
        }

        return $trace;
    }

    /**
     * Normalise une clé d'en-tête (similaire à la méthode dans ProcessExcelBatchMessageHandler).
     */
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
}
