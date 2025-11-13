<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use App\Domain\Import\ValueObject\ImportFileInfo;
use App\Entity\Import;
use App\Entity\ImportStatus;
use App\Entity\ImportType;
use App\Entity\User;
use App\Repository\ImportRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Main facade for the import module.
 *
 * This service acts as the primary entry point for all import operations,
 * coordinating between the various import services and managing the import lifecycle.
 * It follows the Facade pattern to simplify the import process for consumers.
 *
 * Responsibilities:
 * - Initialize new imports
 * - Trigger analysis phase
 * - Confirm and start processing
 * - Cancel imports
 * - Retrieve import details with eager-loaded relations
 */
readonly class ImportOrchestrator
{
    /**
     * @param ImportRepository       $importRepository Repository for Import entities
     * @param EntityManagerInterface $entityManager    Doctrine entity manager
     * @param FileStorageService     $fileStorage      Service for managing import files
     * @param ImportAnalyzer         $analyzer         Service for analyzing imports
     * @param ImportProcessor        $processor        Service for processing imports
     * @param ImportNotifier         $notifier         Service for sending notifications
     */
    public function __construct(
        private ImportRepository $importRepository,
        private EntityManagerInterface $entityManager,
        private FileStorageService $fileStorage,
        private ImportAnalyzer $analyzer,
        private ImportProcessor $processor,
        private ImportNotifier $notifier,
    ) {
    }

    /**
     * Initialize a new import with the uploaded file information.
     *
     * Creates a new Import entity with PENDING status and persists it to the database.
     * The import is ready to be analyzed after initialization.
     *
     * @param ImportFileInfo $fileInfo File information from the upload
     * @param ImportType     $type     Type of import to perform
     * @param User           $user     User who initiated the import
     *
     * @return Import The newly created Import entity with generated ID
     */
    public function initializeImport(ImportFileInfo $fileInfo, ImportType $type, User $user): Import
    {
        $import = new Import();
        $import->setOriginalFilename($fileInfo->originalName);
        $import->setStoredFilename($fileInfo->storedFilename);
        $import->setType($type);
        $import->setUser($user);
        $import->setStatus(ImportStatus::PENDING);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Start the analysis phase for an import.
     *
     * Validates that the import is in PENDING status, marks it as ANALYZING,
     * and dispatches an asynchronous message to analyze the import file.
     *
     * @param Import $import The import to analyze
     *
     * @throws \LogicException If the import is not in PENDING status
     */
    public function startAnalysis(Import $import): void
    {
        if (ImportStatus::PENDING !== $import->getStatus()) {
            throw new \LogicException(sprintf('L\'import doit être en statut PENDING pour être analysé (statut actuel: %s)', $import->getStatus()->value));
        }

        $import->markAsAnalyzing();
        $this->entityManager->flush();

        $this->analyzer->analyzeAsync($import);
    }

    /**
     * Confirm the analysis results and start processing the import.
     *
     * Validates that the import is in AWAITING_CONFIRMATION status, marks it as PROCESSING,
     * and dispatches asynchronous batch messages to process the import data.
     *
     * @param Import $import The import to process
     *
     * @throws \LogicException If the import is not in AWAITING_CONFIRMATION status
     */
    public function confirmAndProcess(Import $import): void
    {
        if (ImportStatus::AWAITING_CONFIRMATION !== $import->getStatus()) {
            throw new \LogicException(sprintf('L\'import doit être en statut AWAITING_CONFIRMATION pour être traité (statut actuel: %s)', $import->getStatus()->value));
        }

        $import->markAsProcessing();
        $this->entityManager->flush();

        $this->processor->processAsync($import);

        // CRITICAL: Flush after dispatching messages to ensure they are persisted to messenger_messages table
        // Messenger with Doctrine transport requires the transaction to be committed
        $this->entityManager->flush();
    }

    /**
     * Cancel an import and clean up associated resources.
     *
     * Validates that the import can be cancelled from its current status,
     * marks it as CANCELLED, deletes the uploaded file, and sends a notification
     * to the user.
     *
     * @param Import $import The import to cancel
     *
     * @throws \LogicException If the import cannot be cancelled from its current status
     */
    public function cancelImport(Import $import): void
    {
        $status = $import->getStatus();

        if (!$status->canBeCancelled()) {
            throw new \LogicException(sprintf('L\'import ne peut pas être annulé depuis le statut %s', $status->value));
        }

        $import->markAsCancelled();
        $this->entityManager->flush();

        // Delete the file from storage
        try {
            $this->fileStorage->deleteImportFile($import);
        } catch (\RuntimeException $e) {
            // Log the error but don't fail the cancellation
            // The import is already marked as cancelled in the database
        }

        // Notify the user
        try {
            $this->notifier->notifyCancellation($import);
        } catch (\Exception $e) {
            // Log the error but don't fail the cancellation
            // The import is already marked as cancelled in the database
        }
    }

    /**
     * Retrieve an import with all related details eagerly loaded.
     *
     * Uses the repository's optimized query to fetch the import with its
     * errors, analysis results, and user in a single database query to avoid
     * N+1 query problems.
     *
     * @param int $importId The ID of the import to retrieve
     *
     * @return Import|null The import with all relations loaded, or null if not found
     */
    public function getImportWithDetails(int $importId): ?Import
    {
        return $this->importRepository->findOneWithDetails($importId);
    }
}
