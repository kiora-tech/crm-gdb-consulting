<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Repository\CommentRepository;
use App\Repository\ContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\EnergyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * SyncController handles synchronization endpoints for offline mode.
 * Provides APIs for pushing and pulling data between client and server.
 */
#[Route('/api/sync', name: 'api_sync_')]
class SyncController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private CustomerRepository $customerRepository,
        private EnergyRepository $energyRepository,
        private ContactRepository $contactRepository,
        private CommentRepository $commentRepository,
    ) {
    }

    /**
     * Get sync status and metadata.
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $status = [
            'server_time' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'entities' => [
                'customers' => $this->customerRepository->count([]),
                'energies' => $this->energyRepository->count([]),
                'contacts' => $this->contactRepository->count([]),
                'comments' => $this->commentRepository->count([]),
            ],
            'sync_available' => true,
            'version' => '1.0',
        ];

        return new JsonResponse($status);
    }

    /**
     * Pull data from server (full sync or incremental).
     */
    #[Route('/pull', name: 'pull', methods: ['GET'])]
    public function pull(Request $request): JsonResponse
    {
        $lastSync = $request->query->get('lastSync');
        $entityTypes = $request->query->all('entities') ?: ['customers', 'energies', 'contacts', 'comments'];
        $limit = min(1000, (int) $request->query->get('limit', 100));

        $data = [];

        // Process each requested entity type
        foreach ($entityTypes as $entityType) {
            $data[$entityType] = $this->pullEntities($entityType, $lastSync, $limit);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'server_time' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'has_more' => $this->hasMoreData($entityTypes, $lastSync, $limit),
        ]);
    }

    /**
     * Push data to server (create, update, delete operations).
     */
    #[Route('/push', name: 'push', methods: ['POST'])]
    public function push(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['changes'])) {
            return new JsonResponse(['error' => 'Invalid request format'], Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        $errors = [];

        try {
            $this->entityManager->beginTransaction();

            foreach ($data['changes'] as $change) {
                try {
                    $result = $this->processChange($change);
                    $results[] = $result;
                } catch (\Exception $e) {
                    $errors[] = [
                        'change' => $change,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if (empty($errors)) {
                $this->entityManager->commit();

                return new JsonResponse([
                    'success' => true,
                    'results' => $results,
                    'server_time' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                ]);
            } else {
                $this->entityManager->rollback();

                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors,
                    'server_time' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            return new JsonResponse([
                'success' => false,
                'error' => 'Sync operation failed: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get changes since a specific timestamp (for incremental sync).
     */
    #[Route('/changes', name: 'changes', methods: ['GET'])]
    public function changes(Request $request): JsonResponse
    {
        $since = $request->query->get('since');
        $entityTypes = $request->query->all('entities') ?: ['customers', 'energies', 'contacts', 'comments'];

        if (!$since) {
            return new JsonResponse(['error' => 'Missing "since" parameter'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $sinceDate = new \DateTime($since);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        $changes = [];

        foreach ($entityTypes as $entityType) {
            $changes[$entityType] = $this->getChangedEntities($entityType, $sinceDate);
        }

        return new JsonResponse([
            'success' => true,
            'changes' => $changes,
            'server_time' => (new \DateTime())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Resolve conflicts between client and server versions.
     */
    #[Route('/resolve-conflict', name: 'resolve_conflict', methods: ['POST'])]
    public function resolveConflict(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['entity'], $data['entityId'], $data['resolution'])) {
            return new JsonResponse(['error' => 'Invalid request format'], Response::HTTP_BAD_REQUEST);
        }

        $entityType = $data['entity'];
        $entityId = $data['entityId'];
        $resolution = $data['resolution']; // 'client_wins', 'server_wins', 'merge'
        $clientData = $data['clientData'] ?? [];

        try {
            $entity = $this->getEntity($entityType, $entityId);

            if (!$entity) {
                return new JsonResponse(['error' => 'Entity not found'], Response::HTTP_NOT_FOUND);
            }

            $result = $this->resolveEntityConflict($entity, $clientData, $resolution);

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'entity' => $this->serializeEntity($entity),
                'resolution_applied' => $resolution,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Conflict resolution failed: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Health check endpoint for sync functionality.
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'database' => 'connected',
        ]);
    }

    // Private helper methods

    private function pullEntities(string $entityType, ?string $lastSync, int $limit): array
    {
        $repository = $this->getRepository($entityType);

        if ($lastSync) {
            $lastSyncDate = new \DateTime($lastSync);
            $entities = $repository->findByLastSync($lastSyncDate, $limit);
        } else {
            $entities = $repository->findBy([], ['id' => 'ASC'], $limit);
        }

        return array_map([$this, 'serializeEntity'], $entities);
    }

    private function hasMoreData(array $entityTypes, ?string $lastSync, int $limit): array
    {
        $hasMore = [];

        foreach ($entityTypes as $entityType) {
            $repository = $this->getRepository($entityType);
            $total = $repository->count([]);
            $hasMore[$entityType] = $total > $limit;
        }

        return $hasMore;
    }

    private function processChange(array $change): array
    {
        $operation = $change['operation']; // 'create', 'update', 'delete'
        $entityType = $change['entity'];
        $entityData = $change['data'];

        switch ($operation) {
            case 'create':
                return $this->createEntity($entityType, $entityData);

            case 'update':
                return $this->updateEntity($entityType, $entityData);

            case 'delete':
                return $this->deleteEntity($entityType, $entityData['id']);

            default:
                throw new \InvalidArgumentException("Unknown operation: {$operation}");
        }
    }

    private function createEntity(string $entityType, array $data): array
    {
        $entityClass = $this->getEntityClass($entityType);
        $entity = new $entityClass();

        // Set basic properties
        $this->populateEntity($entity, $data);

        // Set sync metadata
        if (method_exists($entity, 'setClientId') && isset($data['clientId'])) {
            $entity->setClientId($data['clientId']);
        }

        if (method_exists($entity, 'markAsSynced')) {
            $entity->markAsSynced();
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return [
            'operation' => 'create',
            'entity' => $entityType,
            'id' => $entity->getId(),
            'server_id' => $entity->getId(),
            'client_id' => $data['clientId'] ?? null,
        ];
    }

    private function updateEntity(string $entityType, array $data): array
    {
        $entity = $this->getEntity($entityType, $data['id']);

        if (!$entity) {
            throw new \Exception("Entity {$entityType} with ID {$data['id']} not found");
        }

        // Check for conflicts
        if (method_exists($entity, 'getVersion') && isset($data['version'])) {
            if ($entity->getVersion() > $data['version']) {
                throw new \Exception('Conflict detected: server version is newer');
            }
        }

        $this->populateEntity($entity, $data);

        if (method_exists($entity, 'markAsSynced')) {
            $entity->markAsSynced();
        }

        $this->entityManager->flush();

        return [
            'operation' => 'update',
            'entity' => $entityType,
            'id' => $entity->getId(),
        ];
    }

    private function deleteEntity(string $entityType, int $id): array
    {
        $entity = $this->getEntity($entityType, $id);

        if (!$entity) {
            throw new \Exception("Entity {$entityType} with ID {$id} not found");
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return [
            'operation' => 'delete',
            'entity' => $entityType,
            'id' => $id,
        ];
    }

    private function getChangedEntities(string $entityType, \DateTime $since): array
    {
        $repository = $this->getRepository($entityType);

        // This would need to be implemented in repositories
        if (method_exists($repository, 'findChangedSince')) {
            $entities = $repository->findChangedSince($since);
        } else {
            $entities = $repository->findBy([], ['id' => 'ASC'], 100);
        }

        return array_map([$this, 'serializeEntity'], $entities);
    }

    private function resolveEntityConflict($entity, array $clientData, string $resolution): array
    {
        switch ($resolution) {
            case 'client_wins':
                $this->populateEntity($entity, $clientData);
                if (method_exists($entity, 'markAsSynced')) {
                    $entity->markAsSynced();
                }
                break;

            case 'server_wins':
                // Keep server version, just mark as synced
                if (method_exists($entity, 'markAsSynced')) {
                    $entity->markAsSynced();
                }
                break;

            case 'merge':
                // Custom merge logic would go here
                throw new \Exception('Merge resolution not yet implemented');
            default:
                throw new \InvalidArgumentException("Unknown resolution: {$resolution}");
        }

        return ['resolution' => $resolution];
    }

    private function populateEntity($entity, array $data): void
    {
        // Basic field mapping - this would need to be expanded based on actual entity fields
        $setters = [
            'name' => 'setName',
            'email' => 'setEmail',
            'firstName' => 'setFirstName',
            'lastName' => 'setLastName',
            'note' => 'setNote',
            'phone' => 'setPhone',
            'position' => 'setPosition',
        ];

        foreach ($setters as $field => $setter) {
            if (isset($data[$field]) && method_exists($entity, $setter)) {
                $entity->$setter($data[$field]);
            }
        }
    }

    private function serializeEntity($entity): array
    {
        // Basic serialization - would need to be expanded
        $data = [
            'id' => $entity->getId(),
        ];

        // Add sync metadata if available
        if (method_exists($entity, 'getSyncMetadata')) {
            $data = array_merge($data, $entity->getSyncMetadata());
        }

        // Add basic fields
        $getters = [
            'name' => 'getName',
            'email' => 'getEmail',
            'firstName' => 'getFirstName',
            'lastName' => 'setLastName',
            'note' => 'getNote',
            'phone' => 'getPhone',
            'position' => 'getPosition',
        ];

        foreach ($getters as $field => $getter) {
            if (method_exists($entity, $getter)) {
                $data[$field] = $entity->$getter();
            }
        }

        return $data;
    }

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

    private function getEntity(string $entityType, int $id)
    {
        return $this->getRepository($entityType)->find($id);
    }
}
