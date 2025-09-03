<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Message\SyncQueueMessage;
use App\Repository\CommentRepository;
use App\Repository\ContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OfflineSyncService handles bidirectional synchronization between client and server.
 * Manages data conflicts, queuing, and retry logic for offline mode.
 */
class OfflineSyncService
{
    public const SYNC_BATCH_SIZE = 100;
    public const MAX_RETRY_ATTEMPTS = 3;
    public const RETRY_DELAY_SECONDS = [5, 15, 60]; // Progressive delays

    private ConflictResolutionService $conflictResolver;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly CustomerRepository $customerRepository,
        private readonly EnergyRepository $energyRepository,
        private readonly ContactRepository $contactRepository,
        private readonly CommentRepository $commentRepository,
    ) {
        // ConflictResolutionService will be injected via setter to avoid circular dependency
    }

    public function setConflictResolver(ConflictResolutionService $conflictResolver): void
    {
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * Perform full synchronization between client and server.
     */
    public function performFullSync(array $clientData): array
    {
        $this->logger->info('Starting full sync operation');

        $results = [
            'success' => true,
            'pushed' => [],
            'pulled' => [],
            'conflicts' => [],
            'errors' => [],
        ];

        try {
            $this->entityManager->beginTransaction();

            // Step 1: Push client changes to server
            if (!empty($clientData['changes'])) {
                $pushResults = $this->pushChanges($clientData['changes']);
                $results['pushed'] = $pushResults['processed'];
                $results['conflicts'] = array_merge($results['conflicts'], $pushResults['conflicts']);
                $results['errors'] = array_merge($results['errors'], $pushResults['errors']);
            }

            // Step 2: Pull server changes to client
            $lastSync = $clientData['lastSync'] ?? null;
            $pullResults = $this->pullChanges($lastSync);
            $results['pulled'] = $pullResults;

            // Step 3: Handle conflict resolution if needed
            if (!empty($results['conflicts'])) {
                $resolvedConflicts = $this->resolveConflicts($results['conflicts'], $clientData['conflictStrategy'] ?? 'server_wins');
                $results['resolved'] = $resolvedConflicts;
            }

            $this->entityManager->commit();

            $results['syncTime'] = (new \DateTime())->format(\DateTimeInterface::ATOM);
            $this->logger->info('Full sync completed successfully', ['results' => $results]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Full sync failed', ['error' => $e->getMessage()]);

            $results['success'] = false;
            $results['errors'][] = [
                'message' => 'Sync operation failed',
                'detail' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Push client changes to server.
     */
    public function pushChanges(array $changes): array
    {
        $processed = [];
        $conflicts = [];
        $errors = [];

        foreach ($changes as $change) {
            try {
                $result = $this->processChange($change);

                if ('conflict' === $result['status']) {
                    $conflicts[] = $result;
                } else {
                    $processed[] = $result;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'change' => $change,
                    'error' => $e->getMessage(),
                ];

                $this->logger->error('Failed to process change', [
                    'change' => $change,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'conflicts' => $conflicts,
            'errors' => $errors,
        ];
    }

    /**
     * Pull server changes to client.
     */
    public function pullChanges(?string $lastSync): array
    {
        $entities = [];
        $limit = self::SYNC_BATCH_SIZE;

        // Determine last sync timestamp
        $lastSyncDate = null;
        if ($lastSync) {
            try {
                $lastSyncDate = new \DateTime($lastSync);
            } catch (\Exception $e) {
                $this->logger->warning('Invalid lastSync timestamp', ['lastSync' => $lastSync]);
            }
        }

        // Pull customers
        $customers = $this->getModifiedEntities($this->customerRepository, $lastSyncDate, $limit);
        $entities['customers'] = $this->serializeEntities($customers);

        // Pull energies
        $energies = $this->getModifiedEntities($this->energyRepository, $lastSyncDate, $limit);
        $entities['energies'] = $this->serializeEntities($energies);

        // Pull contacts
        $contacts = $this->getModifiedEntities($this->contactRepository, $lastSyncDate, $limit);
        $entities['contacts'] = $this->serializeEntities($contacts);

        // Pull comments
        $comments = $this->getModifiedEntities($this->commentRepository, $lastSyncDate, $limit);
        $entities['comments'] = $this->serializeEntities($comments);

        return $entities;
    }

    /**
     * Process a single change from the client.
     */
    private function processChange(array $change): array
    {
        $operation = $change['operation'];
        $entityType = $change['entity'];
        $data = $change['data'];
        $clientVersion = $data['version'] ?? 1;

        switch ($operation) {
            case 'create':
                return $this->handleCreate($entityType, $data);

            case 'update':
                return $this->handleUpdate($entityType, $data, $clientVersion);

            case 'delete':
                return $this->handleDelete($entityType, $data['id']);

            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }

    /**
     * Handle entity creation.
     */
    private function handleCreate(string $entityType, array $data): array
    {
        $entityClass = $this->getEntityClass($entityType);
        $entity = new $entityClass();

        $this->populateEntity($entity, $data);

        // Set sync metadata
        if (method_exists($entity, 'setClientId') && isset($data['clientId'])) {
            $entity->setClientId($data['clientId']);
        }

        if (method_exists($entity, 'setSyncVersion')) {
            $entity->setSyncVersion(1);
        }

        if (method_exists($entity, 'setLastSyncedAt')) {
            $entity->setLastSyncedAt(new \DateTime());
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return [
            'status' => 'created',
            'operation' => 'create',
            'entity' => $entityType,
            'id' => $entity->getId(),
            'clientId' => $data['clientId'] ?? null,
        ];
    }

    /**
     * Handle entity update with conflict detection.
     */
    private function handleUpdate(string $entityType, array $data, int $clientVersion): array
    {
        $entity = $this->getEntity($entityType, $data['id']);

        if (!$entity) {
            throw new \RuntimeException("Entity {$entityType} with ID {$data['id']} not found");
        }

        // Check for version conflict
        $serverVersion = method_exists($entity, 'getSyncVersion') ? $entity->getSyncVersion() : 1;

        if ($serverVersion > $clientVersion) {
            // Conflict detected
            return [
                'status' => 'conflict',
                'operation' => 'update',
                'entity' => $entityType,
                'id' => $entity->getId(),
                'clientVersion' => $clientVersion,
                'serverVersion' => $serverVersion,
                'clientData' => $data,
                'serverData' => $this->serializeEntity($entity),
            ];
        }

        // No conflict, apply update
        $this->populateEntity($entity, $data);

        if (method_exists($entity, 'setSyncVersion')) {
            $entity->setSyncVersion($serverVersion + 1);
        }

        if (method_exists($entity, 'setLastSyncedAt')) {
            $entity->setLastSyncedAt(new \DateTime());
        }

        $this->entityManager->flush();

        return [
            'status' => 'updated',
            'operation' => 'update',
            'entity' => $entityType,
            'id' => $entity->getId(),
            'version' => $serverVersion + 1,
        ];
    }

    /**
     * Handle entity deletion.
     */
    private function handleDelete(string $entityType, int $id): array
    {
        $entity = $this->getEntity($entityType, $id);

        if (!$entity) {
            // Already deleted
            return [
                'status' => 'already_deleted',
                'operation' => 'delete',
                'entity' => $entityType,
                'id' => $id,
            ];
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return [
            'status' => 'deleted',
            'operation' => 'delete',
            'entity' => $entityType,
            'id' => $id,
        ];
    }

    /**
     * Resolve conflicts using the specified strategy.
     */
    private function resolveConflicts(array $conflicts, string $strategy): array
    {
        $resolved = [];

        foreach ($conflicts as $conflict) {
            $resolution = $this->conflictResolver->resolve(
                $conflict['serverData'],
                $conflict['clientData'],
                $strategy
            );

            $entity = $this->getEntity($conflict['entity'], $conflict['id']);
            $this->populateEntity($entity, $resolution);

            if (method_exists($entity, 'setSyncVersion')) {
                $entity->setSyncVersion($conflict['serverVersion'] + 1);
            }

            if (method_exists($entity, 'setLastSyncedAt')) {
                $entity->setLastSyncedAt(new \DateTime());
            }

            $this->entityManager->flush();

            $resolved[] = [
                'entity' => $conflict['entity'],
                'id' => $conflict['id'],
                'strategy' => $strategy,
                'resolution' => $resolution,
            ];
        }

        return $resolved;
    }

    /**
     * Queue sync operation for asynchronous processing.
     */
    public function queueSync(array $syncData, int $priority = 0): void
    {
        $message = new SyncQueueMessage(
            $syncData,
            $priority,
            0,
            new \DateTime()
        );

        $this->messageBus->dispatch($message);
        $this->logger->info('Sync operation queued', ['priority' => $priority]);
    }

    /**
     * Process queued sync operations.
     */
    public function processQueuedSync(SyncQueueMessage $message): bool
    {
        $syncData = $message->getSyncData();
        $attempts = $message->getAttemptCount();

        try {
            $this->performFullSync($syncData);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Queued sync failed', [
                'attempt' => $attempts + 1,
                'error' => $e->getMessage(),
            ]);

            // Check if we should retry
            if ($attempts < self::MAX_RETRY_ATTEMPTS - 1) {
                $delay = self::RETRY_DELAY_SECONDS[$attempts];
                $message->incrementAttempt();

                // Re-queue with delay
                $this->messageBus->dispatch($message, [
                    'delay' => $delay * 1000, // Convert to milliseconds
                ]);

                $this->logger->info('Sync re-queued for retry', [
                    'attempt' => $attempts + 1,
                    'delay' => $delay,
                ]);
            } else {
                $this->logger->critical('Sync failed after maximum retries', [
                    'syncData' => $syncData,
                    'attempts' => $attempts + 1,
                ]);
            }

            return false;
        }
    }

    /**
     * Get modified entities since last sync.
     */
    private function getModifiedEntities($repository, ?\DateTime $since, int $limit): array
    {
        if (method_exists($repository, 'findModifiedSince')) {
            return $repository->findModifiedSince($since, $limit);
        }

        // Fallback to regular find if custom method doesn't exist
        return $repository->findBy([], ['id' => 'DESC'], $limit);
    }

    /**
     * Serialize multiple entities.
     */
    private function serializeEntities(array $entities): array
    {
        return array_map([$this, 'serializeEntity'], $entities);
    }

    /**
     * Serialize a single entity.
     */
    private function serializeEntity($entity): array
    {
        $data = [
            'id' => $entity->getId(),
        ];

        // Add sync metadata if available
        if (method_exists($entity, 'getSyncVersion')) {
            $data['version'] = $entity->getSyncVersion();
        }

        if (method_exists($entity, 'getLastSyncedAt')) {
            $lastSync = $entity->getLastSyncedAt();
            $data['lastSyncedAt'] = $lastSync ? $lastSync->format(\DateTimeInterface::ATOM) : null;
        }

        if (method_exists($entity, 'getClientId')) {
            $data['clientId'] = $entity->getClientId();
        }

        // Add entity-specific fields
        $data = array_merge($data, $this->getEntityFields($entity));

        return $data;
    }

    /**
     * Get entity-specific fields for serialization.
     */
    private function getEntityFields($entity): array
    {
        $fields = [];

        if ($entity instanceof Customer) {
            $fields['name'] = $entity->getName();
            $fields['siret'] = $entity->getSiret();
            $fields['address'] = $entity->getAddress();
            $fields['city'] = $entity->getCity();
            $fields['postalCode'] = $entity->getPostalCode();
            $fields['phone'] = $entity->getPhone();
            $fields['email'] = $entity->getEmail();
            $fields['status'] = $entity->getStatus();
        } elseif ($entity instanceof Energy) {
            $fields['customerId'] = $entity->getCustomer()?->getId();
            $fields['code'] = $entity->getCode();
            $fields['type'] = $entity->getType();
            $fields['provider'] = $entity->getProvider();
            $fields['contractNumber'] = $entity->getContractNumber();
            $fields['startDate'] = $entity->getStartDate()?->format('Y-m-d');
            $fields['endDate'] = $entity->getEndDate()?->format('Y-m-d');
        } elseif ($entity instanceof Contact) {
            $fields['customerId'] = $entity->getCustomer()?->getId();
            $fields['firstName'] = $entity->getFirstName();
            $fields['lastName'] = $entity->getLastName();
            $fields['email'] = $entity->getEmail();
            $fields['phone'] = $entity->getPhone();
            $fields['position'] = $entity->getPosition();
            $fields['isPrimary'] = $entity->isPrimary();
        } elseif ($entity instanceof Comment) {
            $fields['customerId'] = $entity->getCustomer()?->getId();
            $fields['content'] = $entity->getContent();
            $fields['type'] = $entity->getType();
            $fields['createdAt'] = $entity->getCreatedAt()?->format(\DateTimeInterface::ATOM);
            $fields['author'] = $entity->getAuthor();
        }

        return $fields;
    }

    /**
     * Populate entity with data.
     */
    private function populateEntity($entity, array $data): void
    {
        if ($entity instanceof Customer) {
            if (isset($data['name'])) {
                $entity->setName($data['name']);
            }
            if (isset($data['siret'])) {
                $entity->setSiret($data['siret']);
            }
            if (isset($data['address'])) {
                $entity->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $entity->setCity($data['city']);
            }
            if (isset($data['postalCode'])) {
                $entity->setPostalCode($data['postalCode']);
            }
            if (isset($data['phone'])) {
                $entity->setPhone($data['phone']);
            }
            if (isset($data['email'])) {
                $entity->setEmail($data['email']);
            }
            if (isset($data['status'])) {
                $entity->setStatus($data['status']);
            }
        } elseif ($entity instanceof Energy) {
            if (isset($data['customerId'])) {
                $customer = $this->customerRepository->find($data['customerId']);
                $entity->setCustomer($customer);
            }
            if (isset($data['code'])) {
                $entity->setCode($data['code']);
            }
            if (isset($data['type'])) {
                $entity->setType($data['type']);
            }
            if (isset($data['provider'])) {
                $entity->setProvider($data['provider']);
            }
            if (isset($data['contractNumber'])) {
                $entity->setContractNumber($data['contractNumber']);
            }
            if (isset($data['startDate'])) {
                $entity->setStartDate(new \DateTime($data['startDate']));
            }
            if (isset($data['endDate'])) {
                $entity->setEndDate(new \DateTime($data['endDate']));
            }
        } elseif ($entity instanceof Contact) {
            if (isset($data['customerId'])) {
                $customer = $this->customerRepository->find($data['customerId']);
                $entity->setCustomer($customer);
            }
            if (isset($data['firstName'])) {
                $entity->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $entity->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $entity->setEmail($data['email']);
            }
            if (isset($data['phone'])) {
                $entity->setPhone($data['phone']);
            }
            if (isset($data['position'])) {
                $entity->setPosition($data['position']);
            }
            if (isset($data['isPrimary'])) {
                $entity->setIsPrimary($data['isPrimary']);
            }
        } elseif ($entity instanceof Comment) {
            if (isset($data['customerId'])) {
                $customer = $this->customerRepository->find($data['customerId']);
                $entity->setCustomer($customer);
            }
            if (isset($data['content'])) {
                $entity->setContent($data['content']);
            }
            if (isset($data['type'])) {
                $entity->setType($data['type']);
            }
            if (isset($data['author'])) {
                $entity->setAuthor($data['author']);
            }
        }
    }

    /**
     * Get repository for entity type.
     */
    private function getRepository(string $entityType)
    {
        return match ($entityType) {
            'customers' => $this->customerRepository,
            'energies' => $this->energyRepository,
            'contacts' => $this->contactRepository,
            'comments' => $this->commentRepository,
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get entity class name.
     */
    private function getEntityClass(string $entityType): string
    {
        return match ($entityType) {
            'customers' => Customer::class,
            'energies' => Energy::class,
            'contacts' => Contact::class,
            'comments' => Comment::class,
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get entity by type and ID.
     */
    private function getEntity(string $entityType, int $id)
    {
        return $this->getRepository($entityType)->find($id);
    }
}

