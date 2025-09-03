<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * ConflictResolutionService handles field-level conflict resolution
 * for offline synchronization with multiple merge strategies.
 */
class ConflictResolutionService
{
    public const STRATEGY_SERVER_WINS = 'server_wins';
    public const STRATEGY_CLIENT_WINS = 'client_wins';
    public const STRATEGY_MERGE = 'merge';
    public const STRATEGY_NEWEST_WINS = 'newest_wins';
    public const STRATEGY_MANUAL = 'manual';

    /**
     * Fields that should always be resolved using server version.
     */
    private const SERVER_PRIORITY_FIELDS = [
        'id',
        'createdAt',
        'syncVersion',
        'lastSyncedAt',
    ];

    /**
     * Fields that can be automatically merged.
     */
    private const MERGEABLE_FIELDS = [
        'tags',
        'categories',
        'notes',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve conflict between server and client data.
     */
    public function resolve(array $serverData, array $clientData, string $strategy): array
    {
        $this->logger->info('Resolving conflict', [
            'strategy' => $strategy,
            'serverId' => $serverData['id'] ?? null,
            'clientId' => $clientData['clientId'] ?? null,
        ]);

        return match ($strategy) {
            self::STRATEGY_SERVER_WINS => $this->serverWins($serverData, $clientData),
            self::STRATEGY_CLIENT_WINS => $this->clientWins($serverData, $clientData),
            self::STRATEGY_MERGE => $this->merge($serverData, $clientData),
            self::STRATEGY_NEWEST_WINS => $this->newestWins($serverData, $clientData),
            self::STRATEGY_MANUAL => $this->prepareManualResolution($serverData, $clientData),
            default => throw new \InvalidArgumentException("Unknown resolution strategy: {$strategy}"),
        };
    }

    /**
     * Server wins strategy - keep server version.
     */
    private function serverWins(array $serverData, array $clientData): array
    {
        $this->logger->debug('Applying server wins strategy');

        // Keep client ID for tracking
        if (isset($clientData['clientId'])) {
            $serverData['clientId'] = $clientData['clientId'];
        }

        return $serverData;
    }

    /**
     * Client wins strategy - keep client version.
     */
    private function clientWins(array $serverData, array $clientData): array
    {
        $this->logger->debug('Applying client wins strategy');

        // Preserve server-managed fields
        foreach (self::SERVER_PRIORITY_FIELDS as $field) {
            if (isset($serverData[$field])) {
                $clientData[$field] = $serverData[$field];
            }
        }

        return $clientData;
    }

    /**
     * Merge strategy - intelligent field-level merging.
     */
    private function merge(array $serverData, array $clientData): array
    {
        $this->logger->debug('Applying merge strategy');

        $merged = [];
        $allFields = array_unique(array_merge(array_keys($serverData), array_keys($clientData)));

        foreach ($allFields as $field) {
            $merged[$field] = $this->mergeField(
                $field,
                $serverData[$field] ?? null,
                $clientData[$field] ?? null
            );
        }

        // Log merge details
        $this->logger->info('Merge completed', [
            'totalFields' => count($allFields),
            'serverFields' => count($serverData),
            'clientFields' => count($clientData),
            'mergedFields' => count($merged),
        ]);

        return $merged;
    }

    /**
     * Newest wins strategy - use most recently updated data.
     */
    private function newestWins(array $serverData, array $clientData): array
    {
        $this->logger->debug('Applying newest wins strategy');

        $serverUpdated = $this->getUpdateTime($serverData);
        $clientUpdated = $this->getUpdateTime($clientData);

        if (null === $serverUpdated && null === $clientUpdated) {
            // No update times available, fall back to server wins
            $this->logger->warning('No update times available, falling back to server wins');

            return $this->serverWins($serverData, $clientData);
        }

        if (null === $serverUpdated) {
            return $this->clientWins($serverData, $clientData);
        }

        if (null === $clientUpdated) {
            return $this->serverWins($serverData, $clientData);
        }

        // Compare timestamps
        if ($clientUpdated > $serverUpdated) {
            $this->logger->debug('Client data is newer', [
                'serverTime' => $serverUpdated->format(\DateTimeInterface::ATOM),
                'clientTime' => $clientUpdated->format(\DateTimeInterface::ATOM),
            ]);

            return $this->clientWins($serverData, $clientData);
        } else {
            $this->logger->debug('Server data is newer', [
                'serverTime' => $serverUpdated->format(\DateTimeInterface::ATOM),
                'clientTime' => $clientUpdated->format(\DateTimeInterface::ATOM),
            ]);

            return $this->serverWins($serverData, $clientData);
        }
    }

    /**
     * Prepare data for manual resolution.
     */
    private function prepareManualResolution(array $serverData, array $clientData): array
    {
        $this->logger->debug('Preparing for manual resolution');

        return [
            'requiresManualResolution' => true,
            'server' => $serverData,
            'client' => $clientData,
            'conflicts' => $this->identifyConflicts($serverData, $clientData),
            'suggestions' => $this->generateSuggestions($serverData, $clientData),
        ];
    }

    /**
     * Merge individual field based on type and rules.
     */
    private function mergeField(string $field, mixed $serverValue, mixed $clientValue): mixed
    {
        // Server-priority fields always use server value
        if (in_array($field, self::SERVER_PRIORITY_FIELDS)) {
            return $serverValue;
        }

        // If values are identical, no conflict
        if ($serverValue === $clientValue) {
            return $serverValue;
        }

        // If one is null, use the non-null value
        if (null === $serverValue) {
            return $clientValue;
        }
        if (null === $clientValue) {
            return $serverValue;
        }

        // Handle arrays (mergeable fields)
        if (in_array($field, self::MERGEABLE_FIELDS)) {
            if (is_array($serverValue) && is_array($clientValue)) {
                return array_unique(array_merge($serverValue, $clientValue));
            }
        }

        // Handle specific field types
        return $this->mergeByFieldType($field, $serverValue, $clientValue);
    }

    /**
     * Merge based on field type and business rules.
     */
    private function mergeByFieldType(string $field, mixed $serverValue, mixed $clientValue): mixed
    {
        // Handle dates - use most recent
        if (str_contains($field, 'Date') || str_contains($field, 'At')) {
            try {
                $serverDate = new \DateTime($serverValue);
                $clientDate = new \DateTime($clientValue);

                return $clientDate > $serverDate ? $clientValue : $serverValue;
            } catch (\Exception $e) {
                // If date parsing fails, prefer server value
                return $serverValue;
            }
        }

        // Handle numeric fields - use maximum (for versions, counts, etc.)
        if (is_numeric($serverValue) && is_numeric($clientValue)) {
            if (str_contains($field, 'version') || str_contains($field, 'count')) {
                return max($serverValue, $clientValue);
            }
        }

        // Handle string concatenation for notes/comments
        if (str_contains($field, 'note') || str_contains($field, 'comment')) {
            if (is_string($serverValue) && is_string($clientValue)) {
                // Combine if different
                if ($serverValue !== $clientValue) {
                    return $serverValue."\n---\n".$clientValue;
                }
            }
        }

        // Default: prefer client value (assuming client has latest user input)
        return $clientValue;
    }

    /**
     * Identify conflicting fields between server and client.
     */
    private function identifyConflicts(array $serverData, array $clientData): array
    {
        $conflicts = [];

        foreach ($serverData as $field => $serverValue) {
            if (!isset($clientData[$field])) {
                continue;
            }

            $clientValue = $clientData[$field];

            if ($serverValue !== $clientValue) {
                $conflicts[] = [
                    'field' => $field,
                    'serverValue' => $serverValue,
                    'clientValue' => $clientValue,
                    'type' => $this->determineFieldType($field, $serverValue),
                ];
            }
        }

        // Check for fields only in client
        foreach ($clientData as $field => $clientValue) {
            if (!isset($serverData[$field])) {
                $conflicts[] = [
                    'field' => $field,
                    'serverValue' => null,
                    'clientValue' => $clientValue,
                    'type' => $this->determineFieldType($field, $clientValue),
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Generate resolution suggestions based on conflict patterns.
     */
    private function generateSuggestions(array $serverData, array $clientData): array
    {
        $suggestions = [];
        $conflicts = $this->identifyConflicts($serverData, $clientData);

        foreach ($conflicts as $conflict) {
            $field = $conflict['field'];
            $suggestion = [
                'field' => $field,
                'recommendation' => $this->getFieldRecommendation($field, $conflict),
                'confidence' => $this->calculateConfidence($conflict),
            ];

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Get recommendation for specific field conflict.
     */
    private function getFieldRecommendation(string $field, array $conflict): string
    {
        // Critical fields should use server version
        if (in_array($field, ['id', 'createdAt', 'syncVersion'])) {
            return 'use_server';
        }

        // User input fields should prefer client version
        if (in_array($field, ['name', 'description', 'notes', 'phone', 'email'])) {
            return 'use_client';
        }

        // Status fields need business logic
        if (str_contains($field, 'status') || str_contains($field, 'state')) {
            return 'requires_review';
        }

        // Timestamps - use most recent
        if ('datetime' === $conflict['type']) {
            return 'use_newest';
        }

        // Arrays can be merged
        if ('array' === $conflict['type']) {
            return 'merge_values';
        }

        return 'requires_review';
    }

    /**
     * Calculate confidence level for automatic resolution.
     */
    private function calculateConfidence(array $conflict): float
    {
        $confidence = 0.5; // Base confidence

        // Higher confidence for null vs non-null
        if (null === $conflict['serverValue'] || null === $conflict['clientValue']) {
            $confidence = 0.9;
        }

        // Lower confidence for critical fields
        if (in_array($conflict['field'], ['status', 'state', 'amount', 'quantity'])) {
            $confidence *= 0.5;
        }

        // Higher confidence for metadata fields
        if (in_array($conflict['field'], self::SERVER_PRIORITY_FIELDS)) {
            $confidence = 1.0;
        }

        return min(1.0, max(0.0, $confidence));
    }

    /**
     * Determine field type for conflict resolution.
     */
    private function determineFieldType(string $field, mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_numeric($value)) {
            return str_contains($field, 'id') || str_contains($field, 'Id') ? 'id' : 'numeric';
        }

        if (is_string($value)) {
            // Check if it's a date
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return 'datetime';
            }

            // Check if it's an email
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            }

            // Check if it's a phone number
            if (preg_match('/^[\d\s\-\+\(\)]+$/', $value)) {
                return 'phone';
            }

            return 'string';
        }

        return 'unknown';
    }

    /**
     * Get update time from data.
     */
    private function getUpdateTime(array $data): ?\DateTime
    {
        $timeFields = ['updatedAt', 'modifiedAt', 'lastModified', 'lastSyncedAt'];

        foreach ($timeFields as $field) {
            if (isset($data[$field])) {
                try {
                    return new \DateTime($data[$field]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Validate merged data for consistency.
     */
    public function validateMergedData(array $mergedData): array
    {
        $errors = [];

        // Check required fields
        $requiredFields = ['id'];
        foreach ($requiredFields as $field) {
            if (!isset($mergedData[$field]) || null === $mergedData[$field]) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }

        // Validate data types
        if (isset($mergedData['email']) && !filter_var($mergedData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Check for data consistency
        if (isset($mergedData['startDate']) && isset($mergedData['endDate'])) {
            try {
                $start = new \DateTime($mergedData['startDate']);
                $end = new \DateTime($mergedData['endDate']);
                if ($start > $end) {
                    $errors[] = 'Start date cannot be after end date';
                }
            } catch (\Exception $e) {
                $errors[] = 'Invalid date format';
            }
        }

        return $errors;
    }
}

