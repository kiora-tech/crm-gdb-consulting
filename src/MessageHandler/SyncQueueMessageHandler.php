<?php

namespace App\MessageHandler;

use App\Message\SyncQueueMessage;
use App\Service\OfflineSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * SyncQueueMessageHandler processes asynchronous sync operations from the message queue.
 */
#[AsMessageHandler]
class SyncQueueMessageHandler
{
    public function __construct(
        private readonly OfflineSyncService $syncService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncQueueMessage $message): void
    {
        $this->logger->info('Processing sync queue message', [
            'attempt' => $message->getAttemptCount() + 1,
            'priority' => $message->getPriority(),
            'entityType' => $message->getEntityType(),
            'operation' => $message->getOperation(),
        ]);

        try {
            // Process the sync operation
            $result = $this->syncService->performFullSync($message->getSyncData());

            if ($result['success']) {
                $this->logger->info('Sync operation completed successfully', [
                    'pushed' => count($result['pushed'] ?? []),
                    'pulled' => count($result['pulled'] ?? []),
                    'conflicts' => count($result['conflicts'] ?? []),
                ]);
            } else {
                $this->handleSyncFailure($message, $result['errors'] ?? []);
            }
        } catch (\Exception $e) {
            $this->logger->error('Sync operation failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleSyncException($message, $e);
        }
    }

    /**
     * Handle sync failure with potential retry.
     */
    private function handleSyncFailure(SyncQueueMessage $message, array $errors): void
    {
        $this->logger->warning('Sync operation failed', [
            'errors' => $errors,
            'attempt' => $message->getAttemptCount() + 1,
        ]);

        if ($message->shouldRetry()) {
            $this->retryMessage($message);
        } else {
            $this->logger->critical('Sync operation failed after maximum retries', [
                'syncData' => $message->getSyncData(),
                'totalAttempts' => $message->getAttemptCount() + 1,
            ]);

            // Could send notification or store in failed queue for manual review
            $this->handlePermanentFailure($message);
        }
    }

    /**
     * Handle sync exception with potential retry.
     */
    private function handleSyncException(SyncQueueMessage $message, \Exception $exception): void
    {
        // Check if it's a temporary error that should be retried
        if ($this->isTemporaryError($exception)) {
            if ($message->shouldRetry()) {
                $this->retryMessage($message);
            } else {
                $this->handlePermanentFailure($message);
            }
        } else {
            // Permanent error, don't retry
            $this->logger->critical('Permanent sync error detected', [
                'error' => $exception->getMessage(),
                'syncData' => $message->getSyncData(),
            ]);

            $this->handlePermanentFailure($message);
        }
    }

    /**
     * Retry the message with delay.
     */
    private function retryMessage(SyncQueueMessage $message): void
    {
        $message->incrementAttempt();
        $delaySeconds = $message->getRetryDelay();

        $this->logger->info('Retrying sync operation', [
            'nextAttempt' => $message->getAttemptCount() + 1,
            'delaySeconds' => $delaySeconds,
        ]);

        // Re-dispatch with delay
        $this->messageBus->dispatch(
            $message,
            [new DelayStamp($delaySeconds * 1000)] // Convert to milliseconds
        );
    }

    /**
     * Handle permanent failure.
     */
    private function handlePermanentFailure(SyncQueueMessage $message): void
    {
        // Log critical failure
        $this->logger->critical('Sync operation permanently failed', [
            'syncData' => $message->getSyncData(),
            'totalAttempts' => $message->getAttemptCount() + 1,
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);

        // Here you could:
        // 1. Store in a dead letter queue
        // 2. Send email notification to admin
        // 3. Create a system alert
        // 4. Store in database for manual review

        // For now, we'll just log it
        // In production, you'd want to implement proper dead letter queue handling
    }

    /**
     * Check if error is temporary and should be retried.
     */
    private function isTemporaryError(\Exception $exception): bool
    {
        $message = $exception->getMessage();

        // Network errors
        if (str_contains($message, 'Connection refused')
            || str_contains($message, 'Connection timed out')
            || str_contains($message, 'Network is unreachable')) {
            return true;
        }

        // Database connection errors
        if (str_contains($message, 'SQLSTATE[HY000]')
            || str_contains($message, 'Connection to database lost')
            || str_contains($message, 'Lock wait timeout exceeded')) {
            return true;
        }

        // HTTP errors that might be temporary
        if (str_contains($message, '502') // Bad Gateway
            || str_contains($message, '503') // Service Unavailable
            || str_contains($message, '504') // Gateway Timeout
            || str_contains($message, '429')) { // Too Many Requests
            return true;
        }

        // All other errors are considered permanent
        return false;
    }
}

