<?php

declare(strict_types=1);

namespace App\Domain\Import\Service;

use App\Domain\Import\Message\AnalyzeImportMessage;
use App\Entity\Import;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service for orchestrating import analysis.
 *
 * This service is responsible for dispatching import analysis tasks to the
 * message queue for asynchronous processing. It serves as the entry point
 * for triggering import analysis operations.
 */
readonly class ImportAnalyzer
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Trigger asynchronous analysis of an import.
     *
     * This method dispatches a message to the message queue to analyze
     * the import file in the background. The analysis will determine
     * what operations would be performed during import execution.
     *
     * @param Import $import The import entity to analyze
     *
     * @throws \InvalidArgumentException If the import ID is null
     */
    public function analyzeAsync(Import $import): void
    {
        if (null === $import->getId()) {
            throw new \InvalidArgumentException('L\'import doit être persisté avant d\'être analysé');
        }

        $message = new AnalyzeImportMessage($import->getId());
        $this->messageBus->dispatch($message);
    }
}
