<?php

declare(strict_types=1);

namespace App\Domain\Import\MessageHandler;

use App\Domain\Import\Contract\ImportAnalyzerInterface;
use App\Domain\Import\Message\AnalyzeImportMessage;
use App\Domain\Import\Service\FileStorageService;
use App\Domain\Import\Service\ImportNotifier;
use App\Entity\ImportStatus;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for asynchronous import file analysis.
 *
 * This handler processes AnalyzeImportMessage messages to analyze uploaded
 * import files and determine their impact before processing. The analysis
 * examines file structure, validates data, and estimates changes.
 */
#[AsMessageHandler]
readonly class AnalyzeImportMessageHandler
{
    /**
     * @param ImportRepository                  $importRepository Repository for Import entities
     * @param FileStorageService                $fileStorage      Service for file operations
     * @param iterable<ImportAnalyzerInterface> $analyzers        Tagged collection of analyzer implementations
     * @param ImportNotifier                    $notifier         Service for sending notifications
     * @param EntityManagerInterface            $entityManager    Doctrine entity manager
     * @param LoggerInterface                   $logger           Logger for tracking operations
     */
    public function __construct(
        private ImportRepository $importRepository,
        private FileStorageService $fileStorage,
        private iterable $analyzers,
        private ImportNotifier $notifier,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handle the analysis of an import file.
     *
     * @param AnalyzeImportMessage $message The message containing the import ID
     */
    public function __invoke(AnalyzeImportMessage $message): void
    {
        $import = $this->importRepository->find($message->importId);

        if (null === $import) {
            $this->logger->error('Import non trouvé pour l\'analyse', [
                'import_id' => $message->importId,
            ]);

            return;
        }

        // Verify status is ANALYZING
        if (ImportStatus::ANALYZING !== $import->getStatus()) {
            $this->logger->warning('L\'import n\'est pas dans le statut ANALYZING', [
                'import_id' => $import->getId(),
                'status' => $import->getStatus()->value,
            ]);

            return;
        }

        $this->logger->info('Démarrage de l\'analyse de l\'import', [
            'import_id' => $import->getId(),
            'type' => $import->getType()->value,
            'filename' => $import->getOriginalFilename(),
        ]);

        try {
            // Get file path
            $filePath = $this->fileStorage->getImportFilePath($import);

            // Find appropriate analyzer
            $analyzer = $this->findAnalyzer($import->getType());

            if (null === $analyzer) {
                throw new \RuntimeException(sprintf('Aucun analyseur trouvé pour le type d\'import "%s"', $import->getType()->value));
            }

            // Analyze the file
            $analysisImpact = $analyzer->analyze($filePath, $import);

            // Update import with analysis results
            $import->setTotalRows($analysisImpact->totalRows);
            $import->markAsAwaitingConfirmation();

            $this->entityManager->flush();

            // Send notification (non-blocking)
            try {
                $this->notifier->notifyAnalysisComplete($import);
            } catch (\Throwable $notificationError) {
                $this->logger->error('Échec de l\'envoi d\'email d\'analyse terminée', [
                    'import_id' => $import->getId(),
                    'error' => $notificationError->getMessage(),
                ]);
            }

            $this->logger->info('Analyse de l\'import terminée avec succès', [
                'import_id' => $import->getId(),
                'total_rows' => $analysisImpact->totalRows,
                'creations' => $analysisImpact->getTotalCreations(),
                'updates' => $analysisImpact->getTotalUpdates(),
                'errors' => $analysisImpact->errorRows,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'analyse de l\'import', [
                'import_id' => $import->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark import as failed
            $import->markAsFailed();
            $this->entityManager->flush();

            // Send failure notification
            try {
                $this->notifier->notifyFailure($import);
            } catch (\Throwable $notificationError) {
                $this->logger->error('Échec de l\'envoi de la notification d\'échec', [
                    'import_id' => $import->getId(),
                    'error' => $notificationError->getMessage(),
                ]);
            }

            // Re-throw to mark message as failed in messenger
            throw $e;
        }
    }

    /**
     * Find the appropriate analyzer for the given import type.
     *
     * @param \App\Entity\ImportType $type The import type
     *
     * @return ImportAnalyzerInterface|null The analyzer that supports the type, or null if none found
     */
    private function findAnalyzer(\App\Entity\ImportType $type): ?ImportAnalyzerInterface
    {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->supports($type)) {
                return $analyzer;
            }
        }

        return null;
    }
}
