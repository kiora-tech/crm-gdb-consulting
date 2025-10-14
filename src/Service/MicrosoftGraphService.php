<?php

namespace App\Service;

use App\Entity\MicrosoftToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MicrosoftGraphService
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
        $this->httpClient = HttpClient::create();
    }

    /**
     * @return array<int, array{id: string, title: string, status: string, importance: string, createdDateTime: string, dueDateTime: string|null, listName: string, listId: string, completedDateTime: string|null, body: string}>
     */
    public function getUserTasks(User $user): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/todo/lists', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (401 === $statusCode) {
                $this->logger->warning('Received 401 in getUserTasks, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $response = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/todo/lists', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                ]);
            }

            $lists = json_decode($response->getContent(), true);
            $allTasks = [];

            foreach ($lists['value'] ?? [] as $list) {
                $tasksResponse = $this->httpClient->request('GET', "https://graph.microsoft.com/v1.0/me/todo/lists/{$list['id']}/tasks", [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $tasks = json_decode($tasksResponse->getContent(), true);

                foreach ($tasks['value'] ?? [] as $task) {
                    $allTasks[] = [
                        'id' => $task['id'],
                        'title' => $task['title'],
                        'status' => $task['status'],
                        'importance' => $task['importance'] ?? 'normal',
                        'createdDateTime' => $task['createdDateTime'],
                        'dueDateTime' => $task['dueDateTime']['dateTime'] ?? null,
                        'listName' => $list['displayName'],
                        'listId' => $list['id'],
                        'completedDateTime' => $task['completedDateTime']['dateTime'] ?? null,
                        'body' => $task['body']['content'] ?? '',
                    ];
                }
            }

            return $allTasks;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Microsoft tasks', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to fetch Microsoft tasks: '.$e->getMessage());
        }
    }

    /**
     * Make an HTTP request with retry logic and error handling.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @phpstan-ignore method.unused (Will be used in future refactoring of HTTP methods)
     */
    private function makeGraphRequest(string $method, string $url, MicrosoftToken $token, array $options = [], int $retries = 3): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $retries) {
            try {
                $options['headers'] = array_merge($options['headers'] ?? [], [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ]);

                $options['timeout'] = 30; // 30 second timeout

                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                // Handle 401: token expired, refresh and retry once
                if (401 === $statusCode && 0 === $attempt) {
                    $this->logger->warning('Token expired, refreshing', ['url' => $url]);
                    $token = $this->refreshToken($token);
                    ++$attempt;
                    continue;
                }

                // Handle 429: rate limit, wait and retry
                if (429 === $statusCode) {
                    $retryAfter = (int) ($response->getHeaders()['retry-after'][0] ?? 5);
                    $this->logger->warning('Rate limit hit, waiting', [
                        'url' => $url,
                        'retry_after' => $retryAfter,
                    ]);
                    sleep(min($retryAfter, 60)); // Max 60 seconds wait
                    ++$attempt;
                    continue;
                }

                // Handle 404: resource not found
                if (404 === $statusCode) {
                    return []; // Return empty array for not found
                }

                // Handle server errors: 500, 502, 503
                if ($statusCode >= 500 && $statusCode < 600) {
                    $this->logger->error('Microsoft API server error', [
                        'url' => $url,
                        'status_code' => $statusCode,
                    ]);
                    ++$attempt;
                    sleep(min($attempt * 2, 10)); // Exponential backoff
                    continue;
                }

                // Success: parse and return JSON
                $content = $response->getContent();

                return json_decode($content, true) ?? [];
            } catch (\Exception $e) {
                $lastException = $e;
                // Don't log 404 errors as they're expected for missing resources
                if (!str_contains($e->getMessage(), '404')) {
                    $this->logger->error('Network error making Graph request', [
                        'url' => $url,
                        'attempt' => $attempt + 1,
                        'error' => $e->getMessage(),
                    ]);
                }
                ++$attempt;
                if ($attempt < $retries) {
                    sleep(min($attempt * 2, 10)); // Exponential backoff
                }
            }
        }

        // All retries failed
        $message = sprintf('Failed to make Graph API request after %d attempts: %s', $retries, $lastException?->getMessage() ?? 'Unknown error');
        throw new \RuntimeException($message);
    }

    private function refreshToken(MicrosoftToken $token): MicrosoftToken
    {
        if (!$token->getRefreshToken()) {
            $this->logger->error('No refresh token available', ['user_id' => $token->getUser()->getId()]);
            throw new \RuntimeException('No refresh token available. Please reconnect your Microsoft account.');
        }

        try {
            $this->logger->info('Attempting to refresh Microsoft token', ['user_id' => $token->getUser()->getId()]);

            $response = $this->httpClient->request('POST', 'https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $token->getRefreshToken(),
                    'scope' => 'openid profile email Tasks.ReadWrite Tasks.ReadWrite.Shared https://graph.microsoft.com/Tasks.ReadWrite https://graph.microsoft.com/Mail.ReadWrite https://graph.microsoft.com/Calendars.ReadWrite',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                $this->logger->error('Token refresh failed', [
                    'user_id' => $token->getUser()->getId(),
                    'status_code' => $statusCode,
                    'response' => $response->getContent(false),
                ]);
                throw new \RuntimeException('Token refresh failed with status '.$statusCode);
            }

            $data = json_decode($response->getContent(), true);

            if (!isset($data['access_token'])) {
                $this->logger->error('No access token in refresh response', [
                    'user_id' => $token->getUser()->getId(),
                    'response_data' => $data,
                ]);
                throw new \RuntimeException('Invalid token refresh response');
            }

            $token->setAccessToken($data['access_token']);
            if (isset($data['refresh_token'])) {
                $token->setRefreshToken($data['refresh_token']);
            }
            $token->setExpiresAt((new \DateTime())->modify('+'.$data['expires_in'].' seconds'));

            $this->entityManager->flush();

            $this->logger->info('Token refreshed successfully', [
                'user_id' => $token->getUser()->getId(),
                'expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);

            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing Microsoft token', [
                'user_id' => $token->getUser()->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to refresh Microsoft token. Please reconnect your Microsoft account.');
        }
    }

    public function hasValidToken(User $user): bool
    {
        $token = $user->getMicrosoftToken();

        return $token && !$token->isExpired();
    }

    /**
     * @return array{id: string, title: string, listName: string, status: string, createdDateTime: string}
     */
    public function createTestTask(User $user): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $this->logger->info('Token expired, refreshing token', ['user_id' => $user->getId()]);
            $token = $this->refreshToken($token);
        }

        try {
            $this->logger->info('Attempting to fetch Microsoft To-Do lists', [
                'user_id' => $user->getId(),
                'token_expires' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);

            // D'abord, test de connectivité avec l'API Graph
            $profileResponse = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $this->logger->info('Successfully connected to Graph API', [
                'user_id' => $user->getId(),
                'status_code' => $profileResponse->getStatusCode(),
            ]);

            // Maintenant, essayons d'accéder aux listes de tâches
            $listsResponse = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/todo/lists', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $listsResponse->getStatusCode();
            if (401 === $statusCode) {
                $this->logger->warning('Received 401, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $listsResponse = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/todo/lists', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                ]);
            } elseif (404 === $statusCode) {
                $this->logger->warning('Microsoft To-Do not available for this user', [
                    'user_id' => $user->getId(),
                    'status_code' => $statusCode,
                ]);
                throw new \RuntimeException('Microsoft To-Do is not available for this account. Please ensure Microsoft To-Do is enabled in your Microsoft 365 subscription.');
            }

            $lists = json_decode($listsResponse->getContent(), true);
            $defaultList = null;

            // Trouver la liste par défaut ou prendre la première
            foreach ($lists['value'] ?? [] as $list) {
                if ('defaultList' === $list['wellknownListName']) {
                    $defaultList = $list;
                    break;
                }
            }

            if (!$defaultList && !empty($lists['value'])) {
                $defaultList = $lists['value'][0];
            }

            if (!$defaultList) {
                throw new \RuntimeException('No task list found');
            }

            // Créer une tâche de test
            $taskData = [
                'title' => 'Tâche de test - '.date('d/m/Y H:i:s'),
                'body' => [
                    'content' => 'Cette tâche a été créée automatiquement pour tester l\'intégration Microsoft To-Do.',
                    'contentType' => 'text',
                ],
                'importance' => 'normal',
            ];

            $response = $this->httpClient->request('POST', "https://graph.microsoft.com/v1.0/me/todo/lists/{$defaultList['id']}/tasks", [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $taskData,
            ]);

            $createdTask = json_decode($response->getContent(), true);

            return [
                'id' => $createdTask['id'],
                'title' => $createdTask['title'],
                'listName' => $defaultList['displayName'],
                'status' => $createdTask['status'],
                'createdDateTime' => $createdTask['createdDateTime'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error creating test Microsoft task', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create test Microsoft task: '.$e->getMessage());
        }
    }

    /**
     * @return array<int, array{id: string, subject: string, status: string, importance: string, createdDateTime: string, dueDateTime: string|null, completedDateTime: string|null, body: string}>
     */
    public function getOutlookTasks(User $user): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/outlook/tasks', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (401 === $statusCode) {
                $this->logger->warning('Received 401 in getOutlookTasks, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $response = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/outlook/tasks', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                ]);
            }

            $tasks = json_decode($response->getContent(), true);
            $allTasks = [];

            foreach ($tasks['value'] ?? [] as $task) {
                $allTasks[] = [
                    'id' => $task['id'],
                    'subject' => $task['subject'] ?? 'Sans titre',
                    'status' => $task['status'],
                    'importance' => $task['importance'] ?? 'normal',
                    'createdDateTime' => $task['createdDateTime'],
                    'dueDateTime' => $task['dueDateTime']['dateTime'] ?? null,
                    'completedDateTime' => $task['completedDateTime']['dateTime'] ?? null,
                    'body' => $task['body']['content'] ?? '',
                ];
            }

            return $allTasks;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Outlook tasks', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to fetch Outlook tasks: '.$e->getMessage());
        }
    }

    /**
     * @return array{id: string, subject: string, status: string, createdDateTime: string}
     */
    public function createOutlookTestTask(User $user): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $this->logger->info('Token expired, refreshing token', ['user_id' => $user->getId()]);
            $token = $this->refreshToken($token);
        }

        try {
            $this->logger->info('Attempting to create Outlook task', [
                'user_id' => $user->getId(),
                'token_expires' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);

            // Créer une tâche de test Outlook
            $taskData = [
                'subject' => 'Tâche Outlook de test - '.date('d/m/Y H:i:s'),
                'body' => [
                    'content' => 'Cette tâche Outlook a été créée automatiquement pour tester l\'intégration Microsoft Outlook.',
                    'contentType' => 'text',
                ],
                'importance' => 'normal',
                'status' => 'notStarted',
            ];

            $response = $this->httpClient->request('POST', 'https://graph.microsoft.com/v1.0/me/outlook/tasks', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $taskData,
            ]);

            $createdTask = json_decode($response->getContent(), true);

            return [
                'id' => $createdTask['id'],
                'subject' => $createdTask['subject'],
                'status' => $createdTask['status'],
                'createdDateTime' => $createdTask['createdDateTime'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error creating test Outlook task', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create test Outlook task: '.$e->getMessage());
        }
    }

    /**
     * @return array<int, array{id: string, name: string, canEdit: bool, isDefaultCalendar: bool}>
     */
    public function getUserCalendars(User $user): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://graph.microsoft.com/v1.0/me/calendars', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $calendars = json_decode($response->getContent(), true);
            $result = [];

            foreach ($calendars['value'] ?? [] as $calendar) {
                $result[] = [
                    'id' => $calendar['id'],
                    'name' => $calendar['name'],
                    'canEdit' => $calendar['canEdit'] ?? true,
                    'isDefaultCalendar' => $calendar['isDefaultCalendar'] ?? false,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching calendars', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to fetch calendars: '.$e->getMessage());
        }
    }

    /**
     * @return array{id: string, subject: string, start: string, end: string, location: string}
     */
    public function createCalendarEvent(User $user, ?string $calendarId = null): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $this->logger->info('Token expired, refreshing token', ['user_id' => $user->getId()]);
            $token = $this->refreshToken($token);
        }

        try {
            $this->logger->info('Attempting to create calendar event', [
                'user_id' => $user->getId(),
                'token_expires' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);

            // Créer un événement de calendrier dans 2 heures, durée 1 heure
            $startDateTime = new \DateTime('+2 hours');
            $endDateTime = new \DateTime('+3 hours');

            $userTimezone = $user->getTimezone();

            $eventData = [
                'subject' => 'Événement de test - '.date('d/m/Y H:i:s'),
                'body' => [
                    'contentType' => 'HTML',
                    'content' => 'Cet événement a été créé automatiquement pour tester l\'intégration Microsoft Calendar.',
                ],
                'start' => [
                    'dateTime' => $startDateTime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $userTimezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $userTimezone,
                ],
                'location' => [
                    'displayName' => 'Bureau - Test',
                ],
                'isOnlineMeeting' => false,
            ];

            // Use specific calendar if provided, otherwise use default
            $url = $calendarId
                ? "https://graph.microsoft.com/v1.0/me/calendars/{$calendarId}/events"
                : 'https://graph.microsoft.com/v1.0/me/events';

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $eventData,
            ]);

            $createdEvent = json_decode($response->getContent(), true);

            $this->logger->info('Calendar event created successfully', [
                'user_id' => $user->getId(),
                'event_id' => $createdEvent['id'],
            ]);

            return [
                'id' => $createdEvent['id'],
                'subject' => $createdEvent['subject'],
                'start' => $createdEvent['start']['dateTime'],
                'end' => $createdEvent['end']['dateTime'],
                'location' => $createdEvent['location']['displayName'] ?? '',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error creating calendar event', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create calendar event: '.$e->getMessage());
        }
    }

    /**
     * Create a calendar event from CalendarEvent entity.
     *
     * @return array{id: string, subject: string, start: array{dateTime: string, timeZone: string}, end: array{dateTime: string, timeZone: string}, location: array{displayName: string}}
     */
    public function createEventFromCalendarEvent(User $user, string $title, string $startDateTime, string $endDateTime, ?string $description = null, ?string $location = null): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $this->logger->info('Token expired, refreshing token', ['user_id' => $user->getId()]);
            $token = $this->refreshToken($token);
        }

        try {
            $userTimezone = $user->getTimezone();

            $eventData = [
                'subject' => $title,
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => $userTimezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => $userTimezone,
                ],
                'isOnlineMeeting' => false,
            ];

            if ($description) {
                $eventData['body'] = [
                    'contentType' => 'HTML',
                    'content' => $description,
                ];
            }

            if ($location) {
                $eventData['location'] = [
                    'displayName' => $location,
                ];
            }

            $calendarId = $user->getDefaultCalendarId();
            $url = $calendarId
                ? "https://graph.microsoft.com/v1.0/me/calendars/{$calendarId}/events"
                : 'https://graph.microsoft.com/v1.0/me/events';

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $eventData,
            ]);

            $statusCode = $response->getStatusCode();
            if (401 === $statusCode) {
                $this->logger->warning('Received 401 when creating event, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $eventData,
                ]);
            }

            $createdEvent = json_decode($response->getContent(), true);

            $this->logger->info('Calendar event created from CalendarEvent entity', [
                'user_id' => $user->getId(),
                'event_id' => $createdEvent['id'],
                'title' => $title,
            ]);

            return $createdEvent;
        } catch (\Exception $e) {
            $this->logger->error('Error creating calendar event from entity', [
                'user_id' => $user->getId(),
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create calendar event: '.$e->getMessage());
        }
    }

    /**
     * Get a specific event by its ID.
     *
     * @return array<string, mixed>|null
     */
    public function getEventById(User $user, string $eventId): ?array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        try {
            $response = $this->httpClient->request('GET', "https://graph.microsoft.com/v1.0/me/events/{$eventId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (404 === $statusCode) {
                // Event not found or deleted
                return null;
            }

            if (401 === $statusCode) {
                $this->logger->warning('Received 401 when fetching event, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $response = $this->httpClient->request('GET', "https://graph.microsoft.com/v1.0/me/events/{$eventId}", [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                ]);

                if (404 === $response->getStatusCode()) {
                    return null;
                }
            }

            return json_decode($response->getContent(), true);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching event by ID', [
                'user_id' => $user->getId(),
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            // Return null if event not found, rethrow other exceptions
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }

            throw new \RuntimeException('Failed to fetch event: '.$e->getMessage());
        }
    }

    /**
     * Update an existing calendar event.
     *
     * @param array<string, mixed> $eventData
     *
     * @return array<string, mixed>
     */
    public function updateEvent(User $user, string $eventId, array $eventData): array
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        try {
            $response = $this->httpClient->request('PATCH', "https://graph.microsoft.com/v1.0/me/events/{$eventId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $eventData,
            ]);

            $statusCode = $response->getStatusCode();
            if (401 === $statusCode) {
                $this->logger->warning('Received 401 when updating event, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $response = $this->httpClient->request('PATCH', "https://graph.microsoft.com/v1.0/me/events/{$eventId}", [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $eventData,
                ]);
            }

            $updatedEvent = json_decode($response->getContent(), true);

            $this->logger->info('Calendar event updated successfully', [
                'user_id' => $user->getId(),
                'event_id' => $eventId,
            ]);

            return $updatedEvent;
        } catch (\Exception $e) {
            $this->logger->error('Error updating calendar event', [
                'user_id' => $user->getId(),
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to update calendar event: '.$e->getMessage());
        }
    }

    /**
     * Delete a calendar event from Microsoft.
     */
    public function deleteEvent(User $user, string $eventId): void
    {
        $token = $user->getMicrosoftToken();
        if (!$token) {
            throw new \RuntimeException('User has no Microsoft token');
        }

        if ($token->isExpired()) {
            $token = $this->refreshToken($token);
        }

        try {
            $response = $this->httpClient->request('DELETE', "https://graph.microsoft.com/v1.0/me/events/{$eventId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if (401 === $statusCode) {
                $this->logger->warning('Received 401 when deleting event, attempting token refresh', ['user_id' => $user->getId()]);
                $token = $this->refreshToken($token);

                // Retry with refreshed token
                $response = $this->httpClient->request('DELETE', "https://graph.microsoft.com/v1.0/me/events/{$eventId}", [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token->getAccessToken(),
                    ],
                ]);
            }

            $this->logger->info('Calendar event deleted from Microsoft', [
                'user_id' => $user->getId(),
                'event_id' => $eventId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting calendar event', [
                'user_id' => $user->getId(),
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to delete calendar event: '.$e->getMessage());
        }
    }
}
