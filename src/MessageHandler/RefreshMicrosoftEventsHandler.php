<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RefreshMicrosoftEventsMessage;
use App\Repository\UserRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshMicrosoftEventsHandler
{
    private const int TTL_EVENTS = 300; // 5 minutes

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CacheItemPoolInterface $microsoftGraphCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshMicrosoftEventsMessage $message): void
    {
        $userId = $message->getUserId();
        $calendarId = $message->getCalendarId();

        $this->logger->info('Refreshing Microsoft events in background', [
            'user_id' => $userId,
            'calendar_id' => $calendarId,
        ]);

        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                $this->logger->error('User not found for refresh', ['user_id' => $userId]);

                return;
            }

            $token = $user->getMicrosoftToken();
            if (!$token || $token->isExpired()) {
                $this->logger->warning('User has no valid Microsoft token', ['user_id' => $userId]);

                return;
            }

            // Fetch fresh data from Microsoft
            $url = sprintf(
                'https://graph.microsoft.com/v1.0/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$orderby=start/dateTime&$top=100',
                $calendarId,
                $message->getStartDate()->format('Y-m-d\TH:i:s'),
                $message->getEndDate()->format('Y-m-d\TH:i:s')
            );

            $httpClient = HttpClient::create();
            $response = $httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'Prefer' => 'outlook.timezone="'.$user->getTimezone().'"',
                ],
                'timeout' => 30,
            ]);

            $data = json_decode($response->getContent(), true);
            $events = $data['value'] ?? [];

            // Update cache
            $cacheKey = sprintf(
                'ms_graph:%d:events:%s:%s:%s',
                $userId,
                $calendarId,
                $message->getStartDate()->format('Y-m-d'),
                $message->getEndDate()->format('Y-m-d')
            );

            $cacheItem = $this->microsoftGraphCache->getItem($cacheKey);
            $cacheItem->set([
                'data' => $events,
                'timestamp' => time(),
            ]);
            $cacheItem->expiresAfter(self::TTL_EVENTS);
            $this->microsoftGraphCache->save($cacheItem);

            $this->logger->info('Microsoft events refreshed successfully', [
                'user_id' => $userId,
                'calendar_id' => $calendarId,
                'count' => count($events),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error refreshing Microsoft events', [
                'user_id' => $userId,
                'calendar_id' => $calendarId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
