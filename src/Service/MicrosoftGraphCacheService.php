<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Message\RefreshMicrosoftCalendarsMessage;
use App\Message\RefreshMicrosoftCategoriesMessage;
use App\Message\RefreshMicrosoftEventsMessage;
use App\Message\RefreshMicrosoftTasksMessage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MicrosoftGraphCacheService
{
    // TTL constants
    private const int TTL_CALENDARS = 3600; // 1 hour
    private const int TTL_CATEGORIES = 3600; // 1 hour
    private const int TTL_EVENTS = 300; // 5 minutes
    private const int TTL_TASKS = 180; // 3 minutes
    // private const int TTL_SINGLE_EVENT = 120; // 2 minutes - unused, kept for future use

    // Refresh threshold (déclencher refresh async si cache plus vieux que ça)
    private const int REFRESH_THRESHOLD_CALENDARS = 1800; // 30 min
    private const int REFRESH_THRESHOLD_CATEGORIES = 1800; // 30 min
    private const int REFRESH_THRESHOLD_EVENTS = 120; // 2 min
    private const int REFRESH_THRESHOLD_TASKS = 90; // 1.5 min

    public function __construct(
        private readonly CacheItemPoolInterface $microsoftGraphCache,
        private readonly MessageBusInterface $messageBus,
        private readonly MicrosoftGraphService $microsoftGraphService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get calendars from cache or API.
     * Always returns cache immediately, dispatches async refresh if needed.
     *
     * @return array<int, array{id: string, name: string, canEdit: bool, isDefaultCalendar: bool}>
     */
    public function getUserCalendars(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = $this->getCacheKey($user->getId(), 'calendars');

        if ($forceRefresh) {
            $this->microsoftGraphCache->deleteItem($cacheKey);
        }

        try {
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);

            // Si on a du cache, le retourner immédiatement
            if ($cacheItem->isHit()) {
                $cachedData = $cacheItem->get();
                $age = time() - ($cachedData['timestamp'] ?? 0);

                // Dispatch async refresh si cache "vieux"
                if ($age > self::REFRESH_THRESHOLD_CALENDARS) {
                    $this->logger->info('Cache hit but stale, dispatching async refresh', [
                        'user_id' => $user->getId(),
                        'resource' => 'calendars',
                        'age' => $age,
                    ]);
                    $this->messageBus->dispatch(new RefreshMicrosoftCalendarsMessage($user->getId()));
                }

                return $cachedData['data'];
            }

            // Pas de cache: fetch synchrone (première fois seulement)
            $this->logger->info('Cache miss, fetching synchronously', [
                'user_id' => $user->getId(),
                'resource' => 'calendars',
            ]);

            $data = $this->microsoftGraphService->getUserCalendars($user);
            $this->storeCachedData($cacheKey, $data, self::TTL_CALENDARS);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching calendars', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            // Retourner tableau vide en cas d'erreur
            return [];
        }
    }

    /**
     * Get categories from cache or API.
     *
     * @return array<int, array{displayName: string, color: string}>
     */
    public function getUserCategories(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = $this->getCacheKey($user->getId(), 'categories');

        if ($forceRefresh) {
            $this->microsoftGraphCache->deleteItem($cacheKey);
        }

        try {
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $cachedData = $cacheItem->get();
                $age = time() - ($cachedData['timestamp'] ?? 0);

                if ($age > self::REFRESH_THRESHOLD_CATEGORIES) {
                    $this->messageBus->dispatch(new RefreshMicrosoftCategoriesMessage($user->getId()));
                }

                return $cachedData['data'];
            }

            $data = $this->microsoftGraphService->getUserCategories($user);
            $this->storeCachedData($cacheKey, $data, self::TTL_CATEGORIES);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching categories', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get calendar events from cache or API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarEvents(
        User $user,
        string $calendarId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        bool $forceRefresh = false,
    ): array {
        $cacheKey = $this->getCacheKey(
            $user->getId(),
            'events',
            $calendarId.':'.$startDate->format('Y-m-d').':'.$endDate->format('Y-m-d')
        );

        if ($forceRefresh) {
            $this->microsoftGraphCache->deleteItem($cacheKey);
        }

        try {
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $cachedData = $cacheItem->get();
                $age = time() - ($cachedData['timestamp'] ?? 0);

                if ($age > self::REFRESH_THRESHOLD_EVENTS) {
                    $this->messageBus->dispatch(new RefreshMicrosoftEventsMessage(
                        $user->getId(),
                        $calendarId,
                        $startDate,
                        $endDate
                    ));
                }

                return $cachedData['data'];
            }

            // Fetch directly via MicrosoftGraphService
            $data = $this->fetchCalendarEventsFromApi($user, $calendarId, $startDate, $endDate);
            $this->storeCachedData($cacheKey, $data, self::TTL_EVENTS);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching calendar events', [
                'user_id' => $user->getId(),
                'calendar_id' => $calendarId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get tasks from cache or API.
     *
     * @return array<int, array{id: string, title: string, status: string, importance: string, createdDateTime: string, dueDateTime: string|null, listName: string, listId: string, completedDateTime: string|null, body: string}>
     */
    public function getUserTasks(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = $this->getCacheKey($user->getId(), 'tasks');

        if ($forceRefresh) {
            $this->microsoftGraphCache->deleteItem($cacheKey);
        }

        try {
            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $cachedData = $cacheItem->get();
                $age = time() - ($cachedData['timestamp'] ?? 0);

                if ($age > self::REFRESH_THRESHOLD_TASKS) {
                    $this->messageBus->dispatch(new RefreshMicrosoftTasksMessage($user->getId()));
                }

                return $cachedData['data'];
            }

            $data = $this->microsoftGraphService->getUserTasks($user);
            $this->storeCachedData($cacheKey, $data, self::TTL_TASKS);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching tasks', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Invalidate cache after user modifications.
     */
    public function invalidateEventCache(User $user, string $calendarId): void
    {
        // Invalider tous les caches d'événements pour ce calendrier
        // Note: en production, utiliser un tag-based cache pour plus d'efficacité
        $pattern = sprintf('ms_graph:%d:events:%s:*', $user->getId(), $calendarId);

        $this->logger->info('Invalidating event cache', [
            'user_id' => $user->getId(),
            'calendar_id' => $calendarId,
            'pattern' => $pattern,
        ]);

        // Pour simplification, on clear tout le cache user
        // En production: utiliser cache tags (Redis SCAN + DELETE)
        $this->microsoftGraphCache->clear();
    }

    /**
     * Invalidate cache after category modification.
     */
    public function invalidateCategoryCache(User $user): void
    {
        $cacheKey = $this->getCacheKey($user->getId(), 'categories');
        $this->microsoftGraphCache->deleteItem($cacheKey);
    }

    /**
     * Invalidate cache after task modification.
     */
    public function invalidateTaskCache(User $user): void
    {
        $cacheKey = $this->getCacheKey($user->getId(), 'tasks');
        $this->microsoftGraphCache->deleteItem($cacheKey);
    }

    /**
     * Fetch calendar events from Microsoft Graph API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchCalendarEventsFromApi(
        User $user,
        string $calendarId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): array {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            // Token refresh is handled in MicrosoftGraphService
        }

        $url = sprintf(
            'https://graph.microsoft.com/v1.0/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$orderby=start/dateTime&$top=100',
            $calendarId,
            $startDate->format('Y-m-d\TH:i:s'),
            $endDate->format('Y-m-d\TH:i:s')
        );

        $httpClient = \Symfony\Component\HttpClient\HttpClient::create();

        $response = $httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token->getAccessToken(),
                'Content-Type' => 'application/json',
                'Prefer' => 'outlook.timezone="'.$user->getTimezone().'"',
            ],
            'timeout' => 30,
        ]);

        $data = json_decode($response->getContent(), true);

        return $data['value'] ?? [];
    }

    /**
     * Store data in cache with timestamp.
     */
    private function storeCachedData(string $cacheKey, mixed $data, int $ttl): void
    {
        $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);
        $cacheItem->set([
            'data' => $data,
            'timestamp' => time(),
        ]);
        $cacheItem->expiresAfter($ttl);

        $this->microsoftGraphCache->save($cacheItem);
    }

    /**
     * Generate cache key.
     */
    private function getCacheKey(int $userId, string $resourceType, ?string $optionalParam = null): string
    {
        $key = sprintf('ms_graph:%d:%s', $userId, $resourceType);

        if ($optionalParam) {
            $key .= ':'.$optionalParam;
        }

        return $key;
    }
}
