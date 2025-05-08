<?php

namespace App\MessageHandler;

use App\Message\ProcessExcelBatchMessage;
use App\Message\StartImportMessage;
use PhpOffice\PhpSpreadsheet\Reader\DefaultReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class StartImportMessageHandler
{
    // Nombre de lignes par lot
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(StartImportMessage $message): void
    {
        ini_set('memory_limit', '2048M');
        $filePath = $message->getFilePath();
        $this->logger->info('Starting import of file: '.$message->getOriginalFilename(), [
            'file_path' => $filePath,
        ]);

        // Utilisons le reader en mode itératif (streaming)
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);

        // Cette option est cruciale pour le traitement en streaming
        $reader->setReadFilter(new DefaultReadFilter());

        try {
            // N'ouvrons le fichier que pour compter les lignes et lire l'en-tête
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestDataRow();

            // Récupération de l'en-tête
            /** @var array<int|string, mixed> $headerRow */
            $headerRow = $worksheet->rangeToArray('A1:'.$worksheet->getHighestDataColumn().'1', null, true, false)[0];

            // Libérer des ressources
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->logger->info('File analysis complete', [
                'rows' => $highestRow,
                'header' => $headerRow,
            ]);

            // Traitement par lots
            for ($startRow = 2; $startRow <= $highestRow; $startRow += self::BATCH_SIZE) {
                $endRow = min($startRow + self::BATCH_SIZE - 1, $highestRow);

                // Création d'un message pour le traitement de ce lot
                $batchMessage = new ProcessExcelBatchMessage(
                    $filePath,
                    $startRow,
                    $endRow,
                    $message->getUserId(),
                    $headerRow,
                    $message->getOriginalFilename()
                );

                // Envoi du message asynchrone
                $this->messageBus->dispatch($batchMessage);

                $this->logger->debug('Dispatched batch processing', [
                    'start_row' => $startRow,
                    'end_row' => $endRow,
                ]);
            }

            $this->logger->info('All batch jobs have been dispatched for file', [
                'file' => $message->getOriginalFilename(),
                'total_rows' => $highestRow - 1,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error during import initialization: '.$e->getMessage(), [
                'file' => $message->getOriginalFilename(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
